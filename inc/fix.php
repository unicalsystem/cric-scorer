<?php
/**
 * Match correction desk - v1.42 (administrator only, opened from fix.php on a laptop)
 *
 * For a match that is already finished: pick the match, the over and the ball, and correct who batted,
 * who bowled, the runs, the extra and the wicket of any ball - or add a missed ball / remove a wrong one.
 *
 * Unlike the scorer's "Edit ball" (which replays every later ball through the scoring engine and refuses
 * anything that breaks the flow), the desk edits the ball rows directly and then rebuilds everything that
 * is derived from them: over / ball numbers, innings totals, the batting order, the chase target, the
 * result, Player of the Match and (because they are worked out from the balls) every scorecard, points
 * table, leaderboard and career page.
 *
 * Every change first saves a snapshot of the match, so "Undo" and "Restore the original" always work.
 */

const FIX_SNAPSHOT_KEEP = 40;

function schema_v142(): void {
    if (val("SHOW TABLES LIKE 'fix_snapshots'")) return;
    db()->exec("CREATE TABLE IF NOT EXISTS fix_snapshots (id INT AUTO_INCREMENT PRIMARY KEY, match_id INT NOT NULL, user_id INT NULL, who VARCHAR(100) NULL,
        note VARCHAR(255) NULL, score VARCHAR(120) NULL, data LONGTEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (match_id), FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---------- Reading ---------- */

/** Which matches the desk can open: finished ones (and rain "no result" matches that have balls) */
function fix_can_open(array $m): bool {
    if ($m['status'] === 'completed') return true;
    return $m['status'] === 'abandoned' && (bool)val('SELECT 1 FROM balls b JOIN innings i ON i.id=b.innings_id WHERE i.match_id=? LIMIT 1', [$m['id']]);
}

function fix_matches(array $in): array {
    $where = !empty($in['all']) ? ['1=1'] : ["(m.status='completed' OR (m.status='abandoned' AND EXISTS (SELECT 1 FROM innings i JOIN balls b ON b.innings_id=i.id WHERE i.match_id=m.id)))"];   // v1.47: 'all' also lists live and upcoming matches (squad tools only)
    $p = [];
    $tid = (int)($in['tournament_id'] ?? 0);
    if ($tid === -1) $where[] = 'm.tournament_id IS NULL';
    elseif ($tid > 0) { $where[] = 'm.tournament_id=?'; $p[] = $tid; }
    date_filter($in, 'm.match_date', $where, $p);
    $q = trim((string)($in['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(t1.name LIKE ? OR t2.name LIKE ? OR m.title LIKE ? OR tr.name LIKE ? OR m.id=?)';
        $like = '%' . $q . '%';
        array_push($p, $like, $like, $like, $like, ctype_digit($q) ? (int)$q : 0);
    }
    $list = rows("SELECT m.id, m.title, m.round_name, m.match_date, m.status, m.result_text, m.tournament_id, m.overs, m.updated_at,
            t1.name t1, t1.short_name s1, t1.color c1, t2.name t2, t2.short_name s2, t2.color c2, tr.name tournament,
            (SELECT COUNT(*) FROM fix_snapshots s WHERE s.match_id=m.id) fixes
        FROM matches m JOIN teams t1 ON t1.id=m.team1_id JOIN teams t2 ON t2.id=m.team2_id LEFT JOIN tournaments tr ON tr.id=m.tournament_id
        WHERE " . implode(' AND ', $where) . ' ORDER BY COALESCE(m.match_date, m.created_at) DESC, m.id DESC LIMIT 300', $p);
    $scores = [];
    if ($list) {
        $ids = implode(',', array_map(fn($r) => (int)$r['id'], $list));
        foreach (rows("SELECT i.match_id, i.innings_no, i.runs, i.wickets, i.legal_balls, i.is_super, t.short_name FROM innings i JOIN teams t ON t.id=i.batting_team_id WHERE i.match_id IN ($ids) ORDER BY i.innings_no") as $i)
            $scores[(int)$i['match_id']][] = $i;
    }
    $bpoOf = [];
    foreach ($list as &$r) {
        $bpoOf[$r['id']] = $bpoOf[$r['id']] ?? (int)val('SELECT balls_per_over FROM matches WHERE id=?', [$r['id']]);
        $r['scores'] = array_map(fn($i) => ['team' => $i['short_name'], 'text' => $i['runs'] . '/' . $i['wickets'] . ' (' . overs_str((int)$i['legal_balls'], $bpoOf[$r['id']]) . ')', 'super' => (bool)$i['is_super']], $scores[(int)$r['id']] ?? []);
        $r['fixes'] = (int)$r['fixes'];
    }
    unset($r);
    $tours = rows("SELECT tr.id, tr.name, COUNT(*) n FROM matches m JOIN tournaments tr ON tr.id=m.tournament_id WHERE m.status IN ('completed','abandoned') GROUP BY tr.id, tr.name ORDER BY tr.id DESC");
    return ['matches' => $list, 'tournaments' => $tours];
}

/** Everyone who can be chosen for a side: the players picked for the match, then the rest of the team's squad */
function fix_side_players(int $matchId, int $teamId): array {
    $out = []; $seen = [];
    foreach (rows('SELECT p.id, p.name, p.role, mp.bat_order FROM match_players mp JOIN players p ON p.id=mp.player_id WHERE mp.match_id=? AND mp.team_id=? ORDER BY mp.bat_order, p.id', [$matchId, $teamId]) as $p) {
        $out[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'role' => $p['role'], 'xi' => true]; $seen[(int)$p['id']] = true;
    }
    foreach (rows('SELECT id, name, role FROM players WHERE team_id=? ORDER BY name', [$teamId]) as $p)
        if (empty($seen[(int)$p['id']])) $out[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'role' => $p['role'], 'xi' => false];
    return $out;
}

/** Player ids that appear in any ball of the match (bat, bowl, out or in the field) */
function fix_involved(int $matchId): array {
    $ids = [];
    foreach (rows('SELECT b.batsman_id, b.non_striker_id, b.bowler_id, b.dismissed_id, b.fielder_id, b.drop_id, b.field_id, b.fielded_id FROM balls b JOIN innings i ON i.id=b.innings_id WHERE i.match_id=?', [$matchId]) as $b)
        foreach ($b as $v) if ($v) $ids[(int)$v] = true;
    return $ids;
}

/** What the scorer would have tapped for this ball: runs + extra */
function fix_ball_runs(array $b): int {
    if (in_array($b['wicket_type'], NON_DELIVERY, true)) return 0;
    switch ($b['extra_type']) {
        case 'wd': return max(0, (int)$b['extra_runs'] - 1);
        case 'b': case 'lb': return (int)$b['extra_runs'];
        default: return (int)$b['runs_bat'];
    }
}

function fix_match_data(int $matchId): array {
    $m = load_match($matchId);
    $open = fix_can_open($m);   // v1.47: live / upcoming matches open too, for the squad tools only (balls stay locked to the scorer)
    $bpo = (int)$m['balls_per_over'];
    $teams = [];
    foreach ([(int)$m['team1_id'], (int)$m['team2_id']] as $tid) {
        $t = row('SELECT id, name, short_name, color FROM teams WHERE id=?', [$tid]);
        $t['id'] = (int)$t['id'];
        $t['players'] = fix_side_players($matchId, $tid);
        $teams[] = $t;
    }
    $involved = fix_involved($matchId);
    foreach ($teams as &$t) foreach ($t['players'] as &$p) $p['played'] = !empty($involved[$p['id']]);
    unset($t, $p);

    $innings = [];
    foreach (rows('SELECT * FROM innings WHERE match_id=? ORDER BY innings_no', [$matchId]) as $inn) {
        $balls = [];
        foreach (rows('SELECT * FROM balls WHERE innings_id=? ORDER BY seq, id', [$inn['id']]) as $b) {
            $balls[] = ['id' => (int)$b['id'], 'seq' => (int)$b['seq'], 'over' => (int)$b['over_no'], 'ball' => (int)$b['ball_no'],
                'batsman_id' => (int)$b['batsman_id'], 'non_striker_id' => nint($b['non_striker_id']), 'bowler_id' => (int)$b['bowler_id'],
                'runs' => fix_ball_runs($b), 'extra' => $b['extra_type'], 'total' => (int)$b['total_runs'], 'legal' => (bool)$b['is_legal'],
                'wicket' => $b['wicket_type'], 'dismissed_id' => nint($b['dismissed_id']), 'fielder_id' => nint($b['fielder_id']),
                'bouncer' => !empty($b['is_bouncer']), 'label' => ball_label($b)];
        }
        $innings[] = ['id' => (int)$inn['id'], 'no' => (int)$inn['innings_no'], 'batting_team_id' => (int)$inn['batting_team_id'], 'bowling_team_id' => (int)$inn['bowling_team_id'],
            'runs' => (int)$inn['runs'], 'wickets' => (int)$inn['wickets'], 'legal_balls' => (int)$inn['legal_balls'], 'overs' => overs_str((int)$inn['legal_balls'], $bpo),
            'extras' => (int)$inn['extras'], 'penalties' => penalty_runs_for_innings((int)$inn['id']), 'target' => $inn['target'] === null ? null : (int)$inn['target'],
            'max_overs' => (int)$inn['max_overs'], 'is_super' => !empty($inn['is_super']), 'dls' => !empty($inn['dls']), 'balls' => $balls];
    }
    $snaps = rows('SELECT id, who, note, score, created_at FROM fix_snapshots WHERE match_id=? ORDER BY id DESC LIMIT 15', [$matchId]);
    $first = row('SELECT id, who, note, score, created_at FROM fix_snapshots WHERE match_id=? ORDER BY id LIMIT 1', [$matchId]);
    return [
        'match' => ['id' => (int)$m['id'], 'balls_open' => $open, 'title' => $m['title'], 'round' => $m['round_name'] ?? null, 'date' => $m['match_date'], 'status' => $m['status'],
            'result' => $m['result_text'], 'winner_team_id' => nint($m['winner_team_id']), 'result_method' => $m['result_method'],
            'overs' => (int)$m['overs'], 'bpo' => $bpo, 'players_per_side' => (int)$m['players_per_side'], 'extra_players' => !empty($m['extra_players']),
            'max_overs_bowler' => (int)$m['max_overs_bowler'], 'last_man_stands' => !empty($m['last_man_stands']), 'test' => is_test($m),
            'tournament' => $m['tournament_id'] ? val('SELECT name FROM tournaments WHERE id=?', [$m['tournament_id']]) : null,
            'pom' => $m['player_of_match_id'] ? val('SELECT name FROM players WHERE id=?', [$m['player_of_match_id']]) : null],
        'teams' => $teams, 'innings' => $innings, 'warnings' => fix_warnings($m),
        'history' => $snaps, 'original' => $first, 'wicket_types' => WICKET_TYPES, 'bowler_wickets' => BOWLER_WICKETS, 'non_delivery' => NON_DELIVERY,
    ];
}

/** Things that look wrong in the ball-by-ball record - a to-do list for the admin, never a block */
function fix_warnings(array $m): array {
    $w = [];
    $bpo = (int)$m['balls_per_over'];
    $names = [];
    foreach (rows('SELECT id, name FROM players WHERE team_id IN (?,?)', [$m['team1_id'], $m['team2_id']]) as $p) $names[(int)$p['id']] = $p['name'];
    foreach (rows('SELECT p.id, p.name FROM match_players mp JOIN players p ON p.id=mp.player_id WHERE mp.match_id=?', [$m['id']]) as $p) $names[(int)$p['id']] = $p['name'];
    $nm = fn($id) => $names[(int)$id] ?? ('player #' . (int)$id);
    $add = function (int $inn, ?int $over, string $text) use (&$w) { $w[] = ['inn' => $inn, 'over' => $over, 'text' => $text]; };

    if (empty($m['extra_players'])) {
        foreach ([(int)$m['team1_id'], (int)$m['team2_id']] as $tid) {
            $n = count(xi_ids((int)$m['id'], $tid));
            if ($n > (int)$m['players_per_side']) $add(0, null, team_name($tid) . " has $n players listed for a {$m['players_per_side']}-a-side match - remove the one who did not play (Playing lists, below the balls)");
        }
    }
    foreach (rows('SELECT * FROM innings WHERE match_id=? ORDER BY innings_no', [$m['id']]) as $inn) {
        $no = (int)$inn['innings_no'];
        $balls = rows('SELECT * FROM balls WHERE innings_id=? ORDER BY seq, id', [$inn['id']]);
        $overBowlers = []; $out = []; $score = penalty_runs_for_innings((int)$inn['id']); $passedAt = null; $legal = 0;
        foreach ($balls as $i => $b) {
            $ov = (int)$b['over_no'];
            if (!in_array($b['wicket_type'], NON_DELIVERY, true)) $overBowlers[$ov][(int)$b['bowler_id']] = true;
            foreach ([(int)$b['batsman_id'], (int)$b['non_striker_id']] as $pid)
                if ($pid && isset($out[$pid])) { $add($no, $ov, $nm($pid) . ' is batting in over ' . ($ov + 1) . ' but was out in over ' . ($out[$pid] + 1)); unset($out[$pid]); }
            if ((int)$b['batsman_id'] === (int)$b['non_striker_id']) $add($no, $ov, 'Over ' . ($ov + 1) . ': ' . $nm($b['batsman_id']) . ' is both striker and non-striker');
            if ($passedAt !== null && $passedAt !== -1) { $add($no, $ov, 'The target of ' . $inn['target'] . ' was reached at ' . $passedAt . ', but balls were scored after it'); $passedAt = -1; }
            if ($b['is_wicket'] && $b['dismissed_id']) $out[(int)$b['dismissed_id']] = $ov;
            $score += (int)$b['total_runs']; $legal += (int)$b['is_legal'];
            if ($inn['target'] !== null && $passedAt === null && $score >= (int)$inn['target']) $passedAt = $b['over_no'] . '.' . $b['ball_no'];
        }
        ksort($overBowlers);
        $prev = null; $prevOv = null; $oversBy = [];
        foreach ($overBowlers as $ov => $set) {
            $ids = array_keys($set);
            if (count($ids) > 1) $add($no, $ov, 'Over ' . ($ov + 1) . ' has ' . count($ids) . ' bowlers: ' . implode(', ', array_map($nm, $ids)));
            elseif ($prev !== null && $prevOv === $ov - 1 && $ids[0] === $prev) $add($no, $ov, $nm($prev) . ' bowled overs ' . $ov . ' and ' . ($ov + 1) . ' back to back');
            $prev = count($ids) === 1 ? $ids[0] : null; $prevOv = $ov;
            foreach ($ids as $id) $oversBy[$id] = ($oversBy[$id] ?? 0) + 1;
        }
        $maxB = (int)$m['max_overs_bowler'];
        if ($maxB > 0 && empty($m['extra_bowlers']) && empty($inn['is_super']))
            foreach ($oversBy as $id => $n) if ($n > $maxB) $add($no, null, $nm($id) . " bowled in $n overs (the limit is $maxB)");
        $cap = (int)$inn['max_overs'] * $bpo;
        if (!is_test($m) && $legal > $cap) $add($no, null, ($legal - $cap) . ' ball(s) more than the ' . $inn['max_overs'] . ' overs of this innings - delete the extra ball(s) or mark them wide / no-ball');
        $limit = bat_limit($m, (int)$inn['batting_team_id']);
        $maxW = !empty($inn['is_super']) ? SUPER_OVER_WICKETS : ($m['last_man_stands'] ? $limit : $limit - 1);
        if ((int)$inn['wickets'] > $maxW && $maxW > 0) $add($no, null, $inn['wickets'] . ' wickets, but only ' . $maxW . ' can fall');
        if (!is_test($m) && $inn['target'] !== null && $m['result_method'] !== 'rain' && (int)$inn['runs'] < (int)$inn['target'] - 1 && $legal < $cap && (int)$inn['wickets'] < $maxW)
            $add($no, null, 'The chase stopped at ' . $inn['runs'] . '/' . $inn['wickets'] . ' after ' . overs_str($legal, $bpo) . ' of ' . $inn['max_overs'] . ' overs, still ' . ((int)$inn['target'] - (int)$inn['runs']) . ' short of the target with wickets in hand - balls may be missing');
    }
    return $w;
}

/* ---------- Snapshots: undo & restore ---------- */

function fix_score_line(int $matchId): string {
    $m = load_match($matchId); $bpo = (int)$m['balls_per_over']; $parts = [];
    foreach (rows('SELECT i.runs, i.wickets, i.legal_balls, t.short_name FROM innings i JOIN teams t ON t.id=i.batting_team_id WHERE i.match_id=? ORDER BY i.innings_no', [$matchId]) as $i)
        $parts[] = $i['short_name'] . ' ' . $i['runs'] . '/' . $i['wickets'] . ' (' . overs_str((int)$i['legal_balls'], $bpo) . ')';
    return mb_substr(implode(' · ', $parts), 0, 120);
}

function fix_snapshot(array $m, ?array $u, string $note): int {
    $mid = (int)$m['id'];
    $inns = rows('SELECT * FROM innings WHERE match_id=? ORDER BY innings_no', [$mid]);
    $innIds = array_map(fn($i) => (int)$i['id'], $inns);
    $in = $innIds ? implode(',', $innIds) : '0';
    $data = [
        'match' => row('SELECT status, winner_team_id, result_text, result_method, player_of_match_id, best_bat_id, best_bowl_id FROM matches WHERE id=?', [$mid]),
        'innings' => $inns,
        'balls' => rows("SELECT * FROM balls WHERE innings_id IN ($in) ORDER BY innings_id, seq, id"),
        'innings_batters' => rows("SELECT * FROM innings_batters WHERE innings_id IN ($in) ORDER BY innings_id, position"),
        'match_players' => rows('SELECT * FROM match_players WHERE match_id=? ORDER BY team_id, bat_order', [$mid]),
    ];
    q('INSERT INTO fix_snapshots (match_id, user_id, who, note, score, data) VALUES (?,?,?,?,?,?)',
        [$mid, $u['id'] ?? null, $u['display_name'] ?? null, mb_substr($note, 0, 255), fix_score_line($mid), json_encode($data, JSON_UNESCAPED_UNICODE)]);
    $id = lastId();
    // keep the very first snapshot (the match as it was scored) and the most recent ones
    $firstId = (int)val('SELECT MIN(id) FROM fix_snapshots WHERE match_id=?', [$mid]);
    $old = rows('SELECT id FROM fix_snapshots WHERE match_id=? AND id<>? ORDER BY id DESC LIMIT 1000 OFFSET ' . FIX_SNAPSHOT_KEEP, [$mid, $firstId]);
    foreach ($old as $o) q('DELETE FROM fix_snapshots WHERE id=?', [$o['id']]);
    return $id;
}

function fix_insert_rows(string $table, array $list): void {
    if (!$list) return;
    $cols = array_map(fn($c) => $c['Field'], rows("SHOW COLUMNS FROM `$table`"));
    foreach ($list as $r) {
        $r = array_intersect_key($r, array_flip($cols));
        q("INSERT INTO `$table` (`" . implode('`,`', array_keys($r)) . '`) VALUES (' . implode(',', array_fill(0, count($r), '?')) . ')', array_values($r));
    }
}

/** Put the match back exactly as it was when the snapshot was taken */
function fix_restore_snapshot(array $m, int $snapId): void {
    $s = row('SELECT * FROM fix_snapshots WHERE id=? AND match_id=?', [$snapId, $m['id']]);
    if (!$s) fail('That saved copy was not found');
    $d = json_decode($s['data'], true);
    if (!is_array($d) || !isset($d['innings'], $d['balls'])) fail('That saved copy cannot be read');
    $mid = (int)$m['id'];
    $now = array_map(fn($r) => (int)$r['id'], rows('SELECT id FROM innings WHERE match_id=?', [$mid]));
    $then = array_map(fn($r) => (int)$r['id'], $d['innings']);
    sort($now); sort($then);
    if ($now !== $then) fail('The innings of this match have changed since that copy was saved - it cannot be restored');
    $in = $then ? implode(',', $then) : '0';
    q("DELETE FROM balls WHERE innings_id IN ($in)");
    q("DELETE FROM innings_batters WHERE innings_id IN ($in)");
    q('DELETE FROM match_players WHERE match_id=?', [$mid]);
    $innCols = array_map(fn($c) => $c['Field'], rows('SHOW COLUMNS FROM innings'));
    foreach ($d['innings'] as $r) {
        $id = (int)$r['id'];
        $r = array_intersect_key($r, array_flip($innCols)); unset($r['id'], $r['match_id']);
        q('UPDATE innings SET ' . implode(',', array_map(fn($c) => "`$c`=?", array_keys($r))) . ' WHERE id=?', array_merge(array_values($r), [$id]));
    }
    fix_insert_rows('balls', $d['balls']);
    fix_insert_rows('innings_batters', $d['innings_batters'] ?? []);
    fix_insert_rows('match_players', $d['match_players'] ?? []);
    $x = $d['match'];
    q('UPDATE matches SET status=?, winner_team_id=?, result_text=?, result_method=?, player_of_match_id=?, best_bat_id=?, best_bowl_id=? WHERE id=?',
        [$x['status'], $x['winner_team_id'], $x['result_text'], $x['result_method'], $x['player_of_match_id'], $x['best_bat_id'], $x['best_bowl_id'], $mid]);
    if (!empty($m['tournament_id']) && ($m['stage'] ?? 'league') !== 'league') playoff_advance((int)$m['tournament_id']);
}

/* ---------- Writing ---------- */

/** Turn what the admin chose for a ball into the stored columns (same rules as live scoring, minus the flow checks) */
function fix_ball_values(array $m, array $inn, array $in, array $bat, array $bowl): array {
    $s = (int)($in['batsman_id'] ?? 0); $ns = nint($in['non_striker_id'] ?? null); $bw = (int)($in['bowler_id'] ?? 0);
    if (!isset($bat[$s])) fail('Choose the striker from the batting side');
    if ($ns !== null && !isset($bat[$ns])) fail('Choose the non-striker from the batting side');
    if ($ns === $s) fail('The striker and the non-striker cannot be the same player');
    if ($ns === null && empty($m['last_man_stands'])) fail('Choose the non-striker');
    if (!isset($bowl[$bw])) fail('Choose the bowler from the bowling side');

    $extra = $in['extra'] ?? null;
    if ($extra === '') $extra = null;
    if ($extra !== null && !in_array($extra, ['wd', 'nb', 'b', 'lb'], true)) fail('Unknown extra');
    $runs = max(0, min(7, (int)($in['runs'] ?? 0)));
    $runsBat = 0; $extraRuns = 0;
    switch ($extra) {
        case null: $runsBat = $runs; break;
        case 'wd': $extraRuns = 1 + $runs; break;
        case 'nb': $runsBat = $runs; $extraRuns = 1; break;
        default:
            if ($runs < 1) fail('Byes and leg byes need at least 1 run');
            $extraRuns = $runs;
    }
    $legal = in_array($extra, ['wd', 'nb'], true) ? 0 : 1;
    $bouncer = !empty($in['bouncer']) ? 1 : 0;

    $wType = trim((string)($in['wicket'] ?? '')); if ($wType === '') $wType = null;
    $isW = 0; $dismissed = null; $fielder = null;
    if ($wType !== null) {
        if (!in_array($wType, WICKET_TYPES, true)) fail('Unknown dismissal type');
        $nonDelivery = in_array($wType, NON_DELIVERY, true);
        if (!$nonDelivery) {
            if ($extra === 'wd' && !in_array($wType, ['stumped', 'run out', 'hit wicket', 'obstructing the field'], true)) fail('On a wide only stumped, run out, hit wicket or obstructing the field is possible');
            if ($extra === 'nb' && !in_array($wType, FIELD_WICKETS, true)) fail('On a no-ball only run out, obstructing the field or hit the ball twice is possible');
            if (in_array($extra, ['b', 'lb'], true) && !in_array($wType, FIELD_WICKETS, true)) fail('With byes only run out, obstructing the field or hit the ball twice is possible');
        }
        $d = nint($in['dismissed_id'] ?? null);
        if ($wType === 'timed out') {
            if (!$d || !isset($bat[$d])) fail('Choose the batter who was timed out');
            $dismissed = $d;
        } elseif (in_array($wType, FIELD_WICKETS, true) || in_array($wType, ['retired hurt', 'retired out'], true)) {
            $dismissed = $d ?: $s;
            if ($dismissed !== $s && $dismissed !== $ns) fail('The batter who is out must be the striker or the non-striker of that ball');
        } else {
            $dismissed = $s;
        }
        $isW = $wType === 'retired hurt' ? 0 : 1;
        $fielder = nint($in['fielder_id'] ?? null);
        if ($fielder && !isset($bowl[$fielder])) fail('The fielder must be from the bowling side');
        if ($nonDelivery) { $runsBat = 0; $extraRuns = 0; $legal = 0; $extra = null; $bouncer = 0; }
    }
    return ['batsman_id' => $s, 'non_striker_id' => $ns, 'bowler_id' => $bw, 'runs_bat' => $runsBat, 'extra_type' => $extra, 'extra_runs' => $extraRuns,
        'total_runs' => $runsBat + $extraRuns, 'is_legal' => $legal, 'is_bouncer' => $bouncer, 'is_wicket' => $isW, 'wicket_type' => $wType, 'dismissed_id' => $dismissed, 'fielder_id' => $fielder];
}

/** A player chosen from the squad who was not on the match list gets added to it */
function fix_ensure_listed(array $m, int $teamId, array $pids): void {
    foreach (array_unique(array_filter(array_map('intval', $pids))) as $pid) {
        if (val('SELECT 1 FROM match_players WHERE match_id=? AND player_id=?', [$m['id'], $pid])) continue;
        $order = (int)val('SELECT COALESCE(MAX(bat_order),0) FROM match_players WHERE match_id=? AND team_id=?', [$m['id'], $teamId]) + 1;
        q('INSERT INTO match_players (match_id, team_id, player_id, bat_order) VALUES (?,?,?,?)', [$m['id'], $teamId, $pid, $order]);
    }
}

function fix_side_map(int $matchId, int $teamId): array {
    $map = [];
    foreach (fix_side_players($matchId, $teamId) as $p) $map[$p['id']] = $p['name'];
    return $map;
}

/** Re-number the balls and rebuild everything the innings derives from them */
function fix_rebuild_innings(array $m, int $innId): void {
    $bpo = (int)$m['balls_per_over'];
    $inn = row('SELECT * FROM innings WHERE id=?', [$innId]);
    $balls = rows('SELECT * FROM balls WHERE innings_id=? ORDER BY seq, id', [$innId]);
    $legal = 0; $seq = 0; $order = [];
    foreach ($balls as $b) {
        $seq++;
        $ov = intdiv($legal, $bpo); $bn = ($legal % $bpo) + 1;
        if ((int)$b['seq'] !== $seq || (int)$b['over_no'] !== $ov || (int)$b['ball_no'] !== $bn) q('UPDATE balls SET seq=?, over_no=?, ball_no=? WHERE id=?', [$seq, $ov, $bn, $b['id']]);
        $legal += (int)$b['is_legal'];
        foreach ([(int)$b['batsman_id'], (int)$b['non_striker_id'], $b['wicket_type'] === 'timed out' ? (int)$b['dismissed_id'] : 0] as $pid)
            if ($pid && !in_array($pid, $order, true)) $order[] = $pid;
    }
    // who is at the crease after the last ball
    $s = nint($inn['striker_id']); $ns = nint($inn['non_striker_id']); $bowler = nint($inn['bowler_id']);
    $wasBatters = array_map('intval', array_column(rows('SELECT player_id FROM innings_batters WHERE innings_id=? ORDER BY position', [$innId]), 'player_id'));
    if ($balls) {
        $last = $balls[count($balls) - 1];
        $oldCrease = array_values(array_filter([$s, $ns]));
        $nextBowler = $bowler;                       // the bowler already chosen for the next over, if any
        $s = (int)$last['batsman_id']; $ns = nint($last['non_striker_id']); $bowler = (int)$last['bowler_id'];
        if (fix_ball_runs($last) % 2 === 1) [$s, $ns] = [$ns, $s];
        if ($last['dismissed_id']) { if ($s === (int)$last['dismissed_id']) $s = null; elseif ($ns === (int)$last['dismissed_id']) $ns = null; }
        if ((int)$last['is_legal'] && $legal % $bpo === 0) { [$s, $ns] = [$ns, $s]; $bowler = ($nextBowler && $nextBowler !== $bowler) ? $nextBowler : null; }
        // a batter who had walked in but never faced a ball stays at the crease
        $outIds = out_ids($innId);
        foreach ($oldCrease as $pid) {
            if (in_array($pid, $order, true) || in_array($pid, $outIds, true) || !in_array($pid, $wasBatters, true)) continue;
            if ($s === null) $s = $pid; elseif ($ns === null) $ns = $pid; else continue;
            $order[] = $pid;
        }
    } else {
        foreach ([$s, $ns] as $pid) if ($pid && !in_array($pid, $order, true)) $order[] = $pid;
    }
    q('DELETE FROM innings_batters WHERE innings_id=?', [$innId]);
    foreach ($order as $i => $pid) q('INSERT INTO innings_batters (innings_id, player_id, position) VALUES (?,?,?)', [$innId, $pid, $i + 1]);
    q('UPDATE innings SET striker_id=?, non_striker_id=?, bowler_id=? WHERE id=?', [$s, $ns, $bowler, $innId]);
    recompute_innings($innId);
}

/** After the balls changed: new target for the chase, new result, new Player of the Match */
function fix_refresh_match(int $matchId): void {
    $m = load_match($matchId);
    foreach (rows('SELECT id FROM innings WHERE match_id=?', [$matchId]) as $i) recompute_innings((int)$i['id']);
    if ($m['status'] === 'abandoned' && $m['result_method'] !== 'rain') return;          // abandoned by hand: stays "no result"
    if (is_test($m)) {
        if ($m['result_method'] === 'draw') {
            $pom = player_of_match($matchId, null); [$bb, $bw] = best_performers($matchId);
            q('UPDATE matches SET player_of_match_id=?, best_bat_id=?, best_bowl_id=? WHERE id=?', [$pom, $bb, $bw, $matchId]);
            return;
        }
        $i4 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=4', [$matchId]);
        if ($i4) {
            $other = (int)$i4['bowling_team_id']; $bat = (int)$i4['batting_team_id'];
            $before = (int)val('SELECT COALESCE(SUM(runs),0) FROM innings WHERE match_id=? AND batting_team_id=? AND innings_no<4', [$matchId, $bat]);
            q('UPDATE innings SET target=? WHERE id=?', [max(1, team_total($matchId, $other) - $before + 1), $i4['id']]);
        }
        finish_match($matchId);
        return;
    }
    dls_update_target($m);
    $i3 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=3', [$matchId]);
    $i4 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=4', [$matchId]);
    if ($i3 && $i4) q('UPDATE innings SET target=? WHERE id=?', [(int)$i3['runs'] + 1, $i4['id']]);
    if ($m['result_method'] === 'rain') {                                               // decided by DLS when rain stopped the chase
        q("UPDATE innings SET status='live' WHERE match_id=? AND innings_no=2", [$matchId]);
        q("UPDATE matches SET status='live', winner_team_id=NULL, result_text=NULL, result_method=NULL WHERE id=?", [$matchId]);
        rain_end_match(load_match($matchId));
        return;
    }
    finish_match($matchId);
}

function fix_innings_of(array $m, int $innId): array {
    $inn = row('SELECT * FROM innings WHERE id=? AND match_id=?', [$innId, $m['id']]);
    if (!$inn) fail('Innings not found');
    return $inn;
}

/**
 * Apply a list of corrections in one go.
 * ops: [{op:'update', ball_id, batsman_id, non_striker_id, bowler_id, runs, extra, wicket, dismissed_id, fielder_id}, {op:'insert', innings_id, after_seq, ...}, {op:'delete', ball_id}]
 */
function fix_apply(array $m, ?array $u, array $in): array {
    if (!fix_can_open($m)) fail('Only finished matches can be corrected here');
    $ops = array_values(array_filter((array)($in['ops'] ?? []), 'is_array'));
    if (!$ops) fail('Nothing to save');
    if (count($ops) > 400) fail('Too many changes at once - save in smaller steps');
    $mid = (int)$m['id'];
    $before = fix_score_line($mid);
    $counts = ['update' => 0, 'insert' => 0, 'delete' => 0];
    $touched = []; $maps = [];
    $side = function (int $teamId) use (&$maps, $mid) { return $maps[$teamId] = $maps[$teamId] ?? fix_side_map($mid, $teamId); };
    $snap = fix_snapshot($m, $u, '');

    foreach ($ops as $op) {
        $kind = (string)($op['op'] ?? '');
        if ($kind === 'delete' || $kind === 'update') {
            $ball = row('SELECT b.* FROM balls b JOIN innings i ON i.id=b.innings_id WHERE b.id=? AND i.match_id=?', [(int)($op['ball_id'] ?? 0), $mid]);
            if (!$ball) fail('A ball in this change no longer exists - reload the match and try again');
            $inn = fix_innings_of($m, (int)$ball['innings_id']);
            if ($kind === 'delete') { q('DELETE FROM balls WHERE id=?', [$ball['id']]); }
            else {
                $op += ['bouncer' => (int)$ball['is_bouncer']];
                $v = fix_ball_values($m, $inn, $op, $side((int)$inn['batting_team_id']), $side((int)$inn['bowling_team_id']));
                q('UPDATE balls SET ' . implode(',', array_map(fn($c) => "$c=?", array_keys($v))) . ' WHERE id=?', array_merge(array_values($v), [$ball['id']]));
                fix_ensure_listed($m, (int)$inn['batting_team_id'], [$v['batsman_id'], $v['non_striker_id'], $v['dismissed_id']]);
                fix_ensure_listed($m, (int)$inn['bowling_team_id'], [$v['bowler_id'], $v['fielder_id']]);
            }
        } elseif ($kind === 'insert') {
            $inn = fix_innings_of($m, (int)($op['innings_id'] ?? 0));
            $after = max(0, (int)($op['after_seq'] ?? 0));
            $v = fix_ball_values($m, $inn, $op, $side((int)$inn['batting_team_id']), $side((int)$inn['bowling_team_id']));
            $near = row('SELECT created_at FROM balls WHERE innings_id=? AND seq<=? ORDER BY seq DESC LIMIT 1', [$inn['id'], $after])
                 ?: row('SELECT created_at FROM balls WHERE innings_id=? ORDER BY seq LIMIT 1', [$inn['id']]);
            q('UPDATE balls SET seq=seq+1 WHERE innings_id=? AND seq>?', [$inn['id'], $after]);
            $v += ['innings_id' => (int)$inn['id'], 'seq' => $after + 1, 'over_no' => 0, 'ball_no' => 0, 'created_at' => $near['created_at'] ?? date('Y-m-d H:i:s')];
            q('INSERT INTO balls (' . implode(',', array_keys($v)) . ') VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')', array_values($v));
            fix_ensure_listed($m, (int)$inn['batting_team_id'], [$v['batsman_id'], $v['non_striker_id'], $v['dismissed_id']]);
            fix_ensure_listed($m, (int)$inn['bowling_team_id'], [$v['bowler_id'], $v['fielder_id']]);
        } else fail('Unknown change');
        $counts[$kind]++;
        $touched[(int)$inn['id']] = true;
    }
    foreach (array_keys($touched) as $innId) fix_rebuild_innings($m, $innId);
    fix_refresh_match($mid);

    $bits = [];
    if ($counts['update']) $bits[] = $counts['update'] . ' ball' . ($counts['update'] === 1 ? '' : 's') . ' corrected';
    if ($counts['insert']) $bits[] = $counts['insert'] . ' added';
    if ($counts['delete']) $bits[] = $counts['delete'] . ' deleted';
    $note = trim((string)($in['note'] ?? ''));
    fix_finish($m, $u, $snap, implode(', ', $bits) . ($note !== '' ? ' - ' . $note : ''), $before);
    return fix_match_data($mid);
}

/** Label the snapshot with what was done and note it in the match log */
function fix_finish(array $m, ?array $u, int $snapId, string $what, string $before): void {
    $after = fix_score_line((int)$m['id']);
    q('UPDATE fix_snapshots SET note=? WHERE id=?', [mb_substr($what, 0, 255), $snapId]);
    match_log($m, 'Admin correction: ' . $what . ($before !== $after ? " (was $before)" : ''), $u);
}

/** The wrong player was picked: put the right one in for an innings or the whole match */
function fix_replace_player(array $m, ?array $u, array $in): array {
    if (!fix_can_open($m)) fail('Only finished matches can be corrected here');
    $mid = (int)$m['id'];
    $from = (int)($in['from'] ?? 0); $to = (int)($in['to'] ?? 0);
    $innId = (int)($in['innings_id'] ?? 0);
    if (!$from || !$to || $from === $to) fail('Choose the wrong player and the player who really played');
    $teamId = 0;
    foreach ([(int)$m['team1_id'], (int)$m['team2_id']] as $tid) { $map = fix_side_map($mid, $tid); if (isset($map[$from]) && isset($map[$to])) $teamId = $tid; }
    if (!$teamId) fail('Both players must be from the same team');
    $roles = array_values(array_intersect((array)($in['roles'] ?? ['bat', 'bowl', 'field']), ['bat', 'bowl', 'field']));
    if (!$roles) fail('Tick at least one of batting, bowling or fielding');
    $inns = $innId ? [fix_innings_of($m, $innId)] : rows('SELECT * FROM innings WHERE match_id=? ORDER BY innings_no', [$mid]);
    $before = fix_score_line($mid);
    $snap = fix_snapshot($m, $u, '');
    $n = 0;
    foreach ($inns as $inn) {
        $id = (int)$inn['id'];
        $cols = [];
        if ((int)$inn['batting_team_id'] === $teamId && in_array('bat', $roles, true)) {
            if (val('SELECT 1 FROM balls WHERE innings_id=? AND ((batsman_id=? AND non_striker_id=?) OR (batsman_id=? AND non_striker_id=?)) LIMIT 1', [$id, $from, $to, $to, $from]))
                fail('Both players batted together in ' . team_name($teamId) . "'s innings - correct those balls one by one instead");
            $cols = ['batsman_id', 'non_striker_id', 'dismissed_id'];
            foreach (['striker_id', 'non_striker_id'] as $c) q("UPDATE innings SET $c=? WHERE id=? AND $c=?", [$to, $id, $from]);
            q('UPDATE innings_batters SET player_id=? WHERE innings_id=? AND player_id=? AND NOT EXISTS (SELECT 1 FROM (SELECT player_id FROM innings_batters WHERE innings_id=? AND player_id=?) x)', [$to, $id, $from, $id, $to]);
        }
        if ((int)$inn['bowling_team_id'] === $teamId) {
            if (in_array('bowl', $roles, true)) { $cols[] = 'bowler_id'; q('UPDATE innings SET bowler_id=? WHERE id=? AND bowler_id=?', [$to, $id, $from]); }
            if (in_array('field', $roles, true)) array_push($cols, 'fielder_id', 'drop_id', 'field_id', 'fielded_id');
        }
        foreach ($cols as $c) $n += q("UPDATE balls SET $c=? WHERE innings_id=? AND $c=?", [$to, $id, $from])->rowCount();
    }
    if (!$n) fail(fix_side_map($mid, $teamId)[$from] . ' does not appear in ' . ($innId ? 'that innings' : 'this match') . ' in that role');
    // the right player takes the wrong player's place on the match list when the wrong one no longer appears anywhere
    $listedTo = (bool)val('SELECT 1 FROM match_players WHERE match_id=? AND player_id=?', [$mid, $to]);
    $involved = fix_involved($mid);
    if (!$listedTo && empty($involved[$from]) && val('SELECT 1 FROM match_players WHERE match_id=? AND player_id=?', [$mid, $from]))
        q('UPDATE match_players SET player_id=? WHERE match_id=? AND player_id=?', [$to, $mid, $from]);
    else fix_ensure_listed($m, $teamId, [$to]);
    foreach ($inns as $inn) fix_rebuild_innings($m, (int)$inn['id']);
    fix_refresh_match($mid);
    $names = fix_side_map($mid, $teamId) + [$from => (string)val('SELECT name FROM players WHERE id=?', [$from])];
    $what = $names[$from] . ' replaced by ' . $names[$to] . ' (' . implode(', ', $roles) . ', ' . ($innId ? 'innings ' . $inns[0]['innings_no'] : 'whole match') . ", $n entries)";
    fix_finish($m, $u, $snap, $what, $before);
    return fix_match_data($mid);
}

/** Take a player who did not play off the match list */
function fix_xi_remove(array $m, ?array $u, array $in): array {
    $mid = (int)$m['id']; $pid = (int)($in['player_id'] ?? 0);
    $mp = row('SELECT mp.*, p.name FROM match_players mp JOIN players p ON p.id=mp.player_id WHERE mp.match_id=? AND mp.player_id=?', [$mid, $pid]);
    if (!$mp) fail('That player is not on the match list');
    if (!empty(fix_involved($mid)[$pid])) fail($mp['name'] . ' batted, bowled or fielded in this match - replace them on those balls first');
    if (val('SELECT 1 FROM innings_batters ib JOIN innings i ON i.id=ib.innings_id WHERE i.match_id=? AND ib.player_id=?', [$mid, $pid])) fail($mp['name'] . ' is in the batting order of this match');
    $min = $m['last_man_stands'] ? 1 : 2;
    if ((int)val('SELECT COUNT(*) FROM match_players WHERE match_id=? AND team_id=?', [$mid, $mp['team_id']]) <= $min) fail('A side needs at least ' . $min . ' player(s)');
    if ($m['status'] === 'live' && val('SELECT 1 FROM innings i WHERE i.match_id=? AND i.status=\'live\' AND (i.striker_id=? OR i.non_striker_id=? OR i.bowler_id=?)', [$mid, $pid, $pid, $pid])) fail($mp['name'] . ' is at the crease or bowling right now');
    if (fix_can_open($m)) {
        $before = fix_score_line($mid);
        $snap = fix_snapshot($m, $u, '');
        q('DELETE FROM match_players WHERE match_id=? AND player_id=?', [$mid, $pid]);
        fix_refresh_match($mid);
        fix_finish($m, $u, $snap, $mp['name'] . ' removed from the match list (did not play)', $before);
    } else {
        q('DELETE FROM match_players WHERE match_id=? AND player_id=?', [$mid, $pid]);
        match_log($m, 'Admin: ' . $mp['name'] . ' removed from the match list', $u);
    }
    return fix_match_data($mid);
}

/** v1.47: add a squad player to the match list - any time (before, during or after the match) */
function fix_xi_add_row(array $m, int $tid, int $pid): string {
    $mid = (int)$m['id'];
    $pl = row('SELECT id, name FROM players WHERE id=? AND team_id=?', [$pid, $tid]);
    if (!$pl) fail('That player is not in this team');
    if (val('SELECT 1 FROM match_players WHERE match_id=? AND player_id=?', [$mid, $pid])) fail($pl['name'] . ' is already on the match list');
    $ord = 1 + (int)val('SELECT COALESCE(MAX(bat_order),0) FROM match_players WHERE match_id=? AND team_id=?', [$mid, $tid]);
    q('INSERT INTO match_players (match_id, team_id, player_id, bat_order, is_captain, is_keeper) VALUES (?,?,?,?,0,0)', [$mid, $tid, $pid, $ord]);
    return $pl['name'];
}
function fix_xi_add(array $m, ?array $u, array $in): array {
    $mid = (int)$m['id']; $pid = (int)($in['player_id'] ?? 0);
    $tid = (int)val('SELECT team_id FROM players WHERE id=?', [$pid]);
    if (!in_array($tid, [(int)$m['team1_id'], (int)$m['team2_id']], true)) fail('That player is not in either team of this match');
    $open = fix_can_open($m); $snap = $open ? fix_snapshot($m, $u, '') : 0; $before = $open ? fix_score_line($mid) : '';
    $name = fix_xi_add_row($m, $tid, $pid);
    if ($open) { fix_refresh_match($mid); fix_finish($m, $u, $snap, $name . ' added to the match list', $before); }
    else match_log($m, 'Admin: ' . $name . ' added to the match list', $u);
    return fix_match_data($mid);
}
/** v1.47: new player in a team's squad (and optionally straight onto the match list) */
function fix_player_add(array $m, ?array $u, array $in): array {
    $mid = (int)$m['id']; $tid = (int)($in['team_id'] ?? 0);
    if (!in_array($tid, [(int)$m['team1_id'], (int)$m['team2_id']], true)) fail('Pick one of the two teams');
    $name = trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? '')));
    if ($name === '' || mb_strlen($name) > 100) fail('Enter the player name');
    if (val('SELECT 1 FROM players WHERE team_id=? AND LOWER(name)=LOWER(?)', [$tid, $name])) fail($name . ' is already in this team - add them to the match list instead');
    $role = in_array($in['role'] ?? '', ['Batter', 'Bowler', 'All-rounder', 'Wicket-keeper'], true) ? $in['role'] : 'Batter';
    q('INSERT INTO players (name, role, batting_style, team_id) VALUES (?,?,?,?)', [$name, $role, 'Right-hand bat', $tid]);
    $pid = lastId();
    if (!empty($in['add_to_match'])) {
        $open = fix_can_open($m); $snap = $open ? fix_snapshot($m, $u, '') : 0; $before = $open ? fix_score_line($mid) : '';
        fix_xi_add_row($m, $tid, $pid);
        if ($open) { fix_refresh_match($mid); fix_finish($m, $u, $snap, $name . ' added to the squad and the match list', $before); }
        else match_log($m, 'Admin: ' . $name . ' added to the squad and the match list', $u);
    } else match_log($m, 'Admin: ' . $name . ' added to the squad of ' . team_name($tid), $u);
    return fix_match_data($mid);
}
/** v1.47: correct a player's name / role (changes everywhere - it is the same player record) */
function fix_player_edit(array $m, ?array $u, array $in): array {
    $pid = (int)($in['player_id'] ?? 0);
    $pl = row('SELECT * FROM players WHERE id=?', [$pid]);
    if (!$pl || !in_array((int)$pl['team_id'], [(int)$m['team1_id'], (int)$m['team2_id']], true)) fail('That player is not in either team of this match');
    $name = trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? $pl['name'])));
    if ($name === '' || mb_strlen($name) > 100) fail('Enter the player name');
    if (val('SELECT 1 FROM players WHERE team_id=? AND id<>? AND LOWER(name)=LOWER(?)', [$pl['team_id'], $pid, $name])) fail('Another player in this team already has that name');
    $role = in_array($in['role'] ?? '', ['Batter', 'Bowler', 'All-rounder', 'Wicket-keeper'], true) ? $in['role'] : $pl['role'];
    q('UPDATE players SET name=?, role=? WHERE id=?', [$name, $role, $pid]);
    if ($name !== $pl['name']) q('UPDATE teams SET captain=? WHERE id=? AND captain=?', [$name, $pl['team_id'], $pl['name']]);
    match_log($m, 'Admin: player ' . $pl['name'] . ($name !== $pl['name'] ? ' renamed to ' . $name : ' updated'), $u);
    return fix_match_data((int)$m['id']);
}

function fix_undo(array $m, ?array $u, array $in): array {
    $mid = (int)$m['id'];
    $snapId = (int)($in['snapshot_id'] ?? 0);
    if ($snapId) {                                   // go back to a chosen saved copy - and keep today's state so this can be undone too
        $target = row('SELECT id, note, created_at FROM fix_snapshots WHERE id=? AND match_id=?', [$snapId, $mid]);
        if (!$target) fail('That saved copy was not found');
        $isFirst = $snapId === (int)val('SELECT MIN(id) FROM fix_snapshots WHERE match_id=?', [$mid]);
        $before = fix_score_line($mid);
        $snap = fix_snapshot($m, $u, '');
        fix_restore_snapshot($m, $snapId);
        fix_finish($m, $u, $snap, $isFirst ? 'restored the match as it was originally scored' : 'went back to before "' . ($target['note'] ?: 'a change') . '"', $before);
        return fix_match_data($mid);
    }
    $last = row('SELECT id, note FROM fix_snapshots WHERE match_id=? ORDER BY id DESC LIMIT 1', [$mid]);
    if (!$last) fail('Nothing to undo - this match has not been corrected');
    fix_restore_snapshot($m, (int)$last['id']);
    q('DELETE FROM fix_snapshots WHERE id=?', [$last['id']]);
    match_log($m, 'Admin correction undone: ' . ($last['note'] ?: 'last change'), $u);
    return fix_match_data($mid);
}

/** admin_fix_* actions (api.php has already checked that this is the administrator) */
function fix_action(string $action, ?array $u, array $in) {
    if ($action === 'admin_fix_matches') return fix_matches($in);
    if ($action === 'admin_fix_match') return fix_match_data((int)($in['id'] ?? 0));
    db()->beginTransaction();
    $m = load_match((int)($in['id'] ?? 0), true);
    switch ($action) {
        case 'admin_fix_apply':   $out = fix_apply($m, $u, $in); break;
        case 'admin_fix_replace': $out = fix_replace_player($m, $u, $in); break;
        case 'admin_fix_xi_remove': $out = fix_xi_remove($m, $u, $in); break;
        case 'admin_fix_xi_add':    $out = fix_xi_add($m, $u, $in); break;
        case 'admin_fix_player_add': $out = fix_player_add($m, $u, $in); break;
        case 'admin_fix_player_edit': $out = fix_player_edit($m, $u, $in); break;
        case 'admin_fix_undo':    $out = fix_undo($m, $u, $in); break;
        default: fail('Unknown action', 404);
    }
    db()->commit();
    return $out;
}

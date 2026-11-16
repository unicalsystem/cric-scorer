<?php
/* RV Badminton - players, matches and tournaments (league groups, knockout draws, league + play-offs) */

/** Find a club player by name, or add them */
function bd_player(string $name, ?int $by = null): int {
    $name = mb_substr(trim(preg_replace('/\s+/', ' ', $name)), 0, 80);
    if ($name === '') fail('Every player needs a name');
    $id = val('SELECT id FROM bd_players WHERE name=?', [$name]);
    if ($id) return (int)$id;
    q('INSERT INTO bd_players (name, created_by) VALUES (?,?)', [$name, $by]);
    return lastId();
}
/** player id → team id, for everyone who is in a team (one small query per request) */
function bd_team_map(bool $fresh = false): array {
    static $map = null;
    if ($map === null || $fresh) { $map = []; foreach (rows('SELECT id, team_id FROM bd_players WHERE team_id IS NOT NULL') as $r) $map[(int)$r['id']] = (int)$r['team_id']; }
    return $map;
}
/** The team a side plays for: every player on the side is in the same team */
function bd_side_team($p1, $p2): ?int {
    $map = bd_team_map();
    $a = $map[(int)$p1] ?? null;
    if (!$p2) return $a;
    return $a !== null && $a === ($map[(int)$p2] ?? null) ? $a : null;
}
function bd_teams(): array {
    return array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name'], 'logo' => $t['logo'], 'color' => $t['color']], rows('SELECT * FROM bd_teams ORDER BY name'));
}
/** Put players into a team; with $onlyEmpty, players who already have a team keep it */
function bd_assign_team(array $playerIds, ?int $teamId, bool $onlyEmpty = false): void {
    if ($teamId !== null && !val('SELECT 1 FROM bd_teams WHERE id=?', [$teamId])) fail('That team was not found');
    foreach (array_filter($playerIds) as $pid) q('UPDATE bd_players SET team_id=? WHERE id=?' . ($onlyEmpty ? ' AND team_id IS NULL' : ''), [$teamId, $pid]);
    bd_team_map(true);
}

/** "Asha / Meera" → [id, id]; "Asha" → [id, null] */
function bd_side(string $text, bool $doubles, ?int $by): array {
    $parts = array_values(array_filter(array_map('trim', preg_split('#\s*(?:/|&|\+|,| and )\s*#i', $text)), 'strlen'));
    if ($doubles) {
        if (count($parts) !== 2) fail("Doubles needs two names for each pair, written like \"Asha / Meera\" - check \"$text\"");
        $a = bd_player($parts[0], $by); $b = bd_player($parts[1], $by);
        if ($a === $b) fail("A pair needs two different players - check \"$text\"");
        return [$a, $b];
    }
    if (count($parts) !== 1) fail("Singles takes one name per side - check \"$text\"");
    return [bd_player($parts[0], $by), null];
}
function bd_side_name(int $p1, ?int $p2): string {
    $a = (string)val('SELECT name FROM bd_players WHERE id=?', [$p1]);
    return $p2 ? $a . ' / ' . val('SELECT name FROM bd_players WHERE id=?', [$p2]) : $a;
}

function bd_can_score(array $m, ?array $u): bool {
    if (!$u) return false;
    if (!empty($u['bd_admin']) || (int)$m['owner_id'] === (int)$u['id'] || (int)($m['scorer_id'] ?? 0) === (int)$u['id']) return true;
    return $m['tour_id'] && (int)val('SELECT owner_id FROM bd_tours WHERE id=?', [$m['tour_id']]) === (int)$u['id'];
}
function bd_round_name(int $round, int $rounds): string {
    $left = $rounds - $round;
    return $left === 0 ? 'Final' : ($left === 1 ? 'Semi-final' : ($left === 2 ? 'Quarter-final' : 'Round of ' . (2 ** ($left + 1))));
}
function bd_opts(array $t): array {
    $o = json_decode((string)($t['opts'] ?? ''), true) ?: [];
    return ['final_rounds' => (int)($o['final_rounds'] ?? 0), 'qualify' => (int)($o['qualify'] ?? 4), 'win_pts' => (int)($o['win_pts'] ?? 2), 'loss_pts' => (int)($o['loss_pts'] ?? 0)];
}
/** Name of a match's round, as shown on the match screen and in lists */
function bd_match_round(array $m, array $t): string {
    if ((int)$m['stage'] === 1) return bd_round_name((int)$m['round'], (int)val('SELECT MAX(round) FROM bd_matches WHERE tour_id=? AND stage=1', [$m['tour_id']]));
    return (int)$t['grp_count'] > 1 ? 'Group ' . chr(65 + (int)$m['grp']) : 'League';
}
/** A match as the phone sees it */
function bd_match_out(array $m, ?array $u, bool $full = true): array {
    $o = ['id' => (int)$m['id'], 'a' => $m['a_name'], 'b' => $m['b_name'], 'doubles' => (bool)$m['doubles'], 'status' => $m['status'],
        'winner' => $m['winner'] === null ? null : (int)$m['winner'], 'summary' => $m['summary'], 'court' => $m['court'],
        'tour_id' => $m['tour_id'] ? (int)$m['tour_id'] : null, 'stage' => (int)$m['stage'], 'round' => $m['round'] === null ? null : (int)$m['round'], 'grp' => $m['grp'] === null ? null : (int)$m['grp'],
        'sched_at' => $m['sched_at'], 'started_at' => $m['started_at'], 'finished_at' => $m['finished_at'], 'rev' => $m['updated_at'],
        'players' => [[$m['a1'] ? (int)$m['a1'] : null, $m['a2'] ? (int)$m['a2'] : null], [$m['b1'] ? (int)$m['b1'] : null, $m['b2'] ? (int)$m['b2'] : null]],
        'teams' => [$m['a1'] ? bd_side_team($m['a1'], $m['a2']) : null, $m['b1'] ? bd_side_team($m['b1'], $m['b2']) : null]];
    $st = $m['state'] ? json_decode($m['state'], true) : null;
    if (!$full) {   // list rows carry just the score line
        if ($st) $o['mini'] = ['games' => $st['games'], 'pts' => $st['pts'], 'server' => $st['server'][0] ?? null];
        if (isset($m['tour_name'])) $o['tour'] = $m['tour_name'];
        return $o;
    }
    $f = bd_format($m['format']);
    $evs = json_decode((string)$m['events'], true) ?: [];
    $o['state'] = $st ?: bd_replay($f, (bool)$m['doubles'], (int)$m['first_srv'], $evs)->state();
    $o['ev'] = count($evs);                                          // the phone sends this back with every change (see "point")
    $last = $evs ? end($evs) : null;
    $o['last_k'] = $last && isset($last['w']) && !isset($last['t']) ? (string)($last['k'] ?? '') : null;   // null = the last event is not a rally
    $o['format'] = $f;
    $o['format_label'] = bd_format_label($f);
    $o['first_srv'] = (int)$m['first_srv'];
    $o['can_score'] = bd_can_score($m, $u);
    $o['scorer'] = $m['scorer_id'] ? val('SELECT display_name FROM users WHERE id=?', [$m['scorer_id']]) : null;
    $o['owner'] = val('SELECT display_name FROM users WHERE id=?', [$m['owner_id']]);
    if ($m['tour_id']) {
        $t = row('SELECT * FROM bd_tours WHERE id=?', [$m['tour_id']]);
        $o['tour'] = $t['name'] ?? null;
        if ($t) $o['round_name'] = bd_match_round($m, $t);
    }
    $names = [];
    foreach (rows('SELECT id, name FROM bd_players WHERE id IN (?,?,?,?)', [(int)$m['a1'], (int)$m['a2'], (int)$m['b1'], (int)$m['b2']]) as $p) $names[(int)$p['id']] = $p['name'];
    $o['names'] = [[$names[(int)$m['a1']] ?? $m['a_name'], $names[(int)$m['a2']] ?? null], [$names[(int)$m['b1']] ?? $m['b_name'], $names[(int)$m['b2']] ?? null]];
    return $o;
}

/** Save the events of a match: recompute the score, status and (in a knockout) who moves on */
function bd_store(array $m, array $events, ?array $manual = null): array {
    $f = bd_format($m['format']);
    $st = $manual ?: bd_replay($f, (bool)$m['doubles'], (int)$m['first_srv'], $events)->state();
    $wasDone = $m['status'] === 'done';
    $status = $st['over'] ? 'done' : (($st['acts'] ?? 0) > 0 ? 'live' : 'scheduled');
    if ($wasDone && ($status !== 'done' || (int)$st['winner'] !== (int)$m['winner'])) bd_ko_retract($m);
    // Times are worked out here and sent as plain values, so the statement is the same on every MySQL / MariaDB version
    $now = date('Y-m-d H:i:s');
    $started = $status === 'scheduled' ? null : ($m['started_at'] ?: $now);
    $finished = $status === 'done' ? ($m['finished_at'] ?: $now) : null;
    q('UPDATE bd_matches SET events=?, state=?, status=?, winner=?, summary=?, started_at=?, finished_at=? WHERE id=?',
      [json_encode($events), json_encode($st), $status, $st['over'] ? (int)$st['winner'] : null, bd_summary($st['games'], $st['outcome']) ?: null, $started, $finished, $m['id']]);
    $m = row('SELECT * FROM bd_matches WHERE id=?', [$m['id']]);
    if ($status === 'done') {
        bd_ko_advance($m);
        // the last league match of a league + play-offs tournament draws the play-offs
        if ($m['tour_id'] && (int)$m['stage'] === 0 && val('SELECT kind FROM bd_tours WHERE id=?', [$m['tour_id']]) === 'lk'
            && !val("SELECT 1 FROM bd_matches WHERE tour_id=? AND stage=0 AND status IN ('scheduled','live') LIMIT 1", [$m['tour_id']])
            && !val('SELECT 1 FROM bd_matches WHERE tour_id=? AND stage=1 LIMIT 1', [$m['tour_id']])) {
            try { bd_playoffs((int)$m['tour_id']); } catch (ApiError $e) { /* an admin can draw them from the tournament page */ }
        }
    }
    return $m;
}
function bd_ko_next(array $m): ?array {
    if (!$m['tour_id'] || (int)$m['stage'] !== 1) return null;
    return row('SELECT * FROM bd_matches WHERE tour_id=? AND stage=1 AND round=? AND slot=?', [$m['tour_id'], (int)$m['round'] + 1, intdiv((int)$m['slot'], 2)]);
}
/** The winner takes their place in the next round */
function bd_ko_advance(array $m): void {
    $next = bd_ko_next($m);
    if (!$next || $m['winner'] === null) return;
    $w = (int)$m['winner'] === 0 ? 'a' : 'b'; $side = (int)$m['slot'] % 2 === 0 ? 'a' : 'b';
    q("UPDATE bd_matches SET entry_$side=?, {$side}1=?, {$side}2=?, {$side}_name=? WHERE id=?", [$m["entry_$w"], $m["{$w}1"], $m["{$w}2"], $m["{$w}_name"], $next['id']]);
}
/** A finished knockout match is reopened: take its winner back out of the next round (unless that has started) */
function bd_ko_retract(array $m): void {
    $next = bd_ko_next($m);
    if (!$next) return;
    if ($next['status'] !== 'scheduled') fail('The next round of this draw has already started, so this result can no longer be changed here.');
    $side = (int)$m['slot'] % 2 === 0 ? 'a' : 'b';
    q("UPDATE bd_matches SET entry_$side=NULL, {$side}1=NULL, {$side}2=NULL, {$side}_name='' WHERE id=?", [$next['id']]);
}

/** Where each seed sits in a draw of $size: [1, 8, 4, 5, 2, 7, 3, 6] for 8 */
function bd_seed_order(int $size): array {
    $o = [1];
    while (count($o) < $size) { $n = count($o) * 2; $x = []; foreach ($o as $s) { $x[] = $s; $x[] = $n + 1 - $s; } $o = $x; }
    return $o;
}
/** Insert a knockout draw for these entries (best seed first). The last $finalRounds rounds use $ff. */
function bd_draw(int $tid, array $u, bool $doubles, array $entries, array $f, array $ff, int $finalRounds): void {
    $size = 2; while ($size < count($entries)) $size *= 2;
    $order = bd_seed_order($size); $rounds = (int)round(log($size, 2));
    for ($r = 1; $r <= $rounds; $r++) for ($s = 0; $s < $size / (2 ** $r); $s++) {
        $fmt = $rounds - $r < $finalRounds ? $ff : $f;
        $a = $b = null; $status = 'scheduled'; $winner = null;
        if ($r === 1) {
            $a = $entries[$order[2 * $s] - 1] ?? null; $b = $entries[$order[2 * $s + 1] - 1] ?? null;
            if (!$a || !$b) { $status = 'bye'; $winner = $a ? 0 : 1; }
        }
        q('INSERT INTO bd_matches (owner_id, tour_id, stage, round, slot, grp, entry_a, entry_b, doubles, a1, a2, b1, b2, a_name, b_name, format, status, winner) VALUES (?,?,1,?,?,NULL,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$u['id'], $tid, $r, $s, $a['id'] ?? null, $b['id'] ?? null, $doubles ? 1 : 0, $a['p1'] ?? null, $a['p2'] ?? null, $b['p1'] ?? null, $b['p2'] ?? null,
           $a['name'] ?? '', $b['name'] ?? '', json_encode($fmt), $status, $winner]);
    }
    foreach (rows("SELECT * FROM bd_matches WHERE tour_id=? AND stage=1 AND status='bye'", [$tid]) as $m) bd_ko_advance($m);
}
function bd_tour_create(array $in, array $u): int {
    $name = str_in($in, 'name', 120);
    if ($name === '') fail('Give the tournament a name');
    $kind = in_array($in['kind'] ?? '', ['rr', 'ko', 'lk'], true) ? $in['kind'] : 'lk';
    $doubles = !empty($in['doubles']);
    $f = bd_format($in['format'] ?? 'bo1');
    $ff = bd_format($in['ko_format'] ?? 'bo3');
    $opts = ['final_rounds' => $kind === 'ko' ? max(0, min(3, int_in($in, 'final_rounds', 2))) : 0,
             'qualify' => $kind === 'lk' ? (in_array(int_in($in, 'qualify', 4), [2, 4, 8], true) ? int_in($in, 'qualify', 4) : 4) : 0,
             'win_pts' => max(0, min(10, int_in($in, 'win_pts', 2))), 'loss_pts' => max(0, min(10, int_in($in, 'loss_pts', 0)))];
    $lines = is_array($in['entries'] ?? null) ? $in['entries'] : preg_split('/\R/', (string)($in['entries'] ?? ''));
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
    if (count($lines) < 2) fail('Add at least two ' . ($doubles ? 'pairs' : 'players') . ', one on each line');
    if (count($lines) > 64) fail('A tournament can have up to 64 entries');
    $groups = $kind === 'ko' ? 1 : max(1, min(8, int_in($in, 'groups', 1)));
    if ($kind !== 'ko' && count($lines) < $groups * 2) fail("With $groups groups you need at least " . ($groups * 2) . ' entries');
    if ($kind === 'lk') {
        if ($opts['qualify'] % $groups) fail("{$opts['qualify']} play-off places cannot be shared equally between $groups groups");
        if (count($lines) < $opts['qualify'] || intdiv(count($lines), $groups) < $opts['qualify'] / $groups) fail("Each group needs at least " . ($opts['qualify'] / $groups) . ' entries for ' . $opts['qualify'] . ' play-off places');
    }
    db()->beginTransaction();
    try {
        q('INSERT INTO bd_tours (name, kind, doubles, format, ko_format, opts, grp_count, owner_id) VALUES (?,?,?,?,?,?,?,?)',
          [$name, $kind, $doubles ? 1 : 0, json_encode($f), json_encode($ff), json_encode($opts), $groups, $u['id']]);
        $tid = lastId();
        $entries = []; $seen = [];
        foreach ($lines as $i => $line) {
            [$p1, $p2] = bd_side($line, $doubles, (int)$u['id']);
            foreach (array_filter([$p1, $p2]) as $p) { if (isset($seen[$p])) fail('"' . val('SELECT name FROM bd_players WHERE id=?', [$p]) . '" is entered twice'); $seen[$p] = 1; }
            $g = $kind !== 'ko' && $groups > 1 ? (intdiv($i, $groups) % 2 === 0 ? $i % $groups : $groups - 1 - $i % $groups) : 0;   // snake seeding across groups
            $nm = bd_side_name($p1, $p2);
            q('INSERT INTO bd_entries (tour_id, pos, grp, p1, p2, name) VALUES (?,?,?,?,?,?)', [$tid, $i + 1, $g, $p1, $p2, $nm]);
            $entries[] = ['id' => lastId(), 'grp' => $g, 'p1' => $p1, 'p2' => $p2, 'name' => $nm];
        }
        if ($kind === 'ko') bd_draw($tid, $u, $doubles, $entries, $f, $ff, $opts['final_rounds']);
        else {
            for ($g = 0; $g < $groups; $g++) {
                $list = array_values(array_filter($entries, fn($e) => $e['grp'] === $g));
                if (count($list) % 2) $list[] = null;                         // circle method: everyone plays once per round
                $n = count($list); $slot = 0;
                for ($r = 0; $r < $n - 1; $r++) {
                    for ($i = 0; $i < $n / 2; $i++) {
                        $a = $list[$i]; $b = $list[$n - 1 - $i];
                        if ($a && $b) q('INSERT INTO bd_matches (owner_id, tour_id, stage, round, slot, grp, entry_a, entry_b, doubles, a1, a2, b1, b2, a_name, b_name, format) VALUES (?,?,0,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                            [$u['id'], $tid, $r + 1, $slot++, $g, $a['id'], $b['id'], $doubles ? 1 : 0, $a['p1'], $a['p2'], $b['p1'], $b['p2'], $a['name'], $b['name'], json_encode($f)]);
                    }
                    array_splice($list, 1, 0, [array_pop($list)]);
                }
            }
        }
        db()->commit();
        return $tid;
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}

/** League + play-offs: draw the play-offs from the league tables. Group winners are seeded first, then runners-up and so
    on, so with two groups the semi-finals cross over (A1 v B2, B1 v A2). Re-drawing is allowed until a play-off match starts. */
function bd_playoffs(int $tid, ?array $u = null): void {
    $t = row('SELECT * FROM bd_tours WHERE id=?', [$tid]);
    if (!$t || $t['kind'] !== 'lk') fail('Play-offs are only for a league + play-offs tournament');
    if (val("SELECT 1 FROM bd_matches WHERE tour_id=? AND stage=1 AND status IN ('live','done') LIMIT 1", [$tid])) fail('The play-offs have already started, so they cannot be drawn again.');
    $o = bd_opts($t); $groups = (int)$t['grp_count']; $per = intdiv($o['qualify'], $groups);
    $byGroup = [];
    foreach (bd_standings($tid) as $r) $byGroup[$r['grp']][] = $r;
    $ents = [];
    foreach (rows('SELECT * FROM bd_entries WHERE tour_id=?', [$tid]) as $e) $ents[(int)$e['id']] = ['id' => (int)$e['id'], 'grp' => (int)$e['grp'], 'p1' => (int)$e['p1'], 'p2' => $e['p2'] ? (int)$e['p2'] : null, 'name' => $e['name']];
    $seeds = [];
    for ($rank = 0; $rank < $per; $rank++) for ($g = 0; $g < $groups; $g++) if (isset($byGroup[$g][$rank])) $seeds[] = $ents[$byGroup[$g][$rank]['id']];
    if (count($seeds) < 2) fail('Not enough entries for the play-offs');
    q('DELETE FROM bd_matches WHERE tour_id=? AND stage=1', [$tid]);
    bd_draw($tid, $u ?: ['id' => (int)$t['owner_id']], (bool)$t['doubles'], $seeds, bd_format($t['ko_format'] ?: 'bo3'), bd_format($t['ko_format'] ?: 'bo3'), 9);
}

/** League tables (rule sheet section 6): match points (2 a win, 0 a loss - set per tournament), then net points difference
    (rally points scored minus conceded in all league games), then head-to-head between the sides still level. */
function bd_standings(int $tid): array {
    $t = row('SELECT * FROM bd_tours WHERE id=?', [$tid]); $o = bd_opts($t ?: []);
    $tab = [];
    foreach (rows('SELECT * FROM bd_entries WHERE tour_id=? ORDER BY pos', [$tid]) as $e)
        $tab[(int)$e['id']] = ['id' => (int)$e['id'], 'name' => $e['name'], 'grp' => (int)$e['grp'], 'p' => 0, 'w' => 0, 'l' => 0, 'mp' => 0, 'gf' => 0, 'ga' => 0, 'pf' => 0, 'pa' => 0, 'pd' => 0, 'pos' => (int)$e['pos']];
    $beat = [];   // [winner entry][loser entry] = wins
    foreach (rows("SELECT entry_a, entry_b, winner, state FROM bd_matches WHERE tour_id=? AND stage=0 AND status='done'", [$tid]) as $m) {
        $a = (int)$m['entry_a']; $b = (int)$m['entry_b'];
        if (!isset($tab[$a], $tab[$b])) continue;
        $wi = (int)$m['winner'] === 0 ? $a : $b; $lo = $wi === $a ? $b : $a;
        $tab[$a]['p']++; $tab[$b]['p']++;
        $tab[$wi]['w']++; $tab[$lo]['l']++;
        $tab[$wi]['mp'] += $o['win_pts']; $tab[$lo]['mp'] += $o['loss_pts'];
        $beat[$wi][$lo] = ($beat[$wi][$lo] ?? 0) + 1;
        foreach ((json_decode((string)$m['state'], true) ?: [])['games'] ?? [] as $g) {
            $tab[$a]['pf'] += $g['a']; $tab[$a]['pa'] += $g['b']; $tab[$b]['pf'] += $g['b']; $tab[$b]['pa'] += $g['a'];
            $x = $g['a'] > $g['b'] ? $a : $b; $tab[$x]['gf']++; $tab[$x === $a ? $b : $a]['ga']++;
        }
    }
    foreach ($tab as &$r) $r['pd'] = $r['pf'] - $r['pa'];
    unset($r);
    $tab = array_values($tab);
    usort($tab, fn($x, $y) => [$x['grp'], $y['mp'], $y['pd']] <=> [$y['grp'], $x['mp'], $x['pd']]);
    // head-to-head inside each block still level on match points and points difference
    $out = []; $i = 0; $n = count($tab);
    while ($i < $n) {
        $j = $i; while ($j + 1 < $n && $tab[$j + 1]['grp'] === $tab[$i]['grp'] && $tab[$j + 1]['mp'] === $tab[$i]['mp'] && $tab[$j + 1]['pd'] === $tab[$i]['pd']) $j++;
        $block = array_slice($tab, $i, $j - $i + 1);
        if (count($block) > 1) {
            $ids = array_column($block, 'id');
            foreach ($block as &$r) { $r['h2h'] = 0; foreach ($ids as $other) $r['h2h'] += $beat[$r['id']][$other] ?? 0; }
            unset($r);
            usort($block, fn($x, $y) => [$y['h2h'], $x['pos']] <=> [$x['h2h'], $y['pos']]);
        }
        foreach ($block as $r) { unset($r['h2h']); $out[] = $r; }
        $i = $j + 1;
    }
    return $out;
}

<?php
/* Cric-Scorer - "pro" scoring: penalty runs, powerplay, shot & fielding details, editing earlier balls,
   changing a wrongly chosen batter or the overs, super overs, best performers and player insights */

const SUPER_OVER_WICKETS = 2;
const WAGON_ZONES = [1 => 'Third man', 2 => 'Point', 3 => 'Cover', 4 => 'Long off', 5 => 'Long on', 6 => 'Mid-wicket', 7 => 'Square leg', 8 => 'Fine leg'];

/** v1.16 schema: ball details, penalties, powerplay, best performers, officials, stream, super over */
function schema_v116(): void {
    if (!val("SHOW COLUMNS FROM balls LIKE 'shot_zone'")) {
        db()->exec("ALTER TABLE balls ADD COLUMN shot_type VARCHAR(20) NULL AFTER fielder_id, ADD COLUMN shot_zone TINYINT NULL AFTER shot_type,
                    ADD COLUMN drop_id INT NULL AFTER shot_zone, ADD COLUMN field_id INT NULL AFTER drop_id, ADD COLUMN field_runs INT NOT NULL DEFAULT 0 AFTER field_id,
                    MODIFY COLUMN wicket_type VARCHAR(30) NULL");
    }
    if (!val("SHOW COLUMNS FROM balls WHERE Field='wicket_type' AND Type LIKE '%30%'")) db()->exec("ALTER TABLE balls MODIFY COLUMN wicket_type VARCHAR(30) NULL");
    if (!val("SHOW COLUMNS FROM balls LIKE 'fielded_id'")) db()->exec("ALTER TABLE balls ADD COLUMN fielded_id INT NULL AFTER field_runs");
    if (!val("SHOW COLUMNS FROM innings LIKE 'field_json'")) db()->exec("ALTER TABLE innings ADD COLUMN field_json TEXT NULL AFTER bowler_id");
    if (val("SHOW TABLES LIKE 'penalties'") && !val("SHOW COLUMNS FROM penalties LIKE 'innings_id'")) db()->exec("ALTER TABLE penalties ADD COLUMN innings_id INT NULL AFTER team_id");
    if (!val("SHOW COLUMNS FROM innings LIKE 'is_super'")) { db()->exec("ALTER TABLE innings ADD COLUMN is_super TINYINT(1) NOT NULL DEFAULT 0 AFTER dls, ADD COLUMN follow_on TINYINT(1) NOT NULL DEFAULT 0 AFTER is_super, ADD COLUMN declared TINYINT(1) NOT NULL DEFAULT 0 AFTER follow_on"); db()->exec("UPDATE innings i JOIN matches m ON m.id=i.match_id SET i.is_super=1 WHERE i.innings_no>=3 AND m.super_over=1"); }
    if (!val("SHOW COLUMNS FROM matches LIKE 'format'")) db()->exec("ALTER TABLE matches ADD COLUMN format VARCHAR(10) NOT NULL DEFAULT 'limited' AFTER overs, ADD COLUMN days TINYINT NOT NULL DEFAULT 0 AFTER format, ADD COLUMN follow_on INT NOT NULL DEFAULT 200 AFTER days");
    if (!val("SHOW COLUMNS FROM matches LIKE 'powerplay_overs'")) {
        db()->exec("ALTER TABLE matches ADD COLUMN powerplay_overs TINYINT NOT NULL DEFAULT 0 AFTER max_overs_bowler, ADD COLUMN super_over TINYINT(1) NOT NULL DEFAULT 0 AFTER powerplay_overs,
                    ADD COLUMN best_bat_id INT NULL AFTER player_of_match_id, ADD COLUMN best_bowl_id INT NULL AFTER best_bat_id,
                    ADD COLUMN officials TEXT NULL AFTER best_bowl_id, ADD COLUMN stream_url VARCHAR(255) NULL AFTER officials");
    }
    if (!val("SHOW TABLES LIKE 'penalties'")) {
        db()->exec("CREATE TABLE IF NOT EXISTS penalties (id INT AUTO_INCREMENT PRIMARY KEY, match_id INT NOT NULL, team_id INT NOT NULL, innings_id INT NULL, runs INT NOT NULL, reason VARCHAR(80) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (match_id), FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

/** Penalty / bonus runs that count for the side batting in this innings (super overs get none) */
function penalty_runs_for_innings(int $innId): int {
    $inn = row('SELECT id, match_id, batting_team_id, innings_no, is_super FROM innings WHERE id=?', [$innId]);
    if (!$inn || !empty($inn['is_super'])) return 0;
    $first = (int)val('SELECT MIN(id) FROM innings WHERE match_id=? AND batting_team_id=? AND is_super=0', [$inn['match_id'], $inn['batting_team_id']]);
    // a penalty is tied to the innings it was given in; older rows (no innings) count in the side's first innings
    return (int)val('SELECT COALESCE(SUM(runs),0) FROM penalties WHERE match_id=? AND team_id=? AND (innings_id=? OR (innings_id IS NULL AND ?=?))', [$inn['match_id'], $inn['batting_team_id'], $innId, $first, $innId]);
}

/** Optional per-ball details sent with a ball (or when tagging a ball later) */
function ball_details_in(array $m, array $inn, array $in): array {
    $d = ['shot_type' => null, 'shot_zone' => null, 'drop_id' => null, 'field_id' => null, 'field_runs' => 0, 'fielded_id' => null];
    $st = trim((string)($in['shot_type'] ?? ''));
    if ($st !== '') { if (!in_array($st, SHOT_TYPES, true)) fail('Unknown shot type'); $d['shot_type'] = $st; }
    $z = (int)($in['shot_zone'] ?? 0);
    if ($z) { if ($z < 1 || $z > 8) fail('Shot zone must be 1-8'); $d['shot_zone'] = $z; }
    $fieldXi = xi_ids((int)$m['id'], (int)$inn['bowling_team_id']);
    $drop = nint($in['drop_id'] ?? null);
    if ($drop) { if (!in_array($drop, $fieldXi, true)) fail('The fielder who dropped it must be on the fielding side'); $d['drop_id'] = $drop; }
    $f = nint($in['field_id'] ?? null);
    $fr = (int)($in['field_runs'] ?? 0);
    if ($f && $fr !== 0) {
        if (!in_array($f, $fieldXi, true)) fail('The fielder must be on the fielding side');
        $d['field_id'] = $f; $d['field_runs'] = max(-6, min(6, $fr));
    }
    // who fielded it: given explicitly, or the fielder set for that zone
    $fd = nint($in['fielded_id'] ?? null);
    if ($fd && in_array($fd, $fieldXi, true)) $d['fielded_id'] = $fd;
    elseif ($d['shot_zone'] && !empty($inn['field_json'])) {
        $pos = json_decode($inn['field_json'], true) ?: [];
        $who = (int)($pos[(string)$d['shot_zone']] ?? 0);
        if ($who && in_array($who, $fieldXi, true)) $d['fielded_id'] = $who;
    }
    return $d;
}

/** Field positions of the bowling side for this innings: zone (1-8) => player */
function field_positions(array $inn, array $names): array {
    $pos = !empty($inn['field_json']) ? (json_decode($inn['field_json'], true) ?: []) : [];
    $out = [];
    foreach ($pos as $z => $pid) if ((int)$pid) $out[(string)(int)$z] = ['id' => (int)$pid, 'name' => $names[(int)$pid] ?? '?'];
    return $out;
}

/** Scorer sets where the fielders stand (zone => player id); used for commentary */
function match_field_set(array $m, array $in): void {
    $inn = latest_innings((int)$m['id']);
    if (!$inn || $inn['status'] !== 'live') fail('No innings in progress');
    $xi = xi_ids((int)$m['id'], (int)$inn['bowling_team_id']);
    $pos = [];
    foreach ((array)($in['positions'] ?? []) as $z => $pid) {
        $z = (int)$z; $pid = (int)$pid;
        if ($z < 1 || $z > 8 || !$pid) continue;
        if (!in_array($pid, $xi, true)) fail('Fielders must be from the fielding side');
        $pos[(string)$z] = $pid;
    }
    q('UPDATE innings SET field_json=? WHERE id=?', [$pos ? json_encode($pos) : null, $inn['id']]);
}

function partnership_row(array $players, array $partBat, int $runs, int $balls, array $names, int $wkt, int $score, bool $unbroken = false): array {
    $ps = [];
    foreach (array_keys($players) as $pid) $ps[] = ['id' => (int)$pid, 'name' => $names[(int)$pid] ?? '?', 'r' => $partBat[$pid]['r'] ?? 0, 'b' => $partBat[$pid]['b'] ?? 0];
    return ['wkt' => $wkt, 'runs' => $runs, 'balls' => $balls, 'players' => $ps, 'score' => $score, 'unbroken' => $unbroken];
}

/** Best batter (most runs, fewer balls) and best bowler (most wickets, fewer runs) of a match - super overs excluded */
function best_performers(int $matchId): array {
    $bat = []; $bowl = [];
    foreach (rows('SELECT b.* FROM balls b JOIN innings i ON i.id=b.innings_id WHERE i.match_id=? AND i.is_super=0', [$matchId]) as $b) {
        if (!in_array($b['wicket_type'], NON_DELIVERY, true)) {
            $p = (int)$b['batsman_id'];
            $bat[$p] = ['r' => ($bat[$p]['r'] ?? 0) + (int)$b['runs_bat'], 'b' => ($bat[$p]['b'] ?? 0) + ($b['extra_type'] === 'wd' ? 0 : 1)];
            $w = (int)$b['bowler_id'];
            $bowl[$w] = ['w' => ($bowl[$w]['w'] ?? 0) + (($b['is_wicket'] && in_array($b['wicket_type'], BOWLER_WICKETS, true)) ? 1 : 0),
                         'r' => ($bowl[$w]['r'] ?? 0) + (int)$b['runs_bat'] + (in_array($b['extra_type'], ['wd', 'nb'], true) ? (int)$b['extra_runs'] : 0)];
        }
    }
    uasort($bat, fn($a, $b) => [$b['r'], $a['b']] <=> [$a['r'], $b['b']]);
    uasort($bowl, fn($a, $b) => [$b['w'], $a['r']] <=> [$a['w'], $b['r']]);
    $bb = $bat && reset($bat)['r'] > 0 ? (int)array_key_first($bat) : null;
    $bw = $bowl && reset($bowl)['w'] > 0 ? (int)array_key_first($bowl) : null;
    return [$bb, $bw];
}

/* ---------- Scorer actions ---------- */

/** Award penalty (or bonus) runs to a side */
function match_penalty(array $m, array $in): void {
    $team = (int)($in['team_id'] ?? 0);
    if (!in_array($team, [(int)$m['team1_id'], (int)$m['team2_id']], true)) fail('Pick the side that gets the runs');
    $runs = (int)($in['runs'] ?? 5);
    if ($runs < 1 || $runs > 50) fail('Penalty runs must be between 1 and 50');
    if (latest_innings((int)$m['id']) && !empty(latest_innings((int)$m['id'])['is_super'])) fail('No penalty runs in a super over');
    $innId = nint(val('SELECT MAX(id) FROM innings WHERE match_id=? AND batting_team_id=? AND is_super=0', [$m['id'], $team]));   // the side's latest innings (NULL = not batted yet: counts in its first)
    q('INSERT INTO penalties (match_id, team_id, runs, reason, innings_id) VALUES (?,?,?,?,?)', [$m['id'], $team, $runs, mb_substr(trim((string)($in['reason'] ?? '')), 0, 80) ?: null, $innId]);
    penalty_recalc($m);
}
function match_penalty_undo(array $m, array $in): void {
    $id = (int)($in['penalty_id'] ?? 0);
    if (!val('SELECT 1 FROM penalties WHERE id=? AND match_id=?', [$id, $m['id']])) fail('Penalty not found');
    q('DELETE FROM penalties WHERE id=?', [$id]);
    penalty_recalc($m);
}
function penalty_recalc(array $m): void {
    foreach (rows('SELECT * FROM innings WHERE match_id=? AND is_super=0 ORDER BY innings_no', [$m['id']]) as $inn) recompute_innings((int)$inn['id']);
    if (!is_test($m)) dls_update_target($m);
    $i2 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=2', [$m['id']]);
    if ($i2 && $i2['status'] === 'live' && innings_end_reason($m, $i2)) complete_innings($m, $i2);
    elseif ($m['status'] === 'completed') finish_match((int)$m['id']);
}

/** Powerplay overs for the match (0 = none) */
function match_powerplay(array $m, array $in): void {
    $n = (int)($in['overs'] ?? 0);
    if ($n < 0 || $n > (int)$m['overs']) fail('Powerplay overs must be between 0 and ' . $m['overs']);
    q('UPDATE matches SET powerplay_overs=? WHERE id=?', [$n, $m['id']]);
}

/** Change the overs of the match while the first innings is on (or before the start) */
function match_overs_change(array $m, array $in): void {
    $new = (int)($in['overs'] ?? 0);
    if ($new < 1 || $new > 100) fail('Overs must be between 1 and 100');
    $inn = latest_innings((int)$m['id']);
    if (!$inn) { q('UPDATE matches SET overs=? WHERE id=?', [$new, $m['id']]); return; }
    if ((int)$inn['innings_no'] !== 1 || $inn['status'] !== 'live') fail('Overs can only be changed during the first innings (use Rain / DLS later)');
    if (val('SELECT 1 FROM interruptions WHERE match_id=?', [$m['id']])) fail('Overs were already reduced for rain - undo that first');
    $bpo = (int)$m['balls_per_over'];
    $played = (int)ceil((int)$inn['legal_balls'] / $bpo);
    if ($new < max(1, $played)) fail("At least $played over(s) have been bowled already");
    q('UPDATE matches SET overs=? WHERE id=?', [$new, $m['id']]);
    q('UPDATE innings SET max_overs=?, start_overs=? WHERE id=?', [$new, $new, $inn['id']]);
    $inn = recompute_innings((int)$inn['id']);
    if (innings_end_reason($m, $inn)) complete_innings($m, $inn);
}

/** Swap a wrongly chosen batter who has not faced a ball yet */
function match_batter_change(array $m, array $in): void {
    $inn = live_innings($m);
    $out = (int)($in['out_id'] ?? 0); $new = (int)($in['in_id'] ?? 0);
    if (!in_array($out, [(int)$inn['striker_id'], (int)$inn['non_striker_id']], true) || !$out) fail('That batter is not at the crease');
    if (val('SELECT 1 FROM balls WHERE innings_id=? AND (batsman_id=? OR non_striker_id=? OR dismissed_id=?) LIMIT 1', [$inn['id'], $out, $out, $out])) fail('This batter has already been at the crease for a ball - use Retire instead');
    if (!in_array($new, available_batters($inn), true)) fail('That player cannot bat now');
    $col = $out === (int)$inn['striker_id'] ? 'striker_id' : 'non_striker_id';
    q("UPDATE innings SET $col=? WHERE id=?", [$new, $inn['id']]);
    q('DELETE FROM innings_batters WHERE innings_id=? AND player_id=?', [$inn['id'], $out]);
    add_innings_batter((int)$inn['id'], $new);
}

/** Tag an earlier ball with shot / fielding details */
function match_ball_detail(array $m, array $in): void {
    [$inn, $ball] = find_ball($m, $in);
    $d = ball_details_in($m, $inn, $in);
    q('UPDATE balls SET shot_type=?, shot_zone=?, drop_id=?, field_id=?, field_runs=?, fielded_id=? WHERE id=?', [$d['shot_type'], $d['shot_zone'], $d['drop_id'], $d['field_id'], $d['field_runs'], $d['fielded_id'], $ball['id']]);
}

function find_ball(array $m, array $in): array {
    $inn = row('SELECT * FROM innings WHERE id=? AND match_id=?', [(int)($in['innings_id'] ?? 0), $m['id']]);
    if (!$inn) fail('Innings not found');
    $ball = row('SELECT * FROM balls WHERE innings_id=? AND seq=?', [$inn['id'], (int)($in['seq'] ?? 0)]);
    if (!$ball) fail('Ball not found');
    return [$inn, $ball];
}

/** Turn a stored ball back into scoring input so it can be replayed */
function ball_to_input(array $b): array {
    $in = ['runs' => 0, 'extra' => $b['extra_type'], 'wicket' => null, 'shot_type' => $b['shot_type'], 'shot_zone' => $b['shot_zone'],
           'drop_id' => $b['drop_id'], 'field_id' => $b['field_id'], 'field_runs' => $b['field_runs'], 'fielded_id' => $b['fielded_id'], 'bouncer' => (int)($b['is_bouncer'] ?? 0), '_created_at' => $b['created_at']];
    switch ($b['extra_type']) {
        case 'wd': $in['runs'] = max(0, (int)$b['extra_runs'] - 1); break;
        case 'b': case 'lb': $in['runs'] = (int)$b['extra_runs']; break;
        default: $in['runs'] = (int)$b['runs_bat'];
    }
    if ($b['wicket_type']) $in['wicket'] = ['type' => $b['wicket_type'], 'dismissed_id' => $b['dismissed_id'], 'fielder_id' => $b['fielder_id']];
    return $in;
}

/** Edit (or delete) any earlier ball: everything after it is replayed with the batters and bowlers as recorded */
function match_ball_edit(array $m, array $in, bool $delete = false): void {
    if ($m['status'] === 'abandoned') fail('Match was abandoned');
    [$inn, $ball] = find_ball($m, $in);
    $later = rows('SELECT * FROM balls WHERE innings_id=? AND seq>=? ORDER BY seq', [$inn['id'], $ball['seq']]);
    $wasCompleted = $inn['status'] === 'completed';
    $laterInnings = rows('SELECT * FROM innings WHERE match_id=? AND innings_no>? ORDER BY innings_no', [$m['id'], $inn['innings_no']]);

    q('DELETE FROM balls WHERE innings_id=? AND seq>=?', [$inn['id'], $ball['seq']]);
    q("UPDATE innings SET status='live' WHERE id=?", [$inn['id']]);
    q("UPDATE matches SET status='live', winner_team_id=NULL, result_text=NULL, result_method=NULL, player_of_match_id=NULL, best_bat_id=NULL, best_bowl_id=NULL WHERE id=?", [$m['id']]);
    $m = load_match((int)$m['id']);
    recompute_innings((int)$inn['id']);

    $replay = [];
    foreach ($later as $i => $b) {
        $input = ball_to_input($b);
        if ($i === 0) {
            if ($delete) continue;
            $edit = array_intersect_key($in, array_flip(['runs', 'extra', 'wicket', 'shot_type', 'shot_zone', 'drop_id', 'field_id', 'field_runs']));
            $input = array_merge($input, $edit);
            if (array_key_exists('extra', $in) && ($in['extra'] === '' || $in['extra'] === null)) $input['extra'] = null;
            if (array_key_exists('wicket', $in) && !$in['wicket']) $input['wicket'] = null;
        }
        $replay[] = [$b, $input];
    }
    foreach ($replay as [$b, $input]) {
        $cur = row('SELECT * FROM innings WHERE id=?', [$inn['id']]);
        if ($cur['status'] !== 'live') fail('That change would end the innings before the later balls - delete those balls first');
        q('UPDATE innings SET striker_id=?, non_striker_id=?, bowler_id=? WHERE id=?', [$b['batsman_id'], $b['non_striker_id'], $b['bowler_id'], $inn['id']]);
        add_innings_batter((int)$inn['id'], (int)$b['batsman_id']);
        if ($b['non_striker_id']) add_innings_batter((int)$inn['id'], (int)$b['non_striker_id']);
        $input['_innings_id'] = (int)$inn['id'];
        score_ball($m, $input);
    }
    // batters who were only ever chosen after the edited ball and never faced one are no longer in
    foreach (rows('SELECT player_id FROM innings_batters WHERE innings_id=?', [$inn['id']]) as $r) {
        $p = (int)$r['player_id'];
        $cur = row('SELECT striker_id, non_striker_id FROM innings WHERE id=?', [$inn['id']]);
        if (in_array($p, [(int)$cur['striker_id'], (int)$cur['non_striker_id']], true)) continue;
        if (!val('SELECT 1 FROM balls WHERE innings_id=? AND (batsman_id=? OR non_striker_id=? OR dismissed_id=?) LIMIT 1', [$inn['id'], $p, $p, $p])) q('DELETE FROM innings_batters WHERE innings_id=? AND player_id=?', [$inn['id'], $p]);
    }
    $cur = row('SELECT * FROM innings WHERE id=?', [$inn['id']]);
    if ($cur['status'] === 'live') {
        if ($wasCompleted || $laterInnings) {
            if (innings_end_reason($m, $cur)) complete_innings($m, $cur);
            elseif ($laterInnings) complete_innings($m, $cur);       // it had been ended by the scorer - keep it ended
        }
    }
    $cur = row('SELECT * FROM innings WHERE id=?', [$inn['id']]);
    if ($cur['status'] !== 'live' && $laterInnings) {
        q("UPDATE matches SET status='live' WHERE id=?", [$m['id']]);
        $m = load_match((int)$m['id']);
        if ((int)$inn['innings_no'] === 1) {
            dls_update_target($m);
            $i2 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=2', [$m['id']]);
            if ($i2['status'] === 'live' && innings_end_reason($m, $i2)) complete_innings($m, $i2);
            elseif ($i2['status'] === 'completed') finish_match((int)$m['id']);
        } else {
            $lastInn = latest_innings((int)$m['id']);
            if ($lastInn['status'] === 'completed') finish_match((int)$m['id']);
        }
    }
}

/** Tie? Play a super over */
function match_super_over(array $m): void {
    if ($m['status'] !== 'completed' || $m['winner_team_id'] || $m['result_method'] === 'rain') fail('A super over is only for a tied match');
    if (!empty($m['super_over'])) fail('Super over already started');
    q("UPDATE matches SET super_over=1, status='live', result_text='Super over!', player_of_match_id=NULL WHERE id=?", [$m['id']]);
}

/** Umpires, scorer, commentator and a live-stream link */
function match_details_save(array $m, array $in): void {
    $o = [];
    foreach (['umpire1', 'umpire2', 'third_umpire', 'referee', 'scorer_name', 'commentator', 'streamer'] as $k) {
        $v = mb_substr(trim((string)($in[$k] ?? '')), 0, 60);
        if ($v !== '') $o[$k] = $v;
    }
    $url = trim((string)($in['stream_url'] ?? ''));
    if ($url !== '' && (!preg_match('~^https?://~i', $url) || strlen($url) > 255)) fail('The stream link must start with http:// or https://');
    $bt = (string)($in['ball_type'] ?? ($m['ball_type'] ?? ''));
    $bt = in_array($bt, BALL_TYPES, true) ? $bt : null;
    $round = mb_substr(trim((string)($in['round_name'] ?? ($m['round_name'] ?? ''))), 0, 60) ?: null;
    $bonus = [];
    foreach ((array)($in['bonus'] ?? []) as $tid => $pts) if (in_array((int)$tid, [(int)$m['team1_id'], (int)$m['team2_id']], true) && (int)$pts) $bonus[(string)(int)$tid] = max(-10, min(10, (int)$pts));
    if (!array_key_exists('bonus', $in) && !empty($m['bonus_json'])) $bonus = json_decode($m['bonus_json'], true) ?: [];
    q('UPDATE matches SET officials=?, stream_url=?, ball_type=?, round_name=?, bonus_json=? WHERE id=?', [$o ? json_encode($o) : null, $url ?: null, $bt, $round, $bonus ? json_encode($bonus) : null, $m['id']]);
}

/* ---------- Player insights (free) ---------- */

function player_insights(int $pid, string $vs, array $vp): array {
    $balls = rows("SELECT b.runs_bat, b.extra_type, b.extra_runs, b.is_legal, b.is_wicket, b.wicket_type, b.dismissed_id, b.batsman_id, b.bowler_id, b.over_no, b.shot_type, b.shot_zone, b.fielder_id, b.drop_id, b.field_id, b.field_runs, i.max_overs, i.innings_no, i.id innings_id
        FROM balls b JOIN innings i ON i.id=b.innings_id JOIN matches m ON m.id=i.match_id
        WHERE (b.batsman_id=? OR b.bowler_id=? OR b.dismissed_id=? OR b.fielder_id=? OR b.drop_id=? OR b.field_id=?) AND i.is_super=0 AND $vs ORDER BY b.innings_id, b.seq", array_merge([$pid, $pid, $pid, $pid, $pid, $pid], $vp));
    $phase = ['powerplay' => ['r' => 0, 'b' => 0, 'o' => 0], 'middle' => ['r' => 0, 'b' => 0, 'o' => 0], 'death' => ['r' => 0, 'b' => 0, 'o' => 0]];
    $bowlPhase = ['powerplay' => ['r' => 0, 'b' => 0, 'w' => 0], 'middle' => ['r' => 0, 'b' => 0, 'w' => 0], 'death' => ['r' => 0, 'b' => 0, 'w' => 0]];
    $outs = []; $wkTypes = []; $shots = []; $zones = []; $runTypes = ['dot' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0, '6' => 0];
    $drops = 0; $saved = 0; $missed = 0; $ballsFaced = 0; $dotsBowled = 0; $legalBowled = 0; $boundariesConceded = 0;
    $firstBalls = []; // balls 1-10 of each innings
    foreach ($balls as $b) {
        $pos = (int)$b['max_overs'] > 0 ? (int)$b['over_no'] / (int)$b['max_overs'] : 0;
        $ph = $pos < 0.3 ? 'powerplay' : ($pos < 0.75 ? 'middle' : 'death');
        $nd = in_array($b['wicket_type'], NON_DELIVERY, true);
        if ((int)$b['batsman_id'] === $pid && !$nd) {
            $faced = $b['extra_type'] !== 'wd';
            if ($faced) { $phase[$ph]['b']++; $ballsFaced++; }
            $phase[$ph]['r'] += (int)$b['runs_bat'];
            if ($faced) {
                $r = (int)$b['runs_bat'];
                if ($r === 0 && !$b['extra_type']) $runTypes['dot']++; elseif (isset($runTypes[(string)$r])) $runTypes[(string)$r]++;
                if ($b['shot_type']) $shots[$b['shot_type']] = ['r' => ($shots[$b['shot_type']]['r'] ?? 0) + $r, 'b' => ($shots[$b['shot_type']]['b'] ?? 0) + 1];
                if ($b['shot_zone']) $zones[(int)$b['shot_zone']] = ['r' => ($zones[(int)$b['shot_zone']]['r'] ?? 0) + $r, 'b' => ($zones[(int)$b['shot_zone']]['b'] ?? 0) + 1];
                $k = $b['innings_id']; $firstBalls[$k] = ($firstBalls[$k] ?? 0) + 1;
            }
        }
        if ((int)$b['dismissed_id'] === $pid && $b['is_wicket']) {
            $outs[$b['wicket_type']] = ($outs[$b['wicket_type']] ?? 0) + 1;
            $phase[$ph]['o']++;
            if (($firstBalls[$b['innings_id']] ?? 0) <= 10) $early = ($early ?? 0) + 1;
        }
        if ((int)$b['bowler_id'] === $pid && !$nd) {
            $bowlPhase[$ph]['b'] += (int)$b['is_legal'];
            $bowlPhase[$ph]['r'] += (int)$b['runs_bat'] + (in_array($b['extra_type'], ['wd', 'nb'], true) ? (int)$b['extra_runs'] : 0);
            if ($b['is_wicket'] && in_array($b['wicket_type'], BOWLER_WICKETS, true)) { $bowlPhase[$ph]['w']++; $wkTypes[$b['wicket_type']] = ($wkTypes[$b['wicket_type']] ?? 0) + 1; }
            if ($b['is_legal']) { $legalBowled++; if ((int)$b['runs_bat'] === 0 && !$b['extra_type']) $dotsBowled++; }
            if ((int)$b['runs_bat'] >= 4) $boundariesConceded++;
        }
        if ((int)$b['drop_id'] === $pid) $drops++;
        if ((int)$b['field_id'] === $pid) { if ((int)$b['field_runs'] > 0) $saved += (int)$b['field_runs']; else $missed += -(int)$b['field_runs']; }
    }
    foreach ($phase as &$p) { $p['sr'] = $p['b'] ? round($p['r'] * 100 / $p['b'], 1) : null; } unset($p);
    foreach ($bowlPhase as &$p) { $p['econ'] = $p['b'] ? round($p['r'] * 6 / $p['b'], 2) : null; } unset($p);
    arsort($outs); arsort($wkTypes);
    uasort($shots, fn($a, $b) => $b['r'] <=> $a['r']);
    $strengths = []; $weaknesses = [];
    $bestPh = null; $worstPh = null;
    foreach ($phase as $k => $p) if ($p['b'] >= 12) { if ($bestPh === null || $p['sr'] > $phase[$bestPh]['sr']) $bestPh = $k; if ($worstPh === null || $p['sr'] < $phase[$worstPh]['sr']) $worstPh = $k; }
    if ($bestPh) $strengths[] = 'Scores fastest in the ' . $bestPh . ' overs (SR ' . $phase[$bestPh]['sr'] . ')';
    if ($worstPh && $worstPh !== $bestPh) $weaknesses[] = 'Slowest in the ' . $worstPh . ' overs (SR ' . $phase[$worstPh]['sr'] . ')';
    if ($shots) { $top = array_key_first($shots); $strengths[] = 'Favourite shot: ' . $top . ' (' . $shots[$top]['r'] . ' runs)'; }
    if ($zones) { arsort($zones); $z = array_key_first($zones); $strengths[] = 'Most runs through ' . strtolower(WAGON_ZONES[$z]) . ' (' . $zones[$z]['r'] . ')'; }
    if ($outs) { $t = array_key_first($outs); $tot = array_sum($outs); if ($tot >= 3 && $outs[$t] / $tot >= 0.4) $weaknesses[] = ucfirst($t) . ' ' . $outs[$t] . ' of ' . $tot . ' times out'; }
    if (($early ?? 0) >= 3) $weaknesses[] = 'Out inside the first 10 balls ' . $early . ' times';
    if ($ballsFaced >= 30) { $dotPct = round($runTypes['dot'] * 100 / $ballsFaced); if ($dotPct >= 50) $weaknesses[] = "$dotPct% dot balls faced"; elseif ($dotPct <= 35) $strengths[] = "Only $dotPct% dot balls - keeps the strike rotating"; }
    if ($legalBowled >= 24) { $dp = round($dotsBowled * 100 / $legalBowled); if ($dp >= 50) $strengths[] = "$dp% dot balls bowled"; if ($boundariesConceded * 6 / $legalBowled >= 1.2) $weaknesses[] = 'Concedes more than a boundary an over'; }
    if ($wkTypes) { $t = array_key_first($wkTypes); $strengths[] = 'Takes most wickets ' . $t . ' (' . $wkTypes[$t] . ')'; }
    if ($drops >= 2) $weaknesses[] = "Dropped $drops catches";
    if ($saved > $missed && $saved >= 4) $strengths[] = "Saved $saved runs in the field";
    return ['phases' => $phase, 'bowl_phases' => $bowlPhase, 'dismissals' => $outs, 'wicket_types' => $wkTypes, 'shots' => $shots, 'zones' => $zones, 'run_types' => $runTypes,
            'fielding' => ['drops' => $drops, 'saved' => $saved, 'missed' => $missed], 'strengths' => $strengths, 'weaknesses' => $weaknesses, 'zone_names' => WAGON_ZONES];
}

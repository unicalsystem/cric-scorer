<?php
/**
 * Playoffs & bowling quotas - v1.27
 *  - A "League + playoffs" tournament picks a playoff format when it is created: IPL style (Qualifier 1,
 *    Eliminator, Qualifier 2, Final - the default), Semi-finals + Final, or Final only, with its own overs
 *    and overs-per-bowler for the playoff matches and for the final.
 *  - Club tournaments run the playoffs per category (Junior, Open, Mixed…).
 *  - Only league-stage matches count in points tables; playoff winners move on automatically. When a playoff
 *    ends without a winner (tie with a tied / no super over, or abandoned) the higher-placed team goes through.
 *  - Bowling quota: "max N overs a bowler, but up to X bowlers may bowl Y overs" (e.g. 6 overs: 1 each, 2 bowlers may bowl 2).
 */
declare(strict_types=1);

const PLAYOFF_FORMATS = ['ipl', 'semis', 'final', 'none'];
const STAGE_NAMES = ['q1' => 'Qualifier 1', 'elim' => 'Eliminator', 'q2' => 'Qualifier 2', 'sf1' => 'Semi-final 1', 'sf2' => 'Semi-final 2', 'final' => 'Final'];

function schema_v127(): void {
    if (!val("SHOW COLUMNS FROM match_types LIKE 'extra_bowlers'")) db()->exec("ALTER TABLE match_types ADD COLUMN extra_bowlers TINYINT NOT NULL DEFAULT 0 AFTER max_overs_bowler, ADD COLUMN extra_overs TINYINT NOT NULL DEFAULT 0 AFTER extra_bowlers");
    if (!val("SHOW COLUMNS FROM matches LIKE 'extra_bowlers'")) db()->exec("ALTER TABLE matches ADD COLUMN extra_bowlers TINYINT NOT NULL DEFAULT 0 AFTER max_overs_bowler, ADD COLUMN extra_overs TINYINT NOT NULL DEFAULT 0 AFTER extra_bowlers");
    if (!val("SHOW COLUMNS FROM matches LIKE 'stage'")) db()->exec("ALTER TABLE matches ADD COLUMN stage VARCHAR(10) NOT NULL DEFAULT 'league' AFTER tournament_id, ADD COLUMN pool VARCHAR(40) NULL AFTER stage");
    if (!val("SHOW COLUMNS FROM tournaments LIKE 'playoff_json'")) db()->exec("ALTER TABLE tournaments ADD COLUMN playoff_json TEXT NULL AFTER format");
}

/* ---------- bowling quota ---------- */

/** Clean "up to X bowlers may bowl Y overs" against the normal max */
function extra_quota(array $in, int $max): array {
    $xb = max(0, min(11, (int)($in['extra_bowlers'] ?? 0)));
    $xo = max(0, min(50, (int)($in['extra_overs'] ?? 0)));
    if ($max <= 0 || $xb === 0 || $xo <= $max) return [0, 0];
    return [$xb, $xo];
}

/**
 * Most overs this bowler may bowl in the innings. $balls = legal balls per bowler so far.
 * A bowler who has already gone past the normal max keeps the bigger quota; otherwise they may take one of
 * the X extra slots only while fewer than X other bowlers have used one.
 */
function bowler_cap(array $m, array $balls, int $pid): int {
    $max = (int)$m['max_overs_bowler'];
    $xb = (int)($m['extra_bowlers'] ?? 0); $xo = (int)($m['extra_overs'] ?? 0);
    if ($max <= 0 || $xb <= 0 || $xo <= $max) return $max;
    $lim = $max * (int)$m['balls_per_over'];
    if (($balls[$pid] ?? 0) > $lim) return $xo;
    $used = 0;
    foreach ($balls as $id => $b) if ((int)$id !== $pid && $b > $lim) $used++;
    return $used < $xb ? $xo : $max;
}

/** Legal balls per bowler in an innings */
function innings_bowler_balls(int $innId): array {
    $out = [];
    foreach (rows('SELECT bowler_id, SUM(is_legal) b FROM balls WHERE innings_id=? GROUP BY bowler_id', [$innId]) as $r) $out[(int)$r['bowler_id']] = (int)$r['b'];
    return $out;
}

/* ---------- playoff settings ---------- */

function playoff_defaults(): array {
    return ['format' => 'ipl', 'top' => 4, 'po_overs' => null, 'po_max_bowler' => null, 'final_overs' => null, 'final_max_bowler' => null];
}

function playoff_clean(array $in): array {
    $n = fn($v, $lo, $hi) => ($v === null || $v === '') ? null : max($lo, min($hi, (int)$v));
    $f = in_array($in['format'] ?? '', PLAYOFF_FORMATS, true) ? $in['format'] : 'ipl';
    return ['format' => $f, 'po_overs' => $n($in['po_overs'] ?? null, 1, 100), 'po_max_bowler' => $n($in['po_max_bowler'] ?? null, 0, 50),
            'final_overs' => $n($in['final_overs'] ?? null, 1, 100), 'final_max_bowler' => $n($in['final_max_bowler'] ?? null, 0, 50)];
}

/** Playoff settings of a tournament; old "League + Final" tournaments get a final only */
function tournament_playoffs(array $t): array {
    if (($t['format'] ?? '') !== 'league_final') return ['format' => 'none'] + playoff_defaults();
    $j = !empty($t['playoff_json']) ? json_decode($t['playoff_json'], true) : null;
    return $j ? playoff_clean($j) : playoff_clean(['format' => 'final']);
}

/** Teams needed in round 1 */
function playoff_needs(string $f): int { return ['ipl' => 4, 'semis' => 4, 'final' => 2][$f] ?? 0; }

/* ---------- standings per pool ---------- */

/** [poolKey => ['name' => ..., 'teams' => [team ids in finishing order], 'league_left' => n]] */
function playoff_pools(array $t): array {
    $tid = (int)$t['id'];
    $cfg = league_of_academy(nint($t['academy_id'] ?? null));
    $left = fn(array $ids) => $ids ? (int)val('SELECT COUNT(*) FROM matches WHERE tournament_id=? AND stage=\'league\' AND status IN (\'scheduled\',\'live\') AND team1_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$tid], $ids)) : 0;
    $pools = [];
    if ($cfg) {
        $lp = league_points($tid, $cfg);
        foreach ($cfg['categories'] as $c) {
            $ids = array_map(fn($r) => (int)$r['id'], array_values(array_filter($lp['teams'], fn($r) => $r['cat'] === $c['key'])));
            if (count($ids) >= 2) $pools[$c['key']] = ['name' => $c['name'], 'teams' => $ids, 'league_left' => $left($ids)];
        }
    } else {
        $ids = array_map(fn($r) => (int)$r['id'], points_table($tid));
        if (count($ids) >= 2) $pools[''] = ['name' => '', 'teams' => $ids, 'league_left' => $left($ids)];
    }
    return $pools;
}

/* ---------- creating & advancing ---------- */

function playoff_match_insert(array $t, array $po, string $pool, string $poolName, string $stage, int $a, int $b): void {
    $final = $stage === 'final';
    $overs = $po[$final ? 'final_overs' : 'po_overs'] ?? null; $overs = $overs ?: (int)($t['overs'] ?? 20);
    $maxb = $po[$final ? 'final_max_bowler' : 'po_max_bowler'] ?? null; $maxb = $maxb === null ? (int)($t['max_overs_bowler'] ?? 0) : (int)$maxb;
    $cfg = league_of_academy(nint($t['academy_id'] ?? null));
    $players = $cfg ? (int)$cfg['playing'] : (int)($t['players_per_side'] ?? 11);
    $title = ($poolName !== '' ? $poolName . ' - ' : '') . STAGE_NAMES[$stage];
    q('INSERT INTO matches (owner_id, tournament_id, stage, pool, match_type_id, title, round_name, team1_id, team2_id, overs, balls_per_over, players_per_side, max_overs_bowler, last_man_stands, venue, match_date, bouncers_per_over)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$t['owner_id'], $t['id'], $stage, $pool, $t['match_type_id'], $title, STAGE_NAMES[$stage], $a, $b,
       $overs, $t['balls_per_over'] ?? 6, $players, $maxb, $t['last_man_stands'] ?? 0, $t['venue'], date('Y-m-d H:i:s'), $t['bouncers_per_over'] ?? 1]);
    inherit_extras(lastId());   // v1.41
}

function tournament_full(int $tid): ?array {
    return row('SELECT t.*, mt.overs, mt.balls_per_over, mt.players_per_side, mt.max_overs_bowler, mt.last_man_stands, mt.bouncers_per_over FROM tournaments t LEFT JOIN match_types mt ON mt.id=t.match_type_id WHERE t.id=?', [$tid]);
}

/** Round 1 from the league standings (owner action). Returns how many matches were created. */
function playoff_start(int $tid, bool $force): array {
    $t = tournament_full($tid);
    $po = tournament_playoffs($t);
    if ($po['format'] === 'none') fail('This tournament has no playoffs - edit it and choose "League + playoffs"');
    if (val("SELECT 1 FROM matches WHERE tournament_id=? AND stage<>'league' LIMIT 1", [$tid])) fail('Playoffs already created');
    $pools = playoff_pools($t);
    if (!$pools) fail('Not enough teams for playoffs');
    $left = array_sum(array_column($pools, 'league_left'));
    if ($left && !$force) return ['confirm' => "$left league " . ($left === 1 ? 'match is' : 'matches are') . ' not finished yet. Create the playoffs from the current table anyway?'];
    $n = 0;
    foreach ($pools as $key => $p) {
        $s = $p['teams']; $f = $po['format'];
        if (count($s) < playoff_needs($f)) $f = 'final';               // too few teams in this category: straight to a final
        if ($f === 'ipl') { playoff_match_insert($t, $po, (string)$key, $p['name'], 'q1', $s[0], $s[1]); playoff_match_insert($t, $po, (string)$key, $p['name'], 'elim', $s[2], $s[3]); $n += 2; }
        elseif ($f === 'semis') { playoff_match_insert($t, $po, (string)$key, $p['name'], 'sf1', $s[0], $s[3]); playoff_match_insert($t, $po, (string)$key, $p['name'], 'sf2', $s[1], $s[2]); $n += 2; }
        else { playoff_match_insert($t, $po, (string)$key, $p['name'], 'final', $s[0], $s[1]); $n++; }
    }
    return ['created' => $n];
}

/** Winner / loser of a finished playoff match; no winner = team1 (always the higher-placed side) goes through */
function playoff_result(?array $m): ?array {
    if (!$m || !in_array($m['status'], ['completed', 'abandoned'], true)) return null;
    $a = (int)$m['team1_id']; $b = (int)$m['team2_id'];
    $w = $m['winner_team_id'] ? (int)$m['winner_team_id'] : $a;
    return ['w' => $w, 'l' => $w === $a ? $b : $a];
}

/** Create (or re-point, if not started) the next playoff matches once results are in. Safe to call any time. */
function playoff_advance(int $tid): void {
    $t = tournament_full($tid);
    if (!$t) return;
    $po = tournament_playoffs($t);
    if ($po['format'] === 'none') return;
    $all = rows("SELECT * FROM matches WHERE tournament_id=? AND stage<>'league'", [$tid]);
    if (!$all) return;
    $byPool = [];
    foreach ($all as $m) $byPool[(string)$m['pool']][$m['stage']] = $m;
    $poolNames = [];
    if ($cfg = league_of_academy(nint($t['academy_id'] ?? null))) foreach ($cfg['categories'] as $c) $poolNames[$c['key']] = $c['name'];
    foreach ($byPool as $pool => $st) {
        $want = [];                                                    // stage => [team1, team2]
        if (isset($st['q1'])) {
            $q1 = playoff_result($st['q1']); $el = playoff_result($st['elim'] ?? null);
            if ($q1 && $el) $want['q2'] = [$q1['l'], $el['w']];
            $q2 = playoff_result($st['q2'] ?? null);
            if ($q1 && $q2) $want['final'] = [$q1['w'], $q2['w']];
        } elseif (isset($st['sf1'])) {
            $a = playoff_result($st['sf1']); $b = playoff_result($st['sf2'] ?? null);
            if ($a && $b) $want['final'] = [$a['w'], $b['w']];
        }
        foreach ($want as $stage => [$x, $y]) {
            $have = $st[$stage] ?? null;
            if (!$have) { playoff_match_insert($t, $po, $pool, $poolNames[$pool] ?? '', $stage, $x, $y); continue; }
            if ($have['status'] === 'scheduled' && ((int)$have['team1_id'] !== $x || (int)$have['team2_id'] !== $y)
                && !val('SELECT 1 FROM innings WHERE match_id=? LIMIT 1', [$have['id']]))
                q('UPDATE matches SET team1_id=?, team2_id=?, toss_winner_id=NULL, toss_decision=NULL WHERE id=?', [$x, $y, $have['id']]);
        }
    }
}

/** For the tournament page */
function playoff_info(array $t): array {
    $po = tournament_playoffs($t);
    if ($po['format'] === 'none') return ['playoffs' => $po];
    $started = (bool)val("SELECT 1 FROM matches WHERE tournament_id=? AND stage<>'league' LIMIT 1", [$t['id']]);
    return ['playoffs' => $po + ['started' => $started]];
}

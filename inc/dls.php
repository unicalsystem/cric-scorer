<?php
/**
 * Cric-Scorer - Duckworth-Lewis-Stern (DLS) Standard Edition
 *
 * Resource table: percentage of a team's run-scoring resources remaining, by overs left (row)
 * and wickets lost (column 0..9), as published by the ICC for the D/L Standard Edition.
 * Rows for 50,45,40,35,30,25,20,15,10,5,4,3,2,1 overs are the published values; the other
 * rows are filled from the Duckworth-Lewis exponential model fitted to those values
 * (max deviation from the published figures: 0.06%).
 *
 * Targets (ICC Standard Edition rules):
 *   R2 <  R1 :  T = floor(S × R2 / R1) + 1
 *   R2 >= R1 :  T = floor(S + G50 × (R2 − R1) / 100) + 1
 *   G50 = 245 for full-member international / first-class level, 200 for junior & club level.
 */
const DLS_TABLE = [
    /*  0 */ [0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0],
    /*  1 */ [3.6, 3.6, 3.6, 3.6, 3.6, 3.5, 3.5, 3.4, 3.2, 2.5],
    /*  2 */ [7.2, 7.1, 7.1, 7.0, 7.0, 6.8, 6.6, 6.2, 5.5, 3.7],
    /*  3 */ [10.6, 10.5, 10.4, 10.3, 10.2, 9.9, 9.5, 8.7, 7.2, 4.2],
    /*  4 */ [13.9, 13.8, 13.7, 13.5, 13.2, 12.7, 12.0, 10.7, 8.4, 4.5],
    /*  5 */ [17.2, 17.0, 16.8, 16.5, 16.1, 15.4, 14.3, 12.5, 9.4, 4.6],
    /*  6 */ [20.3, 20.1, 19.8, 19.4, 18.8, 17.8, 16.4, 13.9, 10.0, 4.7],
    /*  7 */ [23.4, 23.1, 22.7, 22.2, 21.4, 20.1, 18.2, 15.2, 10.5, 4.7],
    /*  8 */ [26.4, 26.0, 25.5, 24.8, 23.8, 22.3, 19.9, 16.2, 10.9, 4.7],
    /*  9 */ [29.3, 28.9, 28.2, 27.4, 26.1, 24.2, 21.4, 17.1, 11.2, 4.7],
    /* 10 */ [32.1, 31.6, 30.8, 29.8, 28.3, 26.1, 22.8, 17.9, 11.4, 4.7],
    /* 11 */ [34.9, 34.3, 33.4, 32.1, 30.4, 27.8, 24.0, 18.5, 11.5, 4.7],
    /* 12 */ [37.6, 36.8, 35.8, 34.4, 32.3, 29.4, 25.1, 19.0, 11.6, 4.7],
    /* 13 */ [40.2, 39.3, 38.1, 36.5, 34.2, 30.8, 26.1, 19.5, 11.7, 4.7],
    /* 14 */ [42.7, 41.7, 40.4, 38.5, 35.9, 32.2, 27.0, 19.9, 11.8, 4.7],
    /* 15 */ [45.2, 44.1, 42.6, 40.5, 37.6, 33.5, 27.8, 20.2, 11.8, 4.7],
    /* 16 */ [47.6, 46.4, 44.7, 42.4, 39.2, 34.7, 28.5, 20.5, 11.8, 4.7],
    /* 17 */ [49.9, 48.6, 46.7, 44.2, 40.6, 35.8, 29.2, 20.7, 11.8, 4.7],
    /* 18 */ [52.2, 50.7, 48.6, 45.9, 42.1, 36.8, 29.8, 20.9, 11.9, 4.7],
    /* 19 */ [54.4, 52.8, 50.5, 47.5, 43.4, 37.7, 30.3, 21.1, 11.9, 4.7],
    /* 20 */ [56.6, 54.8, 52.4, 49.1, 44.6, 38.6, 30.8, 21.2, 11.9, 4.7],
    /* 21 */ [58.7, 56.7, 54.1, 50.6, 45.8, 39.4, 31.2, 21.3, 11.9, 4.7],
    /* 22 */ [60.7, 58.6, 55.8, 52.0, 47.0, 40.2, 31.6, 21.4, 11.9, 4.7],
    /* 23 */ [62.7, 60.4, 57.4, 53.4, 48.0, 40.9, 32.0, 21.5, 11.9, 4.7],
    /* 24 */ [64.6, 62.2, 59.0, 54.7, 49.0, 41.6, 32.3, 21.6, 11.9, 4.7],
    /* 25 */ [66.5, 63.9, 60.5, 56.0, 50.0, 42.2, 32.6, 21.6, 11.9, 4.7],
    /* 26 */ [68.3, 65.6, 62.0, 57.2, 50.9, 42.8, 32.8, 21.7, 11.9, 4.7],
    /* 27 */ [70.1, 67.2, 63.4, 58.4, 51.8, 43.3, 33.0, 21.7, 11.9, 4.7],
    /* 28 */ [71.8, 68.8, 64.8, 59.5, 52.6, 43.8, 33.2, 21.8, 11.9, 4.7],
    /* 29 */ [73.5, 70.3, 66.1, 60.5, 53.4, 44.3, 33.4, 21.8, 11.9, 4.7],
    /* 30 */ [75.1, 71.8, 67.3, 61.6, 54.1, 44.7, 33.6, 21.8, 11.9, 4.7],
    /* 31 */ [76.7, 73.2, 68.6, 62.5, 54.8, 45.1, 33.8, 21.9, 11.9, 4.7],
    /* 32 */ [78.3, 74.6, 69.7, 63.5, 55.4, 45.4, 33.9, 21.9, 11.9, 4.7],
    /* 33 */ [79.8, 75.9, 70.9, 64.4, 56.0, 45.8, 34.0, 21.9, 11.9, 4.7],
    /* 34 */ [81.3, 77.2, 72.0, 65.2, 56.6, 46.1, 34.1, 21.9, 11.9, 4.7],
    /* 35 */ [82.7, 78.5, 73.0, 66.0, 57.2, 46.4, 34.2, 21.9, 11.9, 4.7],
    /* 36 */ [84.1, 79.7, 74.1, 66.8, 57.7, 46.7, 34.3, 21.9, 11.9, 4.7],
    /* 37 */ [85.4, 80.9, 75.0, 67.6, 58.2, 46.9, 34.4, 21.9, 11.9, 4.7],
    /* 38 */ [86.7, 82.0, 76.0, 68.3, 58.7, 47.2, 34.5, 21.9, 11.9, 4.7],
    /* 39 */ [88.0, 83.1, 76.9, 69.0, 59.1, 47.4, 34.5, 22.0, 11.9, 4.7],
    /* 40 */ [89.3, 84.2, 77.8, 69.6, 59.5, 47.6, 34.6, 22.0, 11.9, 4.7],
    /* 41 */ [90.5, 85.3, 78.7, 70.3, 59.9, 47.8, 34.6, 22.0, 11.9, 4.7],
    /* 42 */ [91.7, 86.3, 79.5, 70.9, 60.3, 48.0, 34.7, 22.0, 11.9, 4.7],
    /* 43 */ [92.8, 87.3, 80.3, 71.4, 60.7, 48.1, 34.7, 22.0, 11.9, 4.7],
    /* 44 */ [93.9, 88.2, 81.0, 72.0, 61.0, 48.3, 34.8, 22.0, 11.9, 4.7],
    /* 45 */ [95.0, 89.1, 81.8, 72.5, 61.3, 48.4, 34.8, 22.0, 11.9, 4.7],
    /* 46 */ [96.1, 90.0, 82.5, 73.0, 61.6, 48.6, 34.8, 22.0, 11.9, 4.7],
    /* 47 */ [97.1, 90.9, 83.2, 73.5, 61.9, 48.7, 34.9, 22.0, 11.9, 4.7],
    /* 48 */ [98.1, 91.8, 83.8, 74.0, 62.2, 48.8, 34.9, 22.0, 11.9, 4.7],
    /* 49 */ [99.1, 92.6, 84.5, 74.4, 62.4, 48.9, 34.9, 22.0, 11.9, 4.7],
    /* 50 */ [100.0, 93.4, 85.1, 74.9, 62.7, 49.0, 34.9, 22.0, 11.9, 4.7]
];

/** Resources (%) remaining with $u overs left (fractional allowed) and $w wickets lost */
function dls_R(float $u, int $w): float {
    $w = max(0, min(9, $w));
    if ($u <= 0) return 0.0;
    if ($u >= 50) return DLS_TABLE[50][$w];
    $lo = (int)floor($u); $hi = $lo + 1; $f = $u - $lo;
    $a = $lo === 0 ? 0.0 : DLS_TABLE[$lo][$w];
    return $a + (DLS_TABLE[$hi][$w] - $a) * $f;
}

function dls_g50(array $m): int { $g = (int)($m['dls_g50'] ?? 200); return in_array($g, [200, 245], true) ? $g : 200; }

/** Minimum overs team 2 must face for a result (ICC: 20 in ODIs, 5 in T20s) */
function dls_min_overs(array $m): int {
    $o = (int)$m['overs'];
    if ($o >= 50) return 20;
    if ($o >= 20) return 5;
    return max(1, (int)ceil($o / 4));
}

/** Total resources available to an innings: start allotment minus resources lost to each interruption */
function dls_innings_resources(array $m, array $inn): float {
    $bpo = (int)$m['balls_per_over'];
    $r = dls_R((float)$inn['start_overs'], 0);
    foreach (rows('SELECT * FROM interruptions WHERE match_id=? AND innings_no=? ORDER BY id', [$m['id'], $inn['innings_no']]) as $x) {
        $faced = (int)$x['legal_balls'] / $bpo;
        $r -= dls_R((float)$x['overs_before'] - $faced, (int)$x['wickets']) - dls_R((float)$x['overs_after'] - $faced, (int)$x['wickets']);
    }
    return max(0.0, $r);
}

function dls_score(int $S, float $R1, float $R2, int $g50): float {
    if ($R1 <= 0) return (float)$S;
    return $R2 < $R1 ? $S * $R2 / $R1 : $S + $g50 * ($R2 - $R1) / 100;
}
function dls_target(int $S, float $R1, float $R2, int $g50): int { return (int)floor(dls_score($S, $R1, $R2, $g50) + 1e-9) + 1; }
function dls_par(int $S, float $R1, float $R2used, int $g50): int { return (int)floor(dls_score($S, $R1, $R2used, $g50) + 1e-9); }

/** Has anything happened that makes DLS apply to this match? */
function dls_needed(array $m, ?array $i1, ?array $i2 = null): bool {
    if ((int)val('SELECT COUNT(*) FROM interruptions WHERE match_id=?', [$m['id']]) > 0) return true;
    if ($i1 && (int)$i1['max_overs'] !== (int)$i1['start_overs']) return true;
    if ($i1 && $i2 && (int)$i2['start_overs'] !== (int)$i1['start_overs']) return true;
    return false;
}

/** Recalculate and store team 2's target */
function dls_update_target(array $m): void {
    $i1 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=1', [$m['id']]);
    $i2 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=2', [$m['id']]);
    if (!$i1 || !$i2) return;
    if (!dls_needed($m, $i1, $i2)) { q('UPDATE innings SET target=?, dls=0 WHERE id=?', [(int)$i1['runs'] + 1, $i2['id']]); return; }
    $t = dls_target((int)$i1['runs'], dls_innings_resources($m, $i1), dls_innings_resources($m, $i2), dls_g50($m));
    q('UPDATE innings SET target=?, dls=1 WHERE id=?', [$t, $i2['id']]);
}

/* ---------- Scorer actions ---------- */

/** Rain: reduce the overs of the current innings (or of the match before it starts / at the break) */
function rain_reduce(array $m, array $in): void {
    $new = (int)($in['overs'] ?? 0);
    if ($new < 1) fail('Enter the overs available');
    $bpo = (int)$m['balls_per_over'];
    $inn = latest_innings((int)$m['id']);
    if (!$inn) {                                   // before the match: just a shorter match, no DLS needed
        if ($new > (int)$m['overs']) fail('Overs can only be reduced');
        q('UPDATE matches SET overs=? WHERE id=?', [$new, $m['id']]);
        return;
    }
    if ($inn['status'] === 'completed') {
        if ((int)$inn['innings_no'] === 2) fail('The match is over');
        // Innings break: team 2 gets fewer overs than team 1 had
        if ($new > (int)$inn['max_overs']) fail('Team 2 cannot get more overs than ' . $inn['max_overs']);
        q('UPDATE matches SET inn2_overs=? WHERE id=?', [$new, $m['id']]);
        return;
    }
    $faced = (int)$inn['legal_balls'];
    if ($new * $bpo < $faced) fail('That is fewer overs than already bowled (' . overs_str($faced, $bpo) . ')');
    if ($new >= (int)$inn['max_overs']) fail('Overs can only be reduced (currently ' . $inn['max_overs'] . ')');
    q('INSERT INTO interruptions (match_id, innings_no, legal_balls, wickets, overs_before, overs_after) VALUES (?,?,?,?,?,?)',
        [$m['id'], $inn['innings_no'], $faced, $inn['wickets'], $inn['max_overs'], $new]);
    q('UPDATE innings SET max_overs=? WHERE id=?', [$new, $inn['id']]);
    if ((int)$inn['innings_no'] === 2) dls_update_target($m);
    $inn = row('SELECT * FROM innings WHERE id=?', [$inn['id']]);
    if (innings_end_reason($m, $inn)) complete_innings($m, $inn);
}

/** Undo the most recent rain reduction */
function rain_undo(array $m): void {
    $x = row('SELECT * FROM interruptions WHERE match_id=? ORDER BY id DESC LIMIT 1', [$m['id']]);
    if (!$x) {
        if ($m['inn2_overs'] !== null) { q('UPDATE matches SET inn2_overs=NULL WHERE id=?', [$m['id']]); return; }
        fail('No rain interruption to undo');
    }
    $inn = row('SELECT * FROM innings WHERE match_id=? AND innings_no=?', [$m['id'], $x['innings_no']]);
    q('DELETE FROM interruptions WHERE id=?', [$x['id']]);
    q("UPDATE innings SET max_overs=?, status='live' WHERE id=?", [$x['overs_before'], $inn['id']]);
    if ((int)$x['innings_no'] === 1) q('DELETE FROM innings WHERE match_id=? AND innings_no=2 AND legal_balls=0', [$m['id']]);
    q("UPDATE matches SET status='live', winner_team_id=NULL, result_text=NULL, result_method=NULL, player_of_match_id=NULL WHERE id=?", [$m['id']]);
    dls_update_target($m);
}

/** Rain stopped play in the 2nd innings: decide the match on the DLS par score */
function rain_end_match(array $m): void {
    $i2 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=2', [$m['id']]);
    $i1 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=1', [$m['id']]);
    if (!$i1 || !$i2 || $i2['status'] !== 'live') fail('Rain result is only possible while the team batting second is chasing');
    $bpo = (int)$m['balls_per_over'];
    $min = dls_min_overs($m);
    q("UPDATE innings SET status='completed' WHERE id=?", [$i2['id']]);
    if ((int)$i2['legal_balls'] < $min * $bpo && (int)$i2['legal_balls'] < (int)$i2['max_overs'] * $bpo) {
        q("UPDATE matches SET status='abandoned', result_text=?, result_method='rain', winner_team_id=NULL WHERE id=?",
          ["No result - rain (fewer than $min overs in the chase)", $m['id']]);
        return;
    }
    $par = dls_par_now($m, $i1, $i2);
    $names = [];
    foreach (rows('SELECT id, name FROM teams WHERE id IN (?,?)', [$m['team1_id'], $m['team2_id']]) as $t) $names[(int)$t['id']] = $t['name'];
    $runs = (int)$i2['runs'];
    if ($runs > $par) {
        $winner = (int)$i2['batting_team_id'];
        $n = bat_limit($m, $winner);
        $left = max(1, ($m['last_man_stands'] ? $n : $n - 1) - (int)$i2['wickets']);
        $text = $names[$winner] . " won by $left wicket" . ($left === 1 ? '' : 's') . " (DLS method, par $par)";
    } elseif ($runs === $par) {
        $winner = null; $text = "Match tied (DLS method, par $par)";
    } else {
        $winner = (int)$i1['batting_team_id'];
        $d = $par - $runs;
        $text = $names[$winner] . " won by $d run" . ($d === 1 ? '' : 's') . " (DLS method, par $par)";
    }
    $pom = player_of_match((int)$m['id'], $winner);
    q("UPDATE matches SET status='completed', winner_team_id=?, result_text=?, result_method='rain', player_of_match_id=? WHERE id=?", [$winner, $text, $pom, $m['id']]);
}

/** DLS par score for team 2 right now */
function dls_par_now(array $m, array $i1, array $i2, ?int $legal = null, ?int $wkts = null): int {
    $bpo = (int)$m['balls_per_over'];
    $legal = $legal ?? (int)$i2['legal_balls'];
    $wkts = $wkts ?? (int)$i2['wickets'];
    $R1 = dls_innings_resources($m, $i1);
    $R2 = dls_innings_resources($m, $i2);
    $used = $R2 - dls_R((int)$i2['max_overs'] - $legal / $bpo, $wkts);
    return dls_par((int)$i1['runs'], $R1, $used, dls_g50($m));
}

/** Everything the app shows on the Rain / DLS panel */
function dls_info(array $m): array {
    $bpo = (int)$m['balls_per_over'];
    $i1 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=1', [$m['id']]);
    $i2 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=2', [$m['id']]);
    $int = rows('SELECT innings_no, legal_balls, wickets, overs_before, overs_after FROM interruptions WHERE match_id=? ORDER BY id', [$m['id']]);
    foreach ($int as &$x) { foreach ($x as $k => $v) $x[$k] = (int)$v; $x['at'] = overs_str($x['legal_balls'], $bpo); }
    unset($x);
    $info = ['g50' => dls_g50($m), 'min_overs' => dls_min_overs($m), 'interruptions' => $int,
             'active' => $i1 ? dls_needed($m, $i1, $i2) : false, 'inn2_overs' => $m['inn2_overs'] !== null ? (int)$m['inn2_overs'] : null];
    if ($i1) $info['R1'] = round(dls_innings_resources($m, $i1), 1);
    if ($i2) {
        $info['R2'] = round(dls_innings_resources($m, $i2), 1);
        if ($i2['status'] === 'live') {
            $info['par'] = dls_par_now($m, $i1, $i2);
            // Par at the end of each remaining over, if no more wickets fall
            $from = intdiv((int)$i2['legal_balls'], $bpo) + 1;
            $tbl = [];
            for ($o = max(1, $from); $o <= (int)$i2['max_overs']; $o++) $tbl[] = ['over' => $o, 'par' => dls_par_now($m, $i1, $i2, $o * $bpo, (int)$i2['wickets'])];
            $info['par_table'] = $tbl;
            $info['can_result'] = (int)$i2['legal_balls'] >= $info['min_overs'] * $bpo;
        }
    }
    return $info;
}

/** Live preview of the revised target if team 2's innings were reduced to $overs now */
function dls_preview(array $m, int $overs): array {
    $i1 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=1', [$m['id']]);
    $i2 = row('SELECT * FROM innings WHERE match_id=? AND innings_no=2', [$m['id']]);
    $bpo = (int)$m['balls_per_over'];
    if (!$i1) return ['note' => "The match becomes $overs overs a side - no DLS needed before the start."];
    if (!$i2 && $i1['status'] === 'live') {
        $R1 = dls_innings_resources($m, $i1) - (dls_R((int)$i1['max_overs'] - $i1['legal_balls'] / $bpo, (int)$i1['wickets']) - dls_R($overs - $i1['legal_balls'] / $bpo, (int)$i1['wickets']));
        return ['note' => 'Team batting first loses ' . round(dls_innings_resources($m, $i1) - $R1, 1) . '% of its resources. The DLS target is set at the innings break.'];
    }
    $S = (int)$i1['runs']; $R1 = dls_innings_resources($m, $i1);
    if (!$i2) return ['target' => dls_target($S, $R1, dls_R($overs, 0), dls_g50($m)), 'overs' => $overs];
    $R2 = dls_innings_resources($m, $i2) - (dls_R((int)$i2['max_overs'] - $i2['legal_balls'] / $bpo, (int)$i2['wickets']) - dls_R($overs - $i2['legal_balls'] / $bpo, (int)$i2['wickets']));
    return ['target' => dls_target($S, $R1, $R2, dls_g50($m)), 'overs' => $overs];
}

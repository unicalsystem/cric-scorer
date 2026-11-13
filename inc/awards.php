<?php
/**
 * Tournament leaderboard & awards - v1.30
 *  - Leaderboard: most runs / wickets / sixes / fours (existing) plus highest scores, best bowling figures,
 *    best strike rate, best economy and most catches.
 *  - Match awards (worked out automatically when a match ends): Player of the Match (= match MVP), best batter, best bowler.
 *  - Tournament awards: Player of the Tournament (most MVP points), Best Batter (most runs), Best Bowler (most
 *    wickets) and Most Sixes - shown as "leading so far" while matches remain, and final once the tournament is over
 *    (all matches played and, with playoffs, the final(s) finished).
 */
declare(strict_types=1);

/** Is the tournament finished? */
function tournament_over(array $t): bool {
    $tid = (int)$t['id'];
    $all = (int)val('SELECT COUNT(*) FROM matches WHERE tournament_id=?', [$tid]);
    if (!$all) return false;
    if (val("SELECT 1 FROM matches WHERE tournament_id=? AND status IN ('scheduled','live') LIMIT 1", [$tid])) return false;
    if (function_exists('tournament_playoffs') && tournament_playoffs($t)['format'] !== 'none')
        return (bool)val("SELECT 1 FROM matches WHERE tournament_id=? AND stage='final' AND status IN ('completed','abandoned') LIMIT 1", [$tid]);
    return true;
}

/** Extra leaderboard lists for a tournament (all stages, super overs excluded) */
function tournament_leaderboard_extra(int $tid, ?array $viewer, int $limit = 5): array {
    [$vs, $vp] = vis_sql($viewer, 'm');
    $p = array_merge([$tid], $vp);
    $from = "FROM balls b JOIN innings i ON i.id=b.innings_id JOIN matches m ON m.id=i.match_id WHERE m.tournament_id=? AND i.is_super=0 AND $vs";
    $team = 'JOIN players p ON p.id=x.pid JOIN teams t ON t.id=p.team_id';
    $nd = "b.wicket_type IN ('retired hurt','retired out','timed out')";
    // best single innings with the bat
    $hs = rows("SELECT x.*, p.name, t.short_name team, t.color FROM (SELECT b.batsman_id pid, i.match_id mid,
                  SUM(CASE WHEN $nd THEN 0 ELSE b.runs_bat END) runs, SUM(CASE WHEN b.extra_type='wd' OR $nd THEN 0 ELSE 1 END) balls,
                  MAX(b.is_wicket=1 AND b.dismissed_id=b.batsman_id AND b.wicket_type NOT IN ('retired hurt')) out_
                $from GROUP BY b.batsman_id, i.id, i.match_id) x $team WHERE x.runs > 0 ORDER BY x.runs DESC, x.balls ASC LIMIT $limit", $p);
    // best bowling in an innings
    $bf = rows("SELECT x.*, p.name, t.short_name team, t.color FROM (SELECT b.bowler_id pid, i.match_id mid,
                  SUM(b.is_wicket=1 AND b.wicket_type IN ('bowled','caught','lbw','stumped','hit wicket')) wkts,
                  SUM(b.runs_bat + CASE WHEN b.extra_type IN ('wd','nb') THEN b.extra_runs ELSE 0 END) runs, SUM(b.is_legal) balls
                $from GROUP BY b.bowler_id, i.id, i.match_id) x $team WHERE x.wkts > 0 ORDER BY x.wkts DESC, x.runs ASC LIMIT $limit", $p);
    // strike rate (min 10 balls) and economy (min 2 overs' worth of balls, so 6-over games qualify)
    $sr = rows("SELECT x.*, p.name, t.short_name team, t.color, ROUND(x.runs * 100 / x.balls, 1) sr FROM (SELECT b.batsman_id pid,
                  SUM(CASE WHEN $nd THEN 0 ELSE b.runs_bat END) runs, SUM(CASE WHEN b.extra_type='wd' OR $nd THEN 0 ELSE 1 END) balls
                $from GROUP BY b.batsman_id) x $team WHERE x.balls >= 10 ORDER BY sr DESC, x.runs DESC LIMIT $limit", $p);
    $ec = rows("SELECT x.*, p.name, t.short_name team, t.color, ROUND(x.runs * 6 / x.balls, 2) econ FROM (SELECT b.bowler_id pid,
                  SUM(b.runs_bat + CASE WHEN b.extra_type IN ('wd','nb') THEN b.extra_runs ELSE 0 END) runs, SUM(b.is_legal) balls,
                  SUM(b.is_wicket=1 AND b.wicket_type IN ('bowled','caught','lbw','stumped','hit wicket')) wkts
                $from GROUP BY b.bowler_id) x $team WHERE x.balls >= 12 ORDER BY econ ASC, x.wkts DESC LIMIT $limit", $p);
    $ct = rows("SELECT x.*, p.name, t.short_name team, t.color FROM (SELECT b.fielder_id pid, SUM(b.wicket_type IN ('caught','stumped')) catches, SUM(b.wicket_type LIKE 'run out%') runouts
                $from AND b.is_wicket=1 AND b.fielder_id IS NOT NULL GROUP BY b.fielder_id) x $team WHERE x.catches + x.runouts > 0 ORDER BY x.catches DESC, x.runouts DESC LIMIT $limit", $p);
    $num = fn($l) => array_map(function ($r) { foreach ($r as $k => $v) if (is_numeric($v) && !in_array($k, ['name', 'team', 'color'], true)) $r[$k] = $v + 0; return $r; }, $l);
    return ['high_scores' => $num($hs), 'best_figures' => $num($bf), 'strike_rate' => $num($sr), 'economy' => $num($ec), 'catches' => $num($ct)];
}

/** Awards for every finished match plus the tournament's own awards */
function tournament_awards(array $t, array $leaders, array $mvp, ?array $viewer): array {
    [$vs, $vp] = vis_sql($viewer, 'm');
    $ms = rows("SELECT m.* FROM matches m WHERE m.tournament_id=? AND m.status='completed' AND $vs ORDER BY COALESCE(m.match_date, m.created_at), m.id", array_merge([(int)$t['id']], $vp));
    $match = [];
    foreach ($ms as $m) {
        $mv = mvp_points((int)$m['id']);
        $by = []; foreach ($mv as $x) $by[(int)$x['id']] = $x;
        $pl = function ($pid) use ($by) {
            if (!$pid) return null;
            $x = $by[(int)$pid] ?? null;
            $name = $x['name'] ?? val('SELECT name FROM players WHERE id=?', [$pid]);
            return $name ? ['id' => (int)$pid, 'name' => $name, 'r' => (int)($x['r'] ?? 0), 'b' => (int)($x['b'] ?? 0), 'w' => (int)($x['w'] ?? 0), 'rc' => (int)($x['rc'] ?? 0), 'balls' => (int)($x['balls'] ?? 0), 'pts' => (float)($x['total'] ?? 0)] : null;
        };
        $teams = [];
        foreach (rows('SELECT id, short_name, color FROM teams WHERE id IN (?,?)', [$m['team1_id'], $m['team2_id']]) as $x) $teams[(int)$x['id']] = $x;
        $match[] = ['id' => (int)$m['id'], 'title' => $m['title'], 'stage' => $m['stage'] ?? 'league', 'result' => $m['result_text'],
            't1' => $teams[(int)$m['team1_id']] ?? null, 't2' => $teams[(int)$m['team2_id']] ?? null,
            'pom' => $pl($m['player_of_match_id']), 'bat' => $pl($m['best_bat_id'] ?? null), 'bowl' => $pl($m['best_bowl_id'] ?? null)];
    }
    $over = tournament_over($t);
    $pick = fn($l) => $l ? $l[0] : null;
    $bat = $leaders['runs'] ?? [];
    usort($bat, fn($a, $b) => [$b['runs'], $a['balls']] <=> [$a['runs'], $b['balls']]);   // tie: faster scorer
    return ['final' => $over, 'player' => $pick($mvp), 'batter' => $pick($bat), 'bowler' => $pick($leaders['wickets'] ?? []),
            'sixes' => $pick($leaders['sixes'] ?? []), 'matches' => array_reverse($match)];
}

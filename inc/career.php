<?php
/* Cric-Scorer v1.17 - career pages: match history with filters, full batting/bowling/fielding/captain stats,
   awards, badges, teams played for, MVP points, match notes & scorer log, profile views, captain/keeper in the XI */

const BALL_TYPES = ['leather', 'tennis', 'rubber', 'other'];
const LEVELS = [[0, 'Rookie'], [5, 'Aspirant'], [15, 'Regular'], [40, 'Star'], [100, 'Legend']];

function schema_v117(): void {
    if (!val("SHOW COLUMNS FROM match_players LIKE 'is_captain'")) db()->exec("ALTER TABLE match_players ADD COLUMN is_captain TINYINT(1) NOT NULL DEFAULT 0 AFTER bat_order, ADD COLUMN is_keeper TINYINT(1) NOT NULL DEFAULT 0 AFTER is_captain");
    if (!val("SHOW COLUMNS FROM matches LIKE 'ball_type'")) db()->exec("ALTER TABLE matches ADD COLUMN ball_type VARCHAR(12) NULL AFTER venue, ADD COLUMN bonus_json VARCHAR(100) NULL AFTER ball_type, ADD COLUMN round_name VARCHAR(60) NULL AFTER bonus_json");
    if (!val("SHOW COLUMNS FROM players LIKE 'views'")) db()->exec("ALTER TABLE players ADD COLUMN views INT NOT NULL DEFAULT 0 AFTER is_overseas, ADD COLUMN city VARCHAR(80) NULL AFTER views");
    if (!val("SHOW TABLES LIKE 'match_log'")) db()->exec("CREATE TABLE IF NOT EXISTS match_log (id INT AUTO_INCREMENT PRIMARY KEY, match_id INT NOT NULL, user_id INT NULL, who VARCHAR(100) NULL, text VARCHAR(255) NOT NULL,
        score VARCHAR(40) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (match_id), FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Scorer log - who changed what, at what score */
function match_log(array $m, string $text, ?array $u = null): void {
    $u = $u ?? current_user();
    $inn = latest_innings((int)$m['id']);
    $score = $inn ? $inn['runs'] . '/' . $inn['wickets'] . ' (' . overs_str((int)$inn['legal_balls'], (int)$m['balls_per_over']) . ')' : null;
    q('INSERT INTO match_log (match_id, user_id, who, text, score) VALUES (?,?,?,?,?)', [$m['id'], $u['id'] ?? null, $u['display_name'] ?? null, mb_substr($text, 0, 255), $score]);
}

function player_level(int $matches): string { $l = 'Rookie'; foreach (LEVELS as [$n, $name]) if ($matches >= $n) $l = $name; return $l; }

/* ---------- filters ---------- */

/** Extra SQL (on alias m) from the profile filters: year, tournament, team, overs, ball type, innings */
function career_filter(array $f, ?int $pid = null): array {
    $w = []; $p = [];
    if (!empty($f['year'])) { $w[] = 'YEAR(COALESCE(m.match_date, m.created_at))=?'; $p[] = (int)$f['year']; }
    if (!empty($f['tournament_id'])) { $w[] = 'm.tournament_id=?'; $p[] = (int)$f['tournament_id']; }
    if (!empty($f['team_id']) && $pid) { $w[] = 'EXISTS (SELECT 1 FROM match_players fx WHERE fx.match_id=m.id AND fx.player_id=? AND fx.team_id=?)'; $p[] = $pid; $p[] = (int)$f['team_id']; }
    if (!empty($f['opponent_id']) && $pid) { $w[] = 'EXISTS (SELECT 1 FROM match_players fx WHERE fx.match_id=m.id AND fx.player_id=? AND fx.team_id<>?) AND (m.team1_id=? OR m.team2_id=?)'; $p[] = $pid; $p[] = (int)$f['opponent_id']; $p[] = (int)$f['opponent_id']; $p[] = (int)$f['opponent_id']; }
    if (!empty($f['overs_min'])) { $w[] = 'm.overs>=?'; $p[] = (int)$f['overs_min']; }
    if (!empty($f['overs_max'])) { $w[] = 'm.overs<=?'; $p[] = (int)$f['overs_max']; }
    if (!empty($f['ball_type']) && in_array($f['ball_type'], BALL_TYPES, true)) { $w[] = 'm.ball_type=?'; $p[] = $f['ball_type']; }
    if (!empty($f['result']) && $pid) {
        if ($f['result'] === 'won') { $w[] = 'EXISTS (SELECT 1 FROM match_players fx WHERE fx.match_id=m.id AND fx.player_id=? AND fx.team_id=m.winner_team_id)'; $p[] = $pid; }
        if ($f['result'] === 'lost') { $w[] = "m.status='completed' AND m.winner_team_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM match_players fx WHERE fx.match_id=m.id AND fx.player_id=? AND fx.team_id=m.winner_team_id)"; $p[] = $pid; }
    }
    return [$w ? ' AND ' . implode(' AND ', $w) : '', $p];
}
/** Values the filter sheet can offer for this player */
function career_filter_options(int $pid, ?array $u): array {
    [$vs, $vp] = vis_sql($u, 'm');
    $years = array_map('intval', array_column(rows("SELECT DISTINCT YEAR(COALESCE(m.match_date, m.created_at)) y FROM matches m JOIN match_players mp ON mp.match_id=m.id WHERE mp.player_id=? AND $vs ORDER BY y DESC", array_merge([$pid], $vp)), 'y'));
    $tours = rows("SELECT DISTINCT t.id, t.name FROM tournaments t JOIN matches m ON m.tournament_id=t.id JOIN match_players mp ON mp.match_id=m.id WHERE mp.player_id=? AND $vs ORDER BY t.name", array_merge([$pid], $vp));
    $teams = rows("SELECT DISTINCT t.id, t.name, t.short_name, t.color FROM teams t JOIN match_players mp ON mp.team_id=t.id JOIN matches m ON m.id=mp.match_id WHERE mp.player_id=? AND $vs ORDER BY t.name", array_merge([$pid], $vp));
    $opp = rows("SELECT DISTINCT t.id, t.name, t.short_name, t.color FROM teams t JOIN matches m ON (m.team1_id=t.id OR m.team2_id=t.id) JOIN match_players mp ON mp.match_id=m.id AND mp.player_id=? AND mp.team_id<>t.id WHERE $vs ORDER BY t.name", array_merge([$pid], $vp));
    foreach ([&$tours, &$teams, &$opp] as &$l) foreach ($l as &$r) $r['id'] = (int)$r['id'];
    return ['years' => $years, 'tournaments' => $tours, 'teams' => $teams, 'opponents' => $opp, 'ball_types' => BALL_TYPES];
}

/* ---------- match history ---------- */

function player_matches(int $pid, ?array $u, array $f, int $page = 1, int $per = 12): array {
    [$vs, $vp] = vis_sql($u, 'm');
    [$fw, $fp] = career_filter($f, $pid);
    $off = max(0, $page - 1) * $per;
    $ms = rows("SELECT m.*, mp.team_id my_team, tr.name tour_name FROM matches m JOIN match_players mp ON mp.match_id=m.id AND mp.player_id=? LEFT JOIN tournaments tr ON tr.id=m.tournament_id WHERE $vs $fw ORDER BY COALESCE(m.match_date, m.created_at) DESC, m.id DESC LIMIT " . ($per + 1) . " OFFSET $off", array_merge([$pid], $vp, $fp));
    $more = count($ms) > $per; $ms = array_slice($ms, 0, $per);
    $out = match_summaries($ms);
    foreach ($ms as $i => $m) {
        $bat = row("SELECT COALESCE(SUM(b.runs_bat),0) r, COALESCE(SUM(CASE WHEN b.extra_type='wd' OR b.wicket_type IN ('retired hurt','retired out','timed out') THEN 0 ELSE 1 END),0) b, COALESCE(SUM(b.is_wicket=1 AND b.dismissed_id=?),0) o,
                     COUNT(DISTINCT CASE WHEN b.batsman_id=? THEN b.innings_id END) inns
                    FROM balls b JOIN innings i ON i.id=b.innings_id WHERE i.match_id=? AND i.is_super=0 AND (b.batsman_id=? OR b.dismissed_id=?)", [$pid, $pid, $m['id'], $pid, $pid]);
        $batted = (bool)val('SELECT 1 FROM innings_batters ib JOIN innings i ON i.id=ib.innings_id WHERE i.match_id=? AND ib.player_id=? AND i.is_super=0', [$m['id'], $pid]);
        $bowl = row("SELECT COALESCE(SUM(b.is_legal),0) balls, COALESCE(SUM(b.runs_bat + CASE WHEN b.extra_type IN ('wd','nb') THEN b.extra_runs ELSE 0 END),0) r,
                     COALESCE(SUM(b.is_wicket=1 AND b.wicket_type IN ('bowled','caught','lbw','stumped','hit wicket')),0) w
                    FROM balls b JOIN innings i ON i.id=b.innings_id WHERE i.match_id=? AND i.is_super=0 AND b.bowler_id=? AND (b.wicket_type IS NULL OR b.wicket_type NOT IN ('retired hurt','retired out','timed out'))", [$m['id'], $pid]);
        $out[$i]['tournament_name'] = $m['tour_name'];
        $out[$i]['me'] = [
            'team_id' => (int)$m['my_team'], 'round' => $m['round_name'],
            'bat' => $batted ? ['r' => (int)$bat['r'], 'b' => (int)$bat['b'], 'out' => (int)$bat['o'] > 0] : null,
            'bowl' => (int)$bowl['balls'] > 0 ? ['o' => overs_str((int)$bowl['balls'], (int)$m['balls_per_over']), 'r' => (int)$bowl['r'], 'w' => (int)$bowl['w']] : null,
            'won' => $m['winner_team_id'] ? (int)$m['winner_team_id'] === (int)$m['my_team'] : null,
            'pom' => (int)$m['player_of_match_id'] === $pid,
        ];
    }
    return ['matches' => $out, 'more' => $more, 'page' => $page];
}

/* ---------- full stats ---------- */

function player_career(int $pid, ?array $u, array $f = []): array {
    [$vs, $vp] = vis_sql($u, 'm');
    [$fw, $fp] = career_filter($f, $pid);
    $vs2 = "$vs$fw"; $vp2 = array_merge($vp, $fp);
    $base = player_stats($pid, $u, $f);
    $bat = $base['batting']; $bowl = $base['bowling'];
    // ducks, won/lost
    $bat['ducks'] = (int)val("SELECT COUNT(*) FROM innings_batters ib JOIN innings i ON i.id=ib.innings_id JOIN matches m ON m.id=i.match_id WHERE ib.player_id=? AND i.is_super=0 AND $vs2
        AND (SELECT COALESCE(SUM(runs_bat),0) FROM balls WHERE innings_id=i.id AND batsman_id=ib.player_id)=0 AND EXISTS (SELECT 1 FROM balls WHERE innings_id=i.id AND dismissed_id=ib.player_id AND is_wicket=1)", array_merge([$pid], $vp2));
    $wl = row("SELECT SUM(m.winner_team_id=mp.team_id) w, SUM(m.winner_team_id IS NOT NULL AND m.winner_team_id<>mp.team_id) l, SUM(m.status='completed' AND m.winner_team_id IS NULL) t
               FROM match_players mp JOIN matches m ON m.id=mp.match_id WHERE mp.player_id=? AND m.status='completed' AND $vs2", array_merge([$pid], $vp2));
    $bat['won'] = (int)$wl['w']; $bat['lost'] = (int)$wl['l']; $bat['tied'] = (int)$wl['t'];
    // simpler + correct: recompute maidens and the above with a per-innings pass
    $bowl['maidens'] = 0; $bowl['wd'] = 0; $bowl['nb'] = 0; $bowl['dots'] = 0; $bowl['f4'] = 0; $bowl['f6'] = 0;
    foreach (rows("SELECT b.innings_id, b.over_no, SUM(b.is_legal) l, SUM(b.runs_bat + CASE WHEN b.extra_type IN ('wd','nb') THEN b.extra_runs ELSE 0 END) c,
                   SUM(b.extra_type='wd') wd, SUM(b.extra_type='nb') nb, SUM(b.is_legal=1 AND b.runs_bat=0 AND b.extra_type IS NULL) dots, SUM(b.runs_bat=4) f4, SUM(b.runs_bat=6) f6, m.balls_per_over bpo
                   FROM balls b JOIN innings i ON i.id=b.innings_id JOIN matches m ON m.id=i.match_id
                   WHERE b.bowler_id=? AND i.is_super=0 AND (b.wicket_type IS NULL OR b.wicket_type NOT IN ('retired hurt','retired out','timed out')) AND $vs2 GROUP BY b.innings_id, b.over_no, m.balls_per_over", array_merge([$pid], $vp2)) as $o) {
        if ((int)$o['l'] === (int)$o['bpo'] && (int)$o['c'] === 0) $bowl['maidens']++;
        $bowl['wd'] += (int)$o['wd']; $bowl['nb'] += (int)$o['nb']; $bowl['dots'] += (int)$o['dots']; $bowl['f4'] += (int)$o['f4']; $bowl['f6'] += (int)$o['f6'];
    }
    $bowl['sr'] = $bowl['wkts'] > 0 ? round($bowl['balls'] / $bowl['wkts'], 2) : null;
    $bowl['mat'] = (int)val("SELECT COUNT(DISTINCT i.match_id) FROM balls b JOIN innings i ON i.id=b.innings_id JOIN matches m ON m.id=i.match_id WHERE b.bowler_id=? AND i.is_super=0 AND $vs2", array_merge([$pid], $vp2));
    // fielding
    $fld = row("SELECT COALESCE(SUM(b.wicket_type='caught' AND mp.is_keeper=0),0) catches, COALESCE(SUM(b.wicket_type='caught' AND mp.is_keeper=1),0) cb,
                COALESCE(SUM(b.wicket_type='run out'),0) ro, COALESCE(SUM(b.wicket_type='stumped'),0) st
                FROM balls b JOIN innings i ON i.id=b.innings_id JOIN matches m ON m.id=i.match_id LEFT JOIN match_players mp ON mp.match_id=m.id AND mp.player_id=b.fielder_id
                WHERE b.fielder_id=? AND b.is_wicket=1 AND i.is_super=0 AND $vs2", array_merge([$pid], $vp2));
    $fx = row("SELECT COALESCE(SUM(b.drop_id=?),0) drops, COALESCE(SUM(CASE WHEN b.field_id=? AND b.field_runs>0 THEN b.field_runs ELSE 0 END),0) saved, COALESCE(SUM(CASE WHEN b.field_id=? AND b.field_runs<0 THEN -b.field_runs ELSE 0 END),0) missed
               FROM balls b JOIN innings i ON i.id=b.innings_id JOIN matches m ON m.id=i.match_id WHERE (b.drop_id=? OR b.field_id=?) AND i.is_super=0 AND $vs2", array_merge([$pid, $pid, $pid, $pid, $pid], $vp2));
    $fielding = ['mat' => $bat['mat'], 'catches' => (int)$fld['catches'], 'caught_behind' => (int)$fld['cb'], 'run_outs' => (int)$fld['ro'], 'stumpings' => (int)$fld['st'],
                 'drops' => (int)$fx['drops'], 'saved' => (int)$fx['saved'], 'missed' => (int)$fx['missed']];
    // captain
    $cap = row("SELECT COUNT(*) mat, SUM(m.winner_team_id=mp.team_id) w, SUM(m.winner_team_id IS NOT NULL AND m.winner_team_id<>mp.team_id) l, SUM(m.toss_winner_id=mp.team_id) toss
                FROM match_players mp JOIN matches m ON m.id=mp.match_id WHERE mp.player_id=? AND mp.is_captain=1 AND m.status IN ('live','completed') AND $vs2", array_merge([$pid], $vp2));
    $captain = ['mat' => (int)$cap['mat'], 'won' => (int)$cap['w'], 'lost' => (int)$cap['l'], 'toss_won' => (int)$cap['toss']];
    $captain['win_pct'] = $captain['mat'] ? round($captain['won'] * 100 / $captain['mat']) : null;
    return ['batting' => $bat, 'bowling' => $bowl, 'fielding' => $fielding, 'captain' => $captain, 'pom' => $base['pom'], 'insights' => $base['insights'], 'level' => player_level($bat['mat'])];
}

/* ---------- awards & badges ---------- */

function player_awards(int $pid, ?array $u): array {
    [$vs, $vp] = vis_sql($u, 'm');
    $out = [];
    foreach (rows("SELECT m.*, t1.short_name t1, t2.short_name t2, tr.name tour FROM matches m JOIN teams t1 ON t1.id=m.team1_id JOIN teams t2 ON t2.id=m.team2_id LEFT JOIN tournaments tr ON tr.id=m.tournament_id
                   WHERE (m.player_of_match_id=? OR m.best_bat_id=? OR m.best_bowl_id=?) AND $vs ORDER BY COALESCE(m.match_date, m.created_at) DESC", array_merge([$pid, $pid, $pid], $vp)) as $m) {
        $perf = player_match_figures((int)$m['id'], $pid, (int)$m['balls_per_over']);
        $kinds = [];
        if ((int)$m['player_of_match_id'] === $pid) $kinds[] = 'Player of the Match';
        if ((int)$m['best_bat_id'] === $pid) $kinds[] = 'Best batter';
        if ((int)$m['best_bowl_id'] === $pid) $kinds[] = 'Best bowler';
        $out[] = ['match_id' => (int)$m['id'], 'kinds' => $kinds, 'title' => "{$m['t1']} vs {$m['t2']}", 'tour' => $m['tour'] ?: ($m['title'] ?: 'Friendly'), 'date' => $m['match_date'], 'bat' => $perf['bat'], 'bowl' => $perf['bowl']];
    }
    return $out;
}
function player_match_figures(int $mid, int $pid, int $bpo): array {
    $bat = row("SELECT COALESCE(SUM(b.runs_bat),0) r, COALESCE(SUM(CASE WHEN b.extra_type='wd' OR b.wicket_type IN ('retired hurt','retired out','timed out') THEN 0 ELSE 1 END),0) b, COALESCE(SUM(b.runs_bat=4),0) f4, COALESCE(SUM(b.runs_bat=6),0) f6
                FROM balls b JOIN innings i ON i.id=b.innings_id WHERE i.match_id=? AND i.is_super=0 AND b.batsman_id=?", [$mid, $pid]);
    $out = (bool)val('SELECT 1 FROM balls b JOIN innings i ON i.id=b.innings_id WHERE i.match_id=? AND b.dismissed_id=? AND b.is_wicket=1', [$mid, $pid]);
    $batted = (bool)val('SELECT 1 FROM innings_batters ib JOIN innings i ON i.id=ib.innings_id WHERE i.match_id=? AND ib.player_id=? AND i.is_super=0', [$mid, $pid]);
    $bowl = row("SELECT COALESCE(SUM(b.is_legal),0) balls, COALESCE(SUM(b.runs_bat + CASE WHEN b.extra_type IN ('wd','nb') THEN b.extra_runs ELSE 0 END),0) r,
                 COALESCE(SUM(b.is_wicket=1 AND b.wicket_type IN ('bowled','caught','lbw','stumped','hit wicket')),0) w
                FROM balls b JOIN innings i ON i.id=b.innings_id WHERE i.match_id=? AND i.is_super=0 AND b.bowler_id=? AND (b.wicket_type IS NULL OR b.wicket_type NOT IN ('retired hurt','retired out','timed out'))", [$mid, $pid]);
    return ['bat' => $batted ? ['r' => (int)$bat['r'], 'b' => (int)$bat['b'], 'f4' => (int)$bat['f4'], 'f6' => (int)$bat['f6'], 'out' => $out] : null,
            'bowl' => (int)$bowl['balls'] ? ['o' => overs_str((int)$bowl['balls'], $bpo), 'r' => (int)$bowl['r'], 'w' => (int)$bowl['w']] : null];
}

function player_badges(int $pid, ?array $u): array {
    [$vs, $vp] = vis_sql($u, 'm');
    $b = ['fifty' => 0, 'hundred' => 0, 'three_w' => 0, 'five_w' => 0, 'hat_trick' => 0, 'maiden' => 0, 'six_hitter' => 0, 'pom' => 0, 'fifty_stand' => 0, 'safe_hands' => 0, 'duck' => 0, 'quick_fire' => 0];
    foreach (rows("SELECT i.id, i.innings_no, m.balls_per_over bpo FROM innings i JOIN matches m ON m.id=i.match_id WHERE i.is_super=0 AND $vs AND EXISTS (SELECT 1 FROM balls b WHERE b.innings_id=i.id AND (b.batsman_id=? OR b.bowler_id=? OR b.fielder_id=? OR b.non_striker_id=?))", array_merge($vp, [$pid, $pid, $pid, $pid])) as $inn) {
        $balls = rows('SELECT * FROM balls WHERE innings_id=? ORDER BY seq', [$inn['id']]);
        $r = 0; $bf = 0; $six = 0; $w = 0; $streak = 0; $catches = 0; $overs = []; $part = 0; $partIn = false; $out = false;
        foreach ($balls as $x) {
            $nd = in_array($x['wicket_type'], NON_DELIVERY, true);
            if ((int)$x['batsman_id'] === $pid && !$nd) { $r += (int)$x['runs_bat']; if ($x['extra_type'] !== 'wd') $bf++; if ((int)$x['runs_bat'] === 6) $six++; }
            if ((int)$x['dismissed_id'] === $pid && $x['is_wicket']) $out = true;
            if ((int)$x['bowler_id'] === $pid && !$nd) {
                $bw = $x['is_wicket'] && in_array($x['wicket_type'], BOWLER_WICKETS, true);
                if ($bw) { $w++; $streak++; if ($streak === 3) $b['hat_trick']++; } elseif ($x['is_legal']) $streak = 0;
                $k = (int)$x['over_no']; $overs[$k] = ['l' => ($overs[$k]['l'] ?? 0) + (int)$x['is_legal'], 'c' => ($overs[$k]['c'] ?? 0) + (int)$x['runs_bat'] + (in_array($x['extra_type'], ['wd', 'nb'], true) ? (int)$x['extra_runs'] : 0)];
            }
            if ((int)$x['fielder_id'] === $pid && $x['is_wicket'] && $x['wicket_type'] === 'caught') $catches++;
            // partnerships involving the player
            $inPart = in_array($pid, [(int)$x['batsman_id'], (int)$x['non_striker_id']], true);
            if ($inPart) { $part += (int)$x['total_runs']; $partIn = true; }
            if ($x['is_wicket']) { if ($partIn && $part >= 50) $b['fifty_stand']++; $part = 0; $partIn = false; }
        }
        if ($partIn && $part >= 50) $b['fifty_stand']++;
        if ($r >= 100) $b['hundred']++; elseif ($r >= 50) $b['fifty']++;
        if ($r >= 30 && $bf > 0 && $r * 100 / $bf >= 150) $b['quick_fire']++;
        if ($r === 0 && $out && $bf > 0) $b['duck']++;
        if ($six >= 3) $b['six_hitter']++;
        if ($w >= 5) $b['five_w']++; elseif ($w >= 3) $b['three_w']++;
        foreach ($overs as $o) if ($o['l'] === (int)$inn['bpo'] && $o['c'] === 0) $b['maiden']++;
        if ($catches >= 2) $b['safe_hands']++;
    }
    $b['pom'] = (int)val("SELECT COUNT(*) FROM matches m WHERE m.player_of_match_id=? AND $vs", array_merge([$pid], $vp));
    $meta = ['fifty' => ['⭐', 'Fifty', '50+ in an innings'], 'hundred' => ['💯', 'Century', '100+ in an innings'], 'quick_fire' => ['⚡', 'Quick fire', '30+ at a strike rate of 150+'],
             'six_hitter' => ['🚀', 'Six machine', '3+ sixes in an innings'], 'fifty_stand' => ['🤝', '50 stand', '50+ partnership'], 'three_w' => ['🎯', '3-wicket haul', '3 or 4 wickets in an innings'],
             'five_w' => ['🔥', 'Five-for', '5+ wickets in an innings'], 'hat_trick' => ['🎩', 'Hat-trick', '3 wickets in 3 balls'], 'maiden' => ['🧱', 'Maiden', 'an over with no runs'],
             'safe_hands' => ['🧤', 'Safe hands', '2+ catches in an innings'], 'pom' => ['🏆', 'Player of the Match', ''], 'duck' => ['🦆', 'Duck', 'out for 0 - it happens to everyone']];
    $out = [];
    foreach ($meta as $k => [$icon, $name, $how]) $out[] = ['key' => $k, 'icon' => $icon, 'name' => $name, 'how' => $how, 'count' => $b[$k]];
    return $out;
}

/** Teams the player has turned out for (by match) */
function player_teams(int $pid, ?array $u): array {
    [$vs, $vp] = vis_sql($u, 'm');
    $list = rows("SELECT t.id, t.name, t.short_name, t.color, MIN(COALESCE(m.match_date, m.created_at)) since, COUNT(*) played,
                  SUM(m.winner_team_id=mp.team_id) won, SUM(m.winner_team_id IS NOT NULL AND m.winner_team_id<>mp.team_id) lost
                  FROM match_players mp JOIN matches m ON m.id=mp.match_id JOIN teams t ON t.id=mp.team_id WHERE mp.player_id=? AND m.status IN ('live','completed') AND $vs GROUP BY t.id, t.name, t.short_name, t.color ORDER BY since", array_merge([$pid], $vp));
    foreach ($list as &$r) { $r['id'] = (int)$r['id']; $r['played'] = (int)$r['played']; $r['won'] = (int)$r['won']; $r['lost'] = (int)$r['lost']; }
    return $list;
}

/* ---------- MVP points ---------- */

/** Batting + bowling + fielding points for every player in the match (published formula, see README) */
function mvp_points(int $matchId): array {
    $m = load_match($matchId);
    $bpo = (int)$m['balls_per_over'];
    $names = name_map($matchId);
    $teamOf = [];
    foreach (rows('SELECT player_id, team_id FROM match_players WHERE match_id=?', [$matchId]) as $r) $teamOf[(int)$r['player_id']] = (int)$r['team_id'];
    $P = [];
    $get = function ($pid) use (&$P, $names, $teamOf) { if (!isset($P[$pid])) $P[$pid] = ['id' => $pid, 'name' => $names[$pid] ?? '?', 'team_id' => $teamOf[$pid] ?? null, 'bat' => 0.0, 'bowl' => 0.0, 'field' => 0.0, 'r' => 0, 'b' => 0, 'w' => 0, 'rc' => 0, 'balls' => 0]; return $pid; };
    foreach (rows('SELECT * FROM innings WHERE match_id=? AND is_super=0', [$matchId]) as $inn) {
        $bat = []; $bowl = []; $overs = [];
        foreach (rows('SELECT * FROM balls WHERE innings_id=? ORDER BY seq', [$inn['id']]) as $x) {
            $nd = in_array($x['wicket_type'], NON_DELIVERY, true);
            if (!$nd) {
                $p = (int)$x['batsman_id']; $bat[$p] = $bat[$p] ?? ['r' => 0, 'b' => 0, 'f4' => 0, 'f6' => 0, 'out' => false];
                $bat[$p]['r'] += (int)$x['runs_bat']; if ($x['extra_type'] !== 'wd') $bat[$p]['b']++; if ((int)$x['runs_bat'] === 4) $bat[$p]['f4']++; if ((int)$x['runs_bat'] === 6) $bat[$p]['f6']++;
                $w = (int)$x['bowler_id']; $bowl[$w] = $bowl[$w] ?? ['balls' => 0, 'r' => 0, 'w' => 0, 'dots' => 0];
                $c = (int)$x['runs_bat'] + (in_array($x['extra_type'], ['wd', 'nb'], true) ? (int)$x['extra_runs'] : 0);
                $bowl[$w]['balls'] += (int)$x['is_legal']; $bowl[$w]['r'] += $c; if ($x['is_legal'] && $c === 0) $bowl[$w]['dots']++;
                if ($x['is_wicket'] && in_array($x['wicket_type'], BOWLER_WICKETS, true)) $bowl[$w]['w']++;
                $k = $w . ':' . $x['over_no']; $overs[$k] = ['l' => ($overs[$k]['l'] ?? 0) + (int)$x['is_legal'], 'c' => ($overs[$k]['c'] ?? 0) + $c, 'w' => $w];
            }
            if ($x['is_wicket'] && $x['dismissed_id'] && isset($bat[(int)$x['dismissed_id']])) $bat[(int)$x['dismissed_id']]['out'] = true;
            if ($x['is_wicket'] && $x['fielder_id']) { $f = $get((int)$x['fielder_id']); $P[$f]['field'] += $x['wicket_type'] === 'stumped' ? 10 : 8; }
            if ($x['drop_id']) { $f = $get((int)$x['drop_id']); $P[$f]['field'] -= 5; }
            if ($x['field_id'] && (int)$x['field_runs'] !== 0) { $f = $get((int)$x['field_id']); $P[$f]['field'] += (int)$x['field_runs']; }
        }
        foreach ($bat as $pid => $s) {
            $p = $get($pid); $pts = $s['r'] + $s['f4'] + 2 * $s['f6'];
            if ($s['r'] >= 100) $pts += 25; elseif ($s['r'] >= 50) $pts += 10; elseif ($s['r'] >= 30) $pts += 5;
            if ($s['b'] >= 10) $pts += max(-10, min(10, ($s['r'] * 100 / $s['b'] - 100) / 10));
            if ($s['r'] === 0 && $s['out']) $pts -= 2;
            $P[$p]['bat'] += $pts; $P[$p]['r'] += $s['r']; $P[$p]['b'] += $s['b'];
        }
        foreach ($bowl as $pid => $s) {
            $p = $get($pid); $pts = $s['w'] * 20 + $s['dots'] * 0.5;
            if ($s['w'] >= 5) $pts += 25; elseif ($s['w'] >= 3) $pts += 10;
            if ($s['balls'] >= $bpo) $pts += max(-15, min(15, (6 - $s['r'] * $bpo / $s['balls']) * 3));   // v1.30: economy counts from one full over (short-format games)
            $P[$p]['bowl'] += $pts; $P[$p]['w'] += $s['w']; $P[$p]['rc'] += $s['r']; $P[$p]['balls'] += $s['balls'];
        }
        foreach ($overs as $o) if ($o['l'] === $bpo && $o['c'] === 0) { $p = $get($o['w']); $P[$p]['bowl'] += 5; }
    }
    $list = array_values($P);
    foreach ($list as &$p) { $p['bat'] = round($p['bat'], 1); $p['bowl'] = round($p['bowl'], 1); $p['field'] = round($p['field'], 1); $p['total'] = round($p['bat'] + $p['bowl'] + $p['field'], 1); }
    usort($list, fn($a, $b) => $b['total'] <=> $a['total']);
    return $list;
}

/** Tournament MVP table: points summed over completed matches */
function tournament_mvp(int $tid, int $limit = 10): array {
    $sum = [];
    foreach (rows("SELECT id FROM matches WHERE tournament_id=? AND status='completed'", [$tid]) as $m) {
        foreach (mvp_points((int)$m['id']) as $p) {
            $k = $p['id']; $sum[$k] = $sum[$k] ?? ['id' => $k, 'name' => $p['name'], 'team_id' => $p['team_id'], 'bat' => 0, 'bowl' => 0, 'field' => 0, 'total' => 0, 'mats' => 0];
            $sum[$k]['bat'] += $p['bat']; $sum[$k]['bowl'] += $p['bowl']; $sum[$k]['field'] += $p['field']; $sum[$k]['total'] += $p['total']; $sum[$k]['mats']++;
        }
    }
    $list = array_values($sum);
    foreach ($list as &$p) foreach (['bat', 'bowl', 'field', 'total'] as $k) $p[$k] = round($p[$k], 1);
    usort($list, fn($a, $b) => $b['total'] <=> $a['total']);
    $teams = [];
    foreach (rows('SELECT id, short_name, color FROM teams WHERE id IN (SELECT team_id FROM tournament_teams WHERE tournament_id=?)', [$tid]) as $t) $teams[(int)$t['id']] = $t;
    foreach ($list as &$p) { $p['team'] = $teams[$p['team_id']]['short_name'] ?? ''; $p['color'] = $teams[$p['team_id']]['color'] ?? '#0d4a36'; }
    return array_slice($list, 0, $limit);
}

/* ---------- match notes (auto milestones) & timings ---------- */

function match_notes(array $m, array $inns, array $names): array {
    $notes = []; $bpo = (int)$m['balls_per_over']; $timings = [];
    $prevEnd = null;
    foreach ($inns as $inn) {
        $team = val('SELECT name FROM teams WHERE id=?', [$inn['batting_team_id']]);
        $balls = rows('SELECT * FROM balls WHERE innings_id=? ORDER BY seq', [$inn['id']]);
        if (!$balls) continue;
        $start = $balls[0]['created_at']; $end = end($balls)['created_at'];
        if ($prevEnd) { $gap = (int)round((strtotime($start) - strtotime($prevEnd)) / 60); $notes[] = ['at' => $start, 'text' => "Innings break: $gap min"]; }
        $notes[] = ['at' => $start, 'text' => (!empty($inn['is_super']) ? 'Super over' : 'Innings') . ' started at ' . date('g:i A', strtotime($start)) . " - $team batting"];
        $score = penalty_runs_for_innings((int)$inn['id']); $legal = 0; $extras = 0; $nextTeam = 50; $bat = []; $part = 0; $partBalls = 0; $partPl = []; $partW = 1; $bowlW = []; $wk = 0; $partNoted = [];
        foreach ($balls as $x) {
            $nd = in_array($x['wicket_type'], NON_DELIVERY, true);
            $score += (int)$x['total_runs']; $legal += (int)$x['is_legal']; $extras += (int)$x['extra_runs'];
            $p = (int)$x['batsman_id'];
            if (!$nd) {
                $bat[$p] = $bat[$p] ?? ['r' => 0, 'b' => 0, 'f4' => 0, 'f6' => 0, 'm' => 0];
                $bat[$p]['r'] += (int)$x['runs_bat']; if ($x['extra_type'] !== 'wd') $bat[$p]['b']++; if ((int)$x['runs_bat'] === 4) $bat[$p]['f4']++; if ((int)$x['runs_bat'] === 6) $bat[$p]['f6']++;
                foreach ([100, 50] as $ms) if ($bat[$p]['r'] >= $ms && $bat[$p]['m'] < $ms) { $bat[$p]['m'] = $ms; $notes[] = ['at' => $x['created_at'], 'text' => ($names[$p] ?? '?') . ": $ms off {$bat[$p]['b']} balls ({$bat[$p]['f4']} x 4, {$bat[$p]['f6']} x 6)"]; break; }
                $part += (int)$x['total_runs']; $partBalls += (int)$x['is_legal'];
                foreach ([(int)$x['batsman_id'], (int)$x['non_striker_id']] as $q) if ($q) $partPl[$q] = true;
                if ($part >= 50 && !isset($partNoted[$partW])) { $partNoted[$partW] = true; $pl = array_keys($partPl); $desc = implode(', ', array_map(fn($q) => ($names[$q] ?? '?') . ' ' . ($bat[$q]['r'] ?? 0) . '(' . ($bat[$q]['b'] ?? 0) . ')', $pl)); $notes[] = ['at' => $x['created_at'], 'text' => ordinal($partW) . " wicket: 50 runs in $partBalls balls ($desc)"]; }
                $w = (int)$x['bowler_id'];
                if ($x['is_wicket'] && in_array($x['wicket_type'], BOWLER_WICKETS, true)) { $bowlW[$w] = ($bowlW[$w] ?? 0) + 1; if (in_array($bowlW[$w], [3, 5], true)) $notes[] = ['at' => $x['created_at'], 'text' => ($names[$w] ?? '?') . ': ' . $bowlW[$w] . ' wickets']; }
            }
            while ($score >= $nextTeam) { $notes[] = ['at' => $x['created_at'], 'text' => "$team: $nextTeam runs in " . overs_str($legal, $bpo) . " overs, Extras $extras"]; $nextTeam += 50; }
            if ($x['is_wicket']) { $wk++; $part = 0; $partBalls = 0; $partPl = []; $partW = $wk + 1; }
        }
        $top = $bat; uasort($top, fn($a, $b) => $b['r'] <=> $a['r']);
        $notOut = array_filter(array_keys($top), fn($q) => in_array($q, [(int)$inn['striker_id'], (int)$inn['non_striker_id']], true));
        $endText = $inn['status'] === 'completed' ? (((int)$inn['innings_no'] === 1 || ((int)$inn['innings_no'] < 4 && is_test($m))) ? 'Innings break: ' : 'End: ') . "$team - $score/$wk in " . overs_str($legal, $bpo) . ' overs' : null;
        if ($endText) { $notes[] = ['at' => $end, 'text' => $endText . ($notOut ? ' (' . implode(', ', array_map(fn($q) => ($names[$q] ?? '?') . ' ' . $bat[$q]['r'], $notOut)) . ')' : '')]; $notes[] = ['at' => $end, 'text' => 'Innings ended at ' . date('g:i A', strtotime($end))]; }
        $prevEnd = $end;
        $timings[] = ['no' => (int)$inn['innings_no'], 'mins' => (int)round((strtotime($end) - strtotime($start)) / 60), 'from' => date('H:i', strtotime($start)), 'to' => date('H:i', strtotime($end))];
    }
    if ($m['status'] === 'completed' && $prevEnd) $notes[] = ['at' => $prevEnd, 'text' => 'Match ended at ' . date('g:i A', strtotime($prevEnd)) . ($m['result_text'] ? ' - ' . $m['result_text'] : '')];
    return ['notes' => $notes, 'timings' => $timings];
}
function ordinal(int $n): string { $s = ['th', 'st', 'nd', 'rd']; $v = $n % 100; return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]); }

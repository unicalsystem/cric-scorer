<?php
/**
 * Match setup list - v1.43 (administrator)
 * Matches that have not started, with what is ready: scorer, playing teams, toss. The admin opens a match to
 * save any of them ahead of time (the match page has the same setup screen), or names a scorer straight from the list.
 */
declare(strict_types=1);

function admin_setup_list(array $in): array {
    $where = ["m.status='scheduled'", 'NOT EXISTS (SELECT 1 FROM innings i WHERE i.match_id=m.id)'];
    $p = [];
    $tid = (int)($in['tournament_id'] ?? 0);
    if ($tid === -1) $where[] = 'm.tournament_id IS NULL';
    elseif ($tid > 0) { $where[] = 'm.tournament_id=?'; $p[] = $tid; }
    $q = trim((string)($in['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(t1.name LIKE ? OR t2.name LIKE ? OR m.title LIKE ? OR tr.name LIKE ? OR m.id=?)';
        $like = '%' . $q . '%';
        array_push($p, $like, $like, $like, $like, ctype_digit($q) ? (int)$q : 0);
    }
    $list = rows("SELECT m.id, m.title, m.round_name, m.match_date, m.venue, m.players_per_side, m.toss_winner_id, m.toss_decision, m.scorer_id, m.team1_id, m.team2_id, m.overs,
            t1.name t1, t1.short_name s1, t2.name t2, t2.short_name s2, tr.name tournament,
            su.display_name scorer_name, su.username scorer_user, ow.display_name owner_name,
            (SELECT COUNT(*) FROM match_players mp WHERE mp.match_id=m.id AND mp.team_id=m.team1_id) n1,
            (SELECT COUNT(*) FROM match_players mp WHERE mp.match_id=m.id AND mp.team_id=m.team2_id) n2
        FROM matches m JOIN teams t1 ON t1.id=m.team1_id JOIN teams t2 ON t2.id=m.team2_id
        LEFT JOIN tournaments tr ON tr.id=m.tournament_id LEFT JOIN users su ON su.id=m.scorer_id LEFT JOIN users ow ON ow.id=m.owner_id
        WHERE " . implode(' AND ', $where) . ' ORDER BY (m.match_date IS NULL), m.match_date, m.id LIMIT 300', $p);
    $out = [];
    foreach ($list as $r) {
        $toss = null;
        if ($r['toss_winner_id']) $toss = ((int)$r['toss_winner_id'] === (int)$r['team1_id'] ? $r['s1'] : $r['s2']) . ' · ' . ($r['toss_decision'] === 'bat' ? 'bat first' : 'bowl first');
        $out[] = ['id' => (int)$r['id'], 'title' => $r['title'], 'round' => $r['round_name'], 'date' => $r['match_date'], 'venue' => $r['venue'], 'tournament' => $r['tournament'],
            't1' => $r['t1'], 's1' => $r['s1'], 't2' => $r['t2'], 's2' => $r['s2'], 'overs' => (int)$r['overs'], 'pps' => (int)$r['players_per_side'],
            'n1' => (int)$r['n1'], 'n2' => (int)$r['n2'], 'teams_done' => $r['n1'] > 0 && $r['n2'] > 0, 'toss' => $toss,
            'scorer' => $r['scorer_id'] ? ['name' => $r['scorer_name'], 'username' => $r['scorer_user']] : null, 'owner' => $r['owner_name'],
            'ready' => $r['n1'] > 0 && $r['n2'] > 0 && $toss !== null];
    }
    $tours = rows("SELECT tr.id, tr.name, COUNT(*) n FROM matches m JOIN tournaments tr ON tr.id=m.tournament_id WHERE m.status='scheduled' GROUP BY tr.id, tr.name ORDER BY tr.id DESC");
    return ['matches' => $out, 'tournaments' => $tours];
}

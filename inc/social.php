<?php
/* Cric-Scorer - follow players, private profiles, private matches */

/** SQL condition: which matches this viewer may see. Admin (parent) sees everything. */
function vis_sql(?array $u, string $a = 'matches'): array {
    if ($u && !empty($u['is_admin'])) return ['1=1', []];
    if (!$u) return ["$a.visibility='public'", []];
    return ["($a.visibility='public' OR $a.owner_id=?)", [(int)$u['id']]];
}
function can_view_match(array $m, ?array $u): bool {
    return ($m['visibility'] ?? 'public') === 'public' || ($u && (!empty($u['is_admin']) || (int)$m['owner_id'] === (int)$u['id']));
}
/** Can the viewer see this person at all? (private players are invisible to others) */
function can_view_user(array $target, ?array $u): bool {
    return empty($target['is_private']) || ($u && ((int)$u['id'] === (int)$target['id'] || !empty($u['is_admin'])));
}
function person(array $r): array {
    return ['id' => (int)$r['id'], 'username' => $r['username'], 'display_name' => $r['display_name'], 'avatar' => $r['avatar_url'] ?? null,
            'avatar_preset' => $r['avatar_preset'] ?? null, 'role' => $r['role'] ?? null, 'is_private' => !empty($r['is_private'])];
}
/** SQL condition hiding private players from everyone but themselves and the admin */
function people_vis(array $u, string $a = 'u'): array {
    if (!empty($u['is_admin'])) return ['1=1', []];
    return ["($a.is_private=0 OR $a.id=?)", [(int)$u['id']]];
}
function follow_counts(int $uid, array $viewer): array {
    [$pv, $pp] = people_vis($viewer);
    return [
        'followers' => (int)val("SELECT COUNT(*) FROM follows f JOIN users u ON u.id=f.follower_id WHERE f.followee_id=? AND $pv", array_merge([$uid], $pp)),
        'following' => (int)val("SELECT COUNT(*) FROM follows f JOIN users u ON u.id=f.followee_id WHERE f.follower_id=? AND $pv", array_merge([$uid], $pp)),
    ];
}

function users_search(array $u, array $in): array {
    // v1.38: every registered player, 50 at a time (Load more), most-followed first; optional team filter
    $q = trim(str_in($in, 'q', 60));
    $page = max(0, (int)($in['page'] ?? 0)); $per = 50;
    $team = (int)($in['team_id'] ?? 0);
    $where = ['u.id <> ?']; $params = [(int)$u['id']];
    if (empty($u['is_admin'])) $where[] = 'u.is_private = 0';
    // a full mobile number finds the player who has it (exact match only - no browsing by partial numbers)
    $mob = preg_match('/^[+\d][\d\s\-()]{8,}$/', $q) ? normalize_mobile($q) : null;
    if ($mob) { $where[] = 'u.mobile = ?'; $params[] = $mob; }
    elseif ($q !== '') { $where[] = '(LOCATE(?, u.username) > 0 OR LOCATE(?, u.display_name) > 0)'; $params[] = $q; $params[] = $q; }
    if ($team) { $where[] = 'p.team_id = ?'; $params[] = $team; }
    $w = implode(' AND ', $where);
    $total = (int)val("SELECT COUNT(*) FROM users u LEFT JOIN players p ON p.id=u.player_id WHERE $w", $params);
    $list = rows("SELECT u.*, t.short_name team_short, t.name team_name, t.id team_id, t.color team_color,
                    (SELECT COUNT(*) FROM follows f JOIN users fu ON fu.id=f.follower_id WHERE f.followee_id=u.id AND fu.is_private=0) followers
                  FROM users u LEFT JOIN players p ON p.id=u.player_id LEFT JOIN teams t ON t.id=p.team_id
                  WHERE $w ORDER BY followers DESC, u.display_name LIMIT $per OFFSET " . ($page * $per), $params);
    $teams = $q === '' && !$page ? rows("SELECT t.id, t.name, t.short_name, COUNT(*) n FROM users u JOIN players p ON p.id=u.player_id JOIN teams t ON t.id=p.team_id
                                         WHERE u.id <> ?" . (empty($u['is_admin']) ? ' AND u.is_private=0' : '') . " GROUP BY t.id, t.name, t.short_name HAVING n > 1 ORDER BY t.name", [(int)$u['id']]) : [];
    if (!$list) return ['users' => [], 'total' => $total, 'per' => $per, 'teams' => $teams];
    $ids = array_map(fn($r) => (int)$r['id'], $list);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $mine = [];
    foreach (rows("SELECT followee_id FROM follows WHERE follower_id = ? AND followee_id IN ($ph)", array_merge([(int)$u['id']], $ids)) as $f) $mine[(int)$f['followee_id']] = true;
    $out = array_map(fn($r) => person($r) + ['following' => isset($mine[(int)$r['id']]), 'followers' => (int)$r['followers'], 'joined' => empty($r['placeholder']),
        'team' => $r['team_short'] ? ['id' => (int)$r['team_id'], 'short_name' => $r['team_short'], 'name' => $r['team_name'], 'color' => $r['team_color']] : null], $list);
    return ['users' => $out, 'total' => $total, 'per' => $per, 'teams' => $teams];
}

function user_profile(array $u, array $in): array {
    $t = row('SELECT * FROM users WHERE id=?', [int_in($in, 'id')]);
    if (!$t || !can_view_user($t, $u)) fail('This player keeps their profile private 🔒', 403);
    $me = (int)$t['id'] === (int)$u['id'];
    [$vs, $vp] = vis_sql($u, 'm');
    $matches = match_summaries(rows("SELECT m.* FROM matches m WHERE m.owner_id=? AND $vs ORDER BY m.updated_at DESC LIMIT 10", array_merge([$t['id']], $vp)));
    $xp = function_exists('academy_user_xp') ? academy_user_xp((int)$t['id']) : 0;
    return [
        'user' => person($t) + ['joined' => $t['created_at'], 'is_admin' => (bool)$t['is_admin'], 'verified' => !empty($t['mobile_verified_at']), 'academies' => user_academies((int)$t['id'])] + cricket_profile($t),
        'player_stats' => !empty($t['player_id']) ? player_stats((int)$t['player_id'], $u) : null,
        'me' => $me,
        'counts' => follow_counts((int)$t['id'], $u),
        'following' => !$me && (bool)val('SELECT 1 FROM follows WHERE follower_id=? AND followee_id=?', [$u['id'], $t['id']]),
        'follows_me' => !$me && (bool)val('SELECT 1 FROM follows WHERE follower_id=? AND followee_id=?', [$t['id'], $u['id']]),
        'stats' => ['matches' => (int)val("SELECT COUNT(*) FROM matches m WHERE m.owner_id=? AND $vs", array_merge([$t['id']], $vp))],
        'academy' => function_exists('academy_rank') ? ['xp' => $xp, 'rank' => academy_rank($xp)] : null,
        'matches' => $matches,
    ];
}

function follow_list(array $u, array $in): array {
    $t = row('SELECT * FROM users WHERE id=?', [int_in($in, 'id', (int)$u['id'])]);
    if (!$t || !can_view_user($t, $u)) fail('This player keeps their profile private 🔒', 403);
    $type = ($in['type'] ?? '') === 'following' ? 'following' : 'followers';
    [$pv, $pp] = people_vis($u);
    $join = $type === 'followers' ? 'u.id=f.follower_id WHERE f.followee_id=?' : 'u.id=f.followee_id WHERE f.follower_id=?';
    $list = rows("SELECT u.*, f.created_at since, (SELECT 1 FROM follows x WHERE x.follower_id=? AND x.followee_id=u.id) i_follow
                  FROM follows f JOIN users u ON $join AND $pv ORDER BY f.created_at DESC LIMIT 200", array_merge([$u['id'], $t['id']], $pp));
    return ['type' => $type, 'user' => person($t), 'me' => (int)$t['id'] === (int)$u['id'],
            'users' => array_map(fn($r) => person($r) + ['since' => $r['since'], 'following' => (bool)$r['i_follow'], 'is_me' => (int)$r['id'] === (int)$u['id']], $list)];
}

function follow_user(array $u, array $in, bool $on): array {
    $t = row('SELECT * FROM users WHERE id=?', [int_in($in, 'id')]);
    if (!$t) fail('Player not found', 404);
    if ((int)$t['id'] === (int)$u['id']) fail("You can't follow yourself 😄");
    if ($on) {
        if (!can_view_user($t, $u)) fail('This player keeps their profile private 🔒', 403);
        q('INSERT IGNORE INTO follows (follower_id, followee_id) VALUES (?,?)', [$u['id'], $t['id']]);
    } else {
        q('DELETE FROM follows WHERE follower_id=? AND followee_id=?', [$u['id'], $t['id']]);
    }
    return ['following' => $on, 'counts' => follow_counts((int)$t['id'], $u)];
}

/** Remove someone from MY followers */
function remove_follower(array $u, array $in): array {
    q('DELETE FROM follows WHERE follower_id=? AND followee_id=?', [int_in($in, 'id'), $u['id']]);
    return ['ok' => true];
}

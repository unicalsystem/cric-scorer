<?php
/* Cric-Scorer - who may use a custom team in their matches and tournaments.
   IPL and international teams are open to everyone. A custom team can be used by its owner, the admin,
   and players the owner has allowed - or by everyone if the owner chooses "Everyone". */

function can_use_team(?array $t, ?array $u): bool {
    if (!$t || !$u) return false;
    if (($t['category'] ?? 'custom') !== 'custom') return true;
    if (!empty($u['is_admin']) || $t['owner_id'] === null || (int)$t['owner_id'] === (int)$u['id']) return true;
    if (($t['share_mode'] ?? 'approved') === 'everyone') return true;
    if (!empty($t['academy_id']) && val('SELECT 1 FROM academies WHERE id=? AND owner_id=?', [$t['academy_id'], $u['id']])) return true;   // v1.22: the club that runs the league can use its teams
    return (bool)val("SELECT 1 FROM team_access WHERE team_id=? AND user_id=? AND status='allowed'", [$t['id'], $u['id']]);
}
/** SQL that is true when the team (alias t) can be used by this player */
function team_use_sql(array $u): array {
    if (!empty($u['is_admin'])) return ['1=1', []];
    return ["(t.category<>'custom' OR t.owner_id IS NULL OR t.owner_id=? OR t.share_mode='everyone'
              OR EXISTS (SELECT 1 FROM team_access ta WHERE ta.team_id=t.id AND ta.user_id=? AND ta.status='allowed')
              OR EXISTS (SELECT 1 FROM academies ac WHERE ac.id=t.academy_id AND ac.owner_id=?))", [$u['id'], $u['id'], $u['id']]];
}
function require_team_use(int $teamId, array $u): void {
    $t = row('SELECT t.*, o.display_name owner_name FROM teams t LEFT JOIN users o ON o.id=t.owner_id WHERE t.id=?', [$teamId]);
    if (!$t) fail('Team not found', 404);
    if (!can_use_team($t, $u)) fail("🔒 \"{$t['name']}\" belongs to {$t['owner_name']}. Open the team and tap \"Ask to use this team\" - you can play with it once they allow you.", 403);
}

/** For the team page: my status, and for the owner the allowed players and requests */
function team_access_info(array $t, array $u): array {
    $o = ['can_use' => can_use_team($t, $u), 'share_mode' => $t['share_mode'] ?? 'approved', 'my_access' => null];
    if ($t['category'] !== 'custom') return $o;
    $o['my_access'] = val('SELECT status FROM team_access WHERE team_id=? AND user_id=?', [$t['id'], $u['id']]) ?: null;
    if (can_edit_team($t, $u)) {
        $o['access'] = rows("SELECT ta.user_id, ta.status, ta.created_at, u.username, u.display_name, u.avatar_url, u.avatar_preset
                             FROM team_access ta JOIN users u ON u.id=ta.user_id WHERE ta.team_id=? ORDER BY ta.status='pending' DESC, u.display_name", [$t['id']]);
        foreach ($o['access'] as &$a) { $a['user_id'] = (int)$a['user_id']; $a['avatar'] = $a['avatar_url']; unset($a['avatar_url']); }
    }
    return $o;
}

function team_access_action(string $action, array $u, array $in) {
    $t = row('SELECT * FROM teams WHERE id=?', [(int)($in['team_id'] ?? 0)]);
    if (!$t || $t['category'] !== 'custom') fail('Team not found', 404);
    switch ($action) {
    case 'team_access_request':
        if (can_use_team($t, $u)) return ['ok' => true, 'status' => 'allowed'];
        if ($t['owner_id'] === null) fail('This team has no owner');
        q("INSERT IGNORE INTO team_access (team_id, user_id, status) VALUES (?, ?, 'pending')", [$t['id'], $u['id']]);
        return ['ok' => true, 'status' => val('SELECT status FROM team_access WHERE team_id=? AND user_id=?', [$t['id'], $u['id']])];
    case 'team_access_cancel':                         // withdraw my request, or stop using the team
        q('DELETE FROM team_access WHERE team_id=? AND user_id=?', [$t['id'], $u['id']]);
        return ['ok' => true];
    }
    if (!can_edit_team($t, $u)) fail('Only the team owner can change who plays with this team', 403);
    switch ($action) {
    case 'team_access_set': {                          // allow (1) or decline/remove (0) a player
        $uid = (int)($in['user_id'] ?? 0);
        if (!empty($in['allow'])) q("UPDATE team_access SET status='allowed' WHERE team_id=? AND user_id=?", [$t['id'], $uid]);
        else q('DELETE FROM team_access WHERE team_id=? AND user_id=?', [$t['id'], $uid]);
        break;
    }
    case 'team_access_add': {                          // owner adds a player by username
        $un = strtolower(trim((string)($in['username'] ?? '')));
        $p = row('SELECT id FROM users WHERE username=?', [$un]);
        if (!$p) fail('No player with that exact username');
        if ((int)$p['id'] === (int)$t['owner_id']) fail("That's you - you can always use your own team");
        q("INSERT INTO team_access (team_id, user_id, status) VALUES (?, ?, 'allowed') ON DUPLICATE KEY UPDATE status='allowed'", [$t['id'], $p['id']]);
        break;
    }
    case 'team_share_mode':
        q('UPDATE teams SET share_mode=? WHERE id=?', [($in['mode'] ?? '') === 'everyone' ? 'everyone' : 'approved', $t['id']]);
        $t = row('SELECT * FROM teams WHERE id=?', [$t['id']]);
        break;
    default:
        fail('Unknown team action');
    }
    return team_access_info($t, $u);
}

/** Requests waiting on the teams I own (for the little red dot) */
function team_requests_waiting(array $u): int {
    return (int)val("SELECT COUNT(*) FROM team_access ta JOIN teams t ON t.id=ta.team_id WHERE t.owner_id=? AND ta.status='pending'", [$u['id']]);
}

/* ---------- v1.29: team owners & captains as match scorers ---------- */

/** Accounts that run a team: its owner and (when the captain's player is linked to an account) its captain */
function team_managers(int $teamId): array {
    $t = row('SELECT id, short_name, owner_id, captain, category FROM teams WHERE id=?', [$teamId]);
    if (!$t) return [];
    $out = [];
    if ($t['captain']) {
        $p = row('SELECT id, mobile FROM players WHERE team_id=? AND name=? LIMIT 1', [$t['id'], $t['captain']]);
        if ($p) {
            $cu = row('SELECT id, username, display_name FROM users WHERE player_id=? LIMIT 1', [$p['id']])
               ?: ($p['mobile'] ? row('SELECT id, username, display_name FROM users WHERE mobile=? AND mobile_verified_at IS NOT NULL LIMIT 1', [$p['mobile']]) : null);
            if ($cu) $out[] = ['id' => (int)$cu['id'], 'username' => $cu['username'], 'display_name' => $cu['display_name'], 'role' => 'captain', 'team' => $t['short_name'], 'team_id' => (int)$t['id']];
        }
    }
    if ($t['category'] === 'custom' && $t['owner_id']) {
        $ou = row('SELECT id, username, display_name FROM users WHERE id=?', [$t['owner_id']]);
        if ($ou) $out[] = ['id' => (int)$ou['id'], 'username' => $ou['username'], 'display_name' => $ou['display_name'], 'role' => 'owner', 'team' => $t['short_name'], 'team_id' => (int)$t['id']];
    }
    return $out;
}

/** Owners & captains of both sides, captains first (the people a match creator would hand scoring to) */
function match_scorer_options(array $m): array {
    $list = array_merge(team_managers((int)$m['team1_id']), team_managers((int)$m['team2_id']));
    usort($list, fn($a, $b) => ($a['role'] === 'captain' ? 0 : 1) <=> ($b['role'] === 'captain' ? 0 : 1));
    return $list;
}

/** Hand every scheduled match of a tournament to team 1's / team 2's captain (or owner when there is no linked captain) */
function tournament_assign_scorers(array $t, string $side, array $u): array {
    $done = 0; $skipped = [];
    foreach (rows("SELECT * FROM matches WHERE tournament_id=? AND status='scheduled' ORDER BY COALESCE(match_date, created_at), id", [$t['id']]) as $m) {
        if ($side === 'clear') { if ($m['scorer_id']) { q('UPDATE matches SET scorer_id=NULL WHERE id=?', [$m['id']]); match_log($m, 'Scoring taken back by the creator', $u); $done++; } continue; }
        $teamId = (int)($side === 'team2' ? $m['team2_id'] : $m['team1_id']);
        $mg = team_managers($teamId);
        $pick = null;
        foreach ($mg as $x) if ($x['role'] === 'captain') { $pick = $x; break; }
        if (!$pick && $mg) $pick = $mg[count($mg) - 1];
        if (!$pick || $pick['id'] === (int)$m['owner_id']) { $skipped[] = $m['title'] ?: ('Match ' . $m['id']); continue; }   // nobody, or the creator (who can score anyway)
        if ((int)$m['scorer_id'] === $pick['id']) { $done++; continue; }
        q('UPDATE matches SET scorer_id=? WHERE id=?', [$pick['id'], $m['id']]);
        match_log($m, 'Scoring handed to ' . $pick['display_name'] . ' (' . $pick['team'] . ' ' . $pick['role'] . ')', $u);
        $done++;
    }
    return ['assigned' => $done, 'skipped' => $skipped];
}

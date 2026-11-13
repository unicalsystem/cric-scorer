<?php
/* Cric-Scorer by Aaryush - JSON API */
require_once __DIR__ . '/inc/core.php';
require_once __DIR__ . '/inc/engine.php';
require_once __DIR__ . '/inc/academy.php';

$action = preg_replace('/[^a-z_]/', '', $_GET['action'] ?? '');
$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'CricScorer') fail('Bad request', 400);
    $public = ['login', 'register', 'me', 'match_get', 'google_login', 'client_error', 'wa_request', 'wa_verify_code'];
    ensure_schema();
    $u = current_user();
    if (!in_array($action, $public, true) && !$u) fail('Please log in', 401);
    if (strpos($action, 'admin_') === 0 && empty($u['is_admin'])) fail('Only the administrator can do that', 403);
    out(handle($action, $in, $u));
} catch (ApiError $e) {
    rollback_if_needed();
    http_response_code($e->status);
    out(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    rollback_if_needed();
    // Always record the real reason in the server's PHP error log (hPanel → Advanced → PHP error logs)
    $ref = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    error_log("Cric-Scorer API error [$ref] action=$action: " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    if (function_exists('log_error_report')) log_error_report($ref, 'server', $action, $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    $showReal = DEBUG;
    try { $cu = current_user(); $showReal = $showReal || ($cu && !empty($cu['is_admin'])); } catch (Throwable $x) { /* no database */ }
    http_response_code(500);
    out(['error' => $showReal ? "Server error [$ref]: " . $e->getMessage() : "Something went wrong. Please try again. (ref $ref)"]);
}

function handle(string $action, array $in, ?array $u) {
    switch ($action) {

    /* ===== Auth ===== */
    case 'me':
        return ['user' => $u ? public_user($u) : null, 'version' => APP_VERSION, 'auth' => auth_public_config()] + ($u ? client_extras($u) : ['force_version' => setting('force_version')]);

    case 'client_error': {                          // a crash on a phone, so the admin can see it in Error reports
        start_session();
        $_SESSION['client_errs'] = ($_SESSION['client_errs'] ?? 0) + 1;
        if ($_SESSION['client_errs'] > 10) return ['ok' => true];
        $ref = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        log_error_report($ref, 'app', str_in($in, 'where', 40) ?: 'app', str_in($in, 'message', 600));
        return ['ok' => true, 'ref' => $ref];
    }

    /* ===== Help desk, admin portal, Gully Duel ===== */
    case 'team_access_request': case 'team_access_cancel': case 'team_access_set': case 'team_access_add': case 'team_share_mode':
        return team_access_action($action, $u, $in);
    /* ===== Mobile number & WhatsApp verification (optional) ===== */
    case 'wa_request': {                               // purpose: link (signed in), login, signup
        $purpose = in_array($in['purpose'] ?? '', ['link', 'login', 'signup'], true) ? $in['purpose'] : 'link';
        if ($purpose !== 'link') recaptcha_check($in, $purpose === 'login' ? 'login' : 'register');
        otp_cleanup();
        return otp_request((string)($in['mobile'] ?? ''), $purpose, $u, $in);
    }
    case 'wa_verify_code': {                           // typed the 6-digit code instead of tapping the link
        $nu = otp_consume(null, (int)($in['id'] ?? 0), (string)($in['code'] ?? ''));
        return ['user' => public_user($nu), 'found_player' => $nu['found_player'] ?? null];
    }
    case 'mobile_remove':
        q('UPDATE users SET mobile=NULL, mobile_verified_at=NULL WHERE id=?', [$u['id']]);
        return ['user' => public_user(row('SELECT * FROM users WHERE id=?', [$u['id']]))];
    case 'academy_league_get': case 'academy_league_save': {                       // v1.22 club league rule sheet (club owner only)
        $ac = row('SELECT * FROM academies WHERE id=?', [int_in($in, 'id')]);
        if (!$ac) fail('Club not found', 404);
        if (!academy_view($ac, $u)['can_edit']) fail('Only the club owner can see the league rules', 403);
        if ($action === 'academy_league_save') {
            $cfg = league_clean((array)($in['league'] ?? []));
            q('UPDATE academies SET league_json=?, kind=? WHERE id=?', [json_encode($cfg), $cfg['enabled'] ? 'club' : 'academy', $ac['id']]);
        } else {
            $cfg = $ac['league_json'] ? (json_decode($ac['league_json'], true) ?: league_defaults()) : league_defaults();
        }
        $teams = rows('SELECT id, name, short_name, color, league_group, league_cat, (SELECT COUNT(*) FROM players p WHERE p.team_id=teams.id) players FROM teams WHERE academy_id=? ORDER BY league_group, league_cat, name', [$ac['id']]);
        return ['league' => $cfg + ['defaults' => league_defaults()], 'teams' => $teams, 'club' => ['id' => (int)$ac['id'], 'name' => $ac['name']]];
    }
    case 'academies_list': case 'academy_get': case 'academy_save': case 'academy_delete': case 'academy_student_add': case 'academy_student_remove':
        return academies_action($action, $u, $in);
    case 'auc_home': case 'auc_create': case 'auc_lookup': case 'auc_join': case 'auc_profile': case 'auc_settings': case 'auc_pool_add': case 'auc_pool_remove':
    case 'auc_kick': case 'auc_leave': case 'auc_start': case 'auc_state': case 'auc_bid': case 'auc_host': case 'auc_xi': case 'auc_results': case 'auc_csv': case 'auc_gallery':
        return auction_action($action, $in, $u);
    case 'grounds_list': case 'ground_get': case 'ground_save': case 'ground_delete':
    case 'looking_list': case 'looking_get': case 'looking_save': case 'looking_close': case 'looking_reopen': case 'looking_delete': case 'looking_reply':
        return community_action($action, $in, $u);
    case 'help_send': case 'help_list':
        return help_action($action, $u, $in);
    case 'game_create': case 'game_join': case 'game_get': case 'game_bowl': case 'game_bat': case 'game_nudge':
    case 'game_leave': case 'game_rematch': case 'game_record':
    case 'game_timeout': case 'game_set_bowler': case 'game_profile': case 'game_unlock': case 'game_squad':
        return game_action($action, $u, $in);

    case 'register': {
        $un = strtolower(str_in($in, 'username', 50));
        $pw = (string)($in['password'] ?? '');
        $dn = str_in($in, 'display_name', 100) ?: $un;
        if (!preg_match('/^[a-z0-9_.]{3,30}$/', $un)) fail('Username: 3–30 letters, numbers, dot or underscore');
        if (strlen($pw) < 6) fail('Password must be at least 6 characters');
        if (val('SELECT 1 FROM users WHERE username=?', [$un])) fail('That username is taken');
        if (empty($in['consent'])) fail('Please tick the box to agree to the Terms of Use and Privacy Policy');
        recaptcha_check($in, 'register');
        $first = !val('SELECT 1 FROM users LIMIT 1');
        q('INSERT INTO users (username, password_hash, display_name, is_admin, terms_accepted_at) VALUES (?,?,?,?,NOW())', [$un, password_hash($pw, PASSWORD_DEFAULT), $dn, $first ? 1 : 0]);
        $nu = row('SELECT * FROM users WHERE id=?', [lastId()]);
        login_user($nu, true);
        return ['user' => public_user($nu)];
    }

    case 'login': {
        recaptcha_check($in, 'login');
        $un = strtolower(str_in($in, 'username', 50));
        $nu = row('SELECT * FROM users WHERE username=?', [$un]);
        if ($nu && (string)$nu['password_hash'] === '' && !empty($nu['google_sub'])) fail('This player signs in with Google - tap "Sign in with Google"');
        if (!$nu || !password_verify((string)($in['password'] ?? ''), $nu['password_hash'])) { usleep(600000); fail('Wrong username or password'); }
        login_user($nu, !isset($in['remember']) || $in['remember']);
        return ['user' => public_user($nu)];
    }

    case 'google_login':
        return google_login($in);
    case 'google_link':
        return google_link($u, $in);
    case 'google_unlink':
        return google_unlink($u);

    case 'account_delete':
        return account_delete($u, $in);

    case 'logout':
        clear_remember_token();
        $_SESSION = [];
        session_destroy();
        return ['ok' => true];

    case 'profile_save': {
        $dn = str_in($in, 'display_name', 100);
        if ($dn === '') fail('Enter a name');
        q('UPDATE users SET display_name=? WHERE id=?', [$dn, $u['id']]);
        if (isset($in['is_private'])) q('UPDATE users SET is_private=? WHERE id=?', [!empty($in['is_private']) ? 1 : 0, $u['id']]);
        profile_fields_save($u, $in);
        if (!empty($in['new_password'])) {
            if ((string)$u['password_hash'] !== '' && !password_verify((string)($in['current_password'] ?? ''), $u['password_hash'])) fail('Current password is wrong');
            if (strlen($in['new_password']) < 6) fail('New password must be at least 6 characters');
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($in['new_password'], PASSWORD_DEFAULT), $u['id']]);
        }
        return ['user' => public_user(row('SELECT * FROM users WHERE id=?', [$u['id']]))];
    }

    /* ===== Players: search, follow, privacy ===== */
    case 'avatar_upload':  return avatar_upload($u, $in);
    case 'avatar_remove':  return avatar_remove($u);
    case 'users_search':   return users_search($u, $in);
    case 'user_get':       return user_profile($u, $in);
    case 'follow_list':    return follow_list($u, $in);
    case 'follow':         return follow_user($u, $in, true);
    case 'unfollow':       return follow_user($u, $in, false);
    case 'remove_follower': return remove_follower($u, $in);
    case 'match_visibility': {
        $m = load_match(int_in($in, 'id'));
        if (!can_edit_owned($m, $u)) fail('Only the scorer of this match can change who sees it', 403);
        $v = ($in['visibility'] ?? '') === 'private' ? 'private' : 'public';
        q('UPDATE matches SET visibility=? WHERE id=?', [$v, $m['id']]);
        return match_state((int)$m['id'], $u);
    }

    /* ===== Home ===== */
    case 'home': {
        [$vs, $vp] = vis_sql($u);
        $live = match_summaries(rows("SELECT * FROM matches WHERE status='live' AND $vs ORDER BY updated_at DESC LIMIT 10", $vp));
        $mine = match_summaries(rows("SELECT * FROM matches WHERE (owner_id=? OR scorer_id=?) AND status='scheduled' ORDER BY COALESCE(match_date, created_at) ASC LIMIT 5", [$u['id'], $u['id']]));
        $recent = match_summaries(rows("SELECT * FROM matches WHERE status IN ('completed','abandoned') AND $vs ORDER BY updated_at DESC LIMIT 6", $vp));
        $followFeed = match_summaries(rows("SELECT m.* FROM matches m JOIN follows f ON f.followee_id=m.owner_id AND f.follower_id=? JOIN users o ON o.id=m.owner_id
                                            WHERE m.visibility='public' AND o.is_private=0 AND m.status IN ('live','completed') ORDER BY m.updated_at DESC LIMIT 5", [$u['id']]));
        $counts = [
            'matches' => (int)val("SELECT COUNT(*) FROM matches WHERE owner_id=?", [$u['id']]),
            'teams' => (int)val("SELECT COUNT(*) FROM teams WHERE owner_id=?", [$u['id']]),
            'tournaments' => (int)val("SELECT COUNT(*) FROM tournaments WHERE owner_id=?", [$u['id']]),
        ];
        $counts += follow_counts((int)$u['id'], $u);
        return ['live' => $live, 'upcoming' => $mine, 'recent' => $recent, 'following' => $followFeed, 'counts' => $counts, 'announcements' => client_extras($u)['announcements']];
    }

    /* ===== Teams & players ===== */
    case 'teams_list': {
        $cat = $in['category'] ?? '';
        $sql = 'SELECT t.*, u.display_name owner_name, (SELECT COUNT(*) FROM players p WHERE p.team_id=t.id) players FROM teams t LEFT JOIN users u ON u.id=t.owner_id';
        $p = [];
        if (in_array($cat, ['ipl', 'international', 'custom'], true)) { $sql .= ' WHERE t.category=?'; $p[] = $cat; }
        $sql .= " ORDER BY FIELD(t.category,'custom','ipl','international'), t.name";
        $list = rows($sql, $p);
        $mineReq = []; foreach (rows("SELECT ta.team_id, COUNT(*) c FROM team_access ta JOIN teams t ON t.id=ta.team_id WHERE t.owner_id=? AND ta.status='pending' GROUP BY ta.team_id", [$u['id']]) as $r) $mineReq[(int)$r['team_id']] = (int)$r['c'];
        $myAcc = []; foreach (rows('SELECT team_id, status FROM team_access WHERE user_id=?', [$u['id']]) as $r) $myAcc[(int)$r['team_id']] = $r['status'];
        foreach ($list as &$t) {
            $t['id'] = (int)$t['id']; $t['mine'] = (int)$t['owner_id'] === (int)$u['id']; $t['can_edit'] = can_edit_team($t, $u);
            $t['can_use'] = $t['category'] !== 'custom' || !empty($u['is_admin']) || $t['owner_id'] === null || $t['mine'] || $t['share_mode'] === 'everyone' || ($myAcc[$t['id']] ?? '') === 'allowed';
            $t['my_access'] = $myAcc[$t['id']] ?? null;
            $t['requests'] = $mineReq[$t['id']] ?? 0;
        }
        return ['teams' => $list, 'requests' => array_sum($mineReq)];
    }

    case 'team_get': {
        $t = row('SELECT t.*, u.display_name owner_name FROM teams t LEFT JOIN users u ON u.id=t.owner_id WHERE t.id=?', [int_in($in, 'id')]);
        if (!$t) fail('Team not found', 404);
        $t['id'] = (int)$t['id'];
        $t['can_edit'] = can_edit_team($t, $u);
        $t += team_access_info($t, $u);
        $players = rows("SELECT * FROM players WHERE team_id=? ORDER BY FIELD(role,'Batter','Wicket-keeper','All-rounder','Bowler'), name", [$t['id']]);
        [$vs, $vp] = vis_sql($u);
        $recent = match_summaries(rows("SELECT * FROM matches WHERE (team1_id=? OR team2_id=?) AND status IN ('live','completed') AND $vs ORDER BY updated_at DESC LIMIT 5", array_merge([$t['id'], $t['id']], $vp)));
        $club = null;
        if (!empty($t['academy_id']) && ($cfg = league_of_academy((int)$t['academy_id']))) {
            $c = league_cat($cfg, $t['league_cat']);
            $club = ['id' => $cfg['club_id'], 'name' => $cfg['club_name'], 'group' => $t['league_group'], 'cat' => $c ? $c['name'] : null, 'cat_key' => $t['league_cat'], 'squad_size' => $cfg['squad_size'], 'playing' => $cfg['playing'], 'min_women' => $c ? (int)$c['min_women'] : 0,
                     'problems' => $c ? league_check_players($cfg, $t, $players, false) : []];
        }
        return ['team' => $t, 'players' => $players, 'recent' => $recent, 'club' => $club, 'my_clubs' => user_league_clubs($u)];
    }

    case 'team_captain': {                             // team owner (or admin) picks the captain from the squad; empty = no captain
        $t = row('SELECT * FROM teams WHERE id=?', [int_in($in, 'id')]);
        if (!can_edit_team($t, $u)) fail('Only the team owner can change the captain', 403);
        $pid = nint($in['player_id'] ?? null);
        $name = $pid ? val('SELECT name FROM players WHERE id=? AND team_id=?', [$pid, $t['id']]) : null;
        if ($pid && !$name) fail('That player is not in this team');
        q('UPDATE teams SET captain=? WHERE id=?', [$name, $t['id']]);
        return ['captain' => $name];
    }
    case 'team_owner_set': {                           // v1.26: admin hands a custom team to another player
        if (empty($u['is_admin'])) fail('Only the administrator can change a team\'s owner', 403);
        $t = row('SELECT * FROM teams WHERE id=?', [int_in($in, 'id')]);
        if (!$t) fail('Team not found', 404);
        if ($t['category'] !== 'custom') fail('IPL and international teams have no owner');
        $new = row('SELECT id, display_name, username FROM users WHERE id=?', [int_in($in, 'user_id')]);
        if (!$new) fail('Player not found');
        $old = $t['owner_id'] !== null ? (int)$t['owner_id'] : null;
        if ($old === (int)$new['id']) fail($new['display_name'] . ' already owns this team');
        db()->beginTransaction();
        q('UPDATE teams SET owner_id=? WHERE id=?', [$new['id'], $t['id']]);
        q('DELETE FROM team_access WHERE team_id=? AND user_id=?', [$t['id'], $new['id']]);   // owners need no access row
        // the previous owner keeps using the team (their fixtures keep working) unless the admin says otherwise
        if ($old && empty($in['drop_old']))
            q("INSERT INTO team_access (team_id, user_id, status) VALUES (?, ?, 'allowed') ON DUPLICATE KEY UPDATE status='allowed'", [$t['id'], $old]);
        db()->commit();
        return ['owner_id' => (int)$new['id'], 'owner_name' => $new['display_name']];
    }
    case 'team_save': {
        $id = int_in($in, 'id');
        $name = str_in($in, 'name', 100);
        if ($name === '') fail('Enter a team name');
        $short = strtoupper(str_in($in, 'short_name', 10)) ?: strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
        $color = valid_color(str_in($in, 'color', 10));
        $cap = str_in($in, 'captain', 100) ?: null;
        $ground = str_in($in, 'home_ground', 150) ?: null;
        // v1.22 club league: a team may belong to a club, in a group and a category (only clubs the user is in)
        $clubId = nint($in['academy_id'] ?? null); $grp = null; $lcat = null;
        if ($clubId) {
            $cfg = league_of_academy($clubId);
            if (!$cfg || !is_club_member($clubId, $u)) fail('You are not a member of that club');
            $grp = str_in($in, 'league_group', 60) ?: null; $lcat = str_in($in, 'league_cat', 40) ?: null;
            if ($grp !== null && $cfg['groups'] && !in_array($grp, $cfg['groups'], true)) fail('Pick one of the club\'s groups');
            if ($lcat !== null && !league_cat($cfg, $lcat)) fail('Pick one of the club\'s categories');
            if ($grp && $lcat) {
                if (val('SELECT 1 FROM teams WHERE academy_id=? AND league_group=? AND league_cat=? AND id<>?', [$clubId, $grp, $lcat, $id])) fail("$grp already has a " . league_cat($cfg, $lcat)['name'] . ' team');
                if ((int)val('SELECT COUNT(*) FROM teams WHERE academy_id=? AND league_group=? AND id<>?', [$clubId, $grp, $id]) >= (int)$cfg['max_teams_per_group']) fail("$grp already has " . $cfg['max_teams_per_group'] . ' teams');
            }
        }
        if ($id) {
            $t = row('SELECT * FROM teams WHERE id=?', [$id]);
            if (!can_edit_team($t, $u)) fail('You cannot edit this team', 403);
            q('UPDATE teams SET name=?, short_name=?, color=?, captain=?, home_ground=?, academy_id=?, league_group=?, league_cat=? WHERE id=?', [$name, $short, $color, $cap, $ground, $clubId, $grp, $lcat, $id]);
        } else {
            $cat = ($u['is_admin'] && in_array($in['category'] ?? '', ['ipl', 'international'], true)) ? $in['category'] : 'custom';
            q('INSERT INTO teams (name, short_name, color, category, owner_id, captain, home_ground, academy_id, league_group, league_cat) VALUES (?,?,?,?,?,?,?,?,?,?)', [$name, $short, $color, $cat, $cat === 'custom' ? $u['id'] : null, $cap, $ground, $clubId, $grp, $lcat]);
            $id = lastId();
        }
        return ['id' => $id];
    }

    case 'team_delete': {
        $t = row('SELECT * FROM teams WHERE id=?', [int_in($in, 'id')]);
        if (!can_edit_team($t, $u)) fail('You cannot delete this team', 403);
        if (val('SELECT 1 FROM matches WHERE team1_id=? OR team2_id=? LIMIT 1', [$t['id'], $t['id']])) fail('This team has played matches and cannot be deleted');
        q('DELETE FROM teams WHERE id=?', [$t['id']]);
        return ['ok' => true];
    }

    case 'player_save': {
        $id = int_in($in, 'id');
        $teamId = int_in($in, 'team_id');
        if ($id) { $teamId = (int)val('SELECT team_id FROM players WHERE id=?', [$id]); }
        $t = row('SELECT * FROM teams WHERE id=?', [$teamId]);
        if (!can_edit_team($t, $u)) fail('You cannot edit players of this team', 403);
        $name = str_in($in, 'name', 100);
        if ($name === '') fail('Enter the player name');
        $role = in_array($in['role'] ?? '', ['Batter', 'Bowler', 'All-rounder', 'Wicket-keeper'], true) ? $in['role'] : 'Batter';
        $mobRaw = trim((string)($in['mobile'] ?? ''));
        $mobile = $mobRaw === '' ? null : normalize_mobile($mobRaw);
        if ($mobRaw !== '' && !$mobile) fail('That WhatsApp number does not look right, e.g. 98765 43210');
        $gender = in_array($in['gender'] ?? '', ['M', 'F', 'O'], true) ? $in['gender'] : null;
        $by = int_in($in, 'birth_year'); $by = $by >= 1920 && $by <= (int)date('Y') ? $by : null;
        $vals = [$name, $role, str_in($in, 'batting_style', 30) ?: 'Right-hand bat', str_in($in, 'bowling_style', 40) ?: null,
                 str_in($in, 'country', 50) ?: null, str_in($in, 'jersey_no', 5) ?: null, !empty($in['is_overseas']) ? 1 : 0, $mobile, $gender, $by];
        if ($id) {
            q('UPDATE players SET name=?, role=?, batting_style=?, bowling_style=?, country=?, jersey_no=?, is_overseas=?, mobile=?, gender=?, birth_year=? WHERE id=?', array_merge($vals, [$id]));
        } else {
            league_check_squad_add($t);
            q('INSERT INTO players (name, role, batting_style, bowling_style, country, jersey_no, is_overseas, mobile, gender, birth_year, team_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [$teamId]));
            $id = lastId();
        }
        $linked = $mobile ? link_player_by_mobile($mobile, $id) : null;
        return ['id' => $id, 'linked_to' => $linked];
    }

    case 'players_import': {   // v1.25: bulk add from a pasted list / CSV
        $teamId = int_in($in, 'team_id');
        $t = row('SELECT * FROM teams WHERE id=?', [$teamId]);
        if (!can_edit_team($t, $u)) fail('You cannot edit players of this team', 403);
        $rowsIn = is_array($in['rows'] ?? null) ? array_slice($in['rows'], 0, 60) : [];
        if (!$rowsIn) fail('Nothing to import');
        $existing = rows('SELECT id, name, mobile FROM players WHERE team_id=?', [$teamId]);
        $names = []; $mobiles = [];
        foreach ($existing as $e) { $names[mb_strtolower(trim($e['name']))] = true; if ($e['mobile']) $mobiles[$e['mobile']] = true; }
        $cfg = (!empty($t['academy_id']) && !empty($t['league_cat'])) ? league_of_academy((int)$t['academy_id']) : null;
        $count = count($existing); $added = []; $skipped = [];
        foreach ($rowsIn as $r) {
            if (!is_array($r)) continue;
            $name = preg_replace('/\s+/u', ' ', trim((string)($r['name'] ?? '')));
            if ($name === '' || mb_strlen($name) > 100) continue;
            $key = mb_strtolower($name);
            if (isset($names[$key])) { $skipped[] = ['name' => $name, 'reason' => 'already in the team']; continue; }
            $mobRaw = trim((string)($r['mobile'] ?? '')); $mobile = $mobRaw === '' ? null : normalize_mobile($mobRaw);
            if ($mobile && isset($mobiles[$mobile])) { $skipped[] = ['name' => $name, 'reason' => 'that WhatsApp number is already in the team']; continue; }
            if ($cfg && $count >= (int)$cfg['squad_size']) { $skipped[] = ['name' => $name, 'reason' => 'club squad limit of ' . $cfg['squad_size'] . ' reached']; continue; }
            $role = in_array($r['role'] ?? '', ['Batter', 'Bowler', 'All-rounder', 'Wicket-keeper'], true) ? $r['role'] : 'Batter';
            $gender = in_array($r['gender'] ?? '', ['M', 'F', 'O'], true) ? $r['gender'] : null;
            $by = (int)($r['birth_year'] ?? 0); $by = $by >= 1920 && $by <= (int)date('Y') ? $by : null;
            $jersey = mb_substr(trim((string)($r['jersey_no'] ?? '')), 0, 5) ?: null;
            $bats = mb_substr(trim((string)($r['batting_style'] ?? '')), 0, 30) ?: 'Right-hand bat';
            $bowls = mb_substr(trim((string)($r['bowling_style'] ?? '')), 0, 40) ?: null;
            q('INSERT INTO players (name, role, batting_style, bowling_style, jersey_no, mobile, gender, birth_year, team_id) VALUES (?,?,?,?,?,?,?,?,?)', [$name, $role, $bats, $bowls, $jersey, $mobile, $gender, $by, $teamId]);
            $pid = lastId(); $count++; $names[$key] = true; if ($mobile) { $mobiles[$mobile] = true; link_player_by_mobile($mobile, $pid); }
            $added[] = ['id' => $pid, 'name' => $name];
        }
        return ['added' => $added, 'skipped' => $skipped];
    }

    case 'player_delete': {
        $p = row('SELECT * FROM players WHERE id=?', [int_in($in, 'id')]);
        if (!$p) fail('Player not found', 404);
        if (!can_edit_team(row('SELECT * FROM teams WHERE id=?', [$p['team_id']]), $u)) fail('Not allowed', 403);
        if (val('SELECT 1 FROM match_players WHERE player_id=? LIMIT 1', [$p['id']])) fail('This player has played matches and cannot be deleted');
        q('DELETE FROM players WHERE id=?', [$p['id']]);
        return ['ok' => true];
    }

    case 'players_search': {
        $qs = '%' . str_in($in, 'q', 60) . '%';
        $exclude = int_in($in, 'exclude_team');
        $custom = !empty($in['custom_only']) ? " AND t.category='custom'" : '';
        [$us, $up] = team_use_sql($u);
        $players = rows("SELECT p.id, p.name, p.role, p.country, t.name team_name, t.short_name team, t.color,
                                     (SELECT username FROM users WHERE player_id=p.id) linked_to, IF($us, 1, 0) can_use
                                   FROM players p JOIN teams t ON t.id=p.team_id
                                   WHERE p.name LIKE ? AND p.team_id<>?$custom ORDER BY t.category='custom' DESC, p.name LIMIT 60", array_merge($up, [$qs, $exclude]));
        // Players with an account - even brand-new ones who are in no team yet - so anyone can add them to a team
        $q = trim(str_in($in, 'q', 60)); $mob = normalize_mobile($q);
        [$pv, $pp] = people_vis($u, 'u');
        $accounts = rows("SELECT u.id, u.username, u.display_name, u.role, u.batting_style, u.bowling_style, u.mobile_verified_at IS NOT NULL verified
                          FROM users u WHERE $pv AND u.id<>? AND (" . ($mob ? 'u.mobile=?' : 'LOCATE(?, u.display_name) > 0 OR LOCATE(?, u.username) > 0') . ")
                          AND NOT EXISTS (SELECT 1 FROM players px WHERE px.team_id=? AND px.name=u.display_name) ORDER BY u.display_name LIMIT 20",
                          array_merge($pp, [$u['id']], $mob ? [$mob] : [$q, $q], [$exclude]));
        foreach ($accounts as &$a) { $a['id'] = (int)$a['id']; $a['verified'] = (bool)$a['verified']; }
        return ['players' => $players, 'accounts' => $accounts];
    }

    case 'player_copy': {
        $t = row('SELECT * FROM teams WHERE id=?', [int_in($in, 'team_id')]);
        if (!can_edit_team($t, $u)) fail('You cannot edit this team', 403);
        $n = 0;
        foreach ((array)($in['user_ids'] ?? []) as $uid) {           // app accounts: make a player entry for them in this team
            $a = row('SELECT * FROM users WHERE id=?', [(int)$uid]);
            if (!$a || !can_view_user($a, $u) || val('SELECT 1 FROM players WHERE team_id=? AND name=?', [$t['id'], $a['display_name']])) continue;
            q('INSERT INTO players (team_id, name, role, batting_style, bowling_style, country, mobile) VALUES (?,?,?,?,?,?,?)',
              [$t['id'], $a['display_name'], $a['role'] ?: 'Batter', $a['batting_style'] ?: 'Right-hand bat', $a['bowling_style'] ?: null, 'India', $a['mobile_verified_at'] ? $a['mobile'] : null]);
            if (empty($a['player_id'])) q('UPDATE users SET player_id=? WHERE id=?', [lastId(), $a['id']]);   // their stats will show on their profile
            $n++;
        }
        foreach ((array)($in['player_ids'] ?? []) as $pid) {
            $p = row('SELECT * FROM players WHERE id=?', [(int)$pid]);
            if (!$p || !can_use_team(row('SELECT * FROM teams WHERE id=?', [$p['team_id']]), $u)) continue;
            if (val('SELECT 1 FROM players WHERE team_id=? AND name=?', [$t['id'], $p['name']])) continue;
            q('INSERT INTO players (team_id, name, role, batting_style, bowling_style, country, jersey_no, is_overseas) VALUES (?,?,?,?,?,?,?,?)',
              [$t['id'], $p['name'], $p['role'], $p['batting_style'], $p['bowling_style'], $p['country'], $p['jersey_no'], 0]);
            $n++;
        }
        return ['added' => $n];
    }

    case 'player_get': {
        $p = row('SELECT p.*, t.name team_name, t.short_name, t.color, t.category FROM players p JOIN teams t ON t.id=p.team_id WHERE p.id=?', [int_in($in, 'id')]);
        if (!$p) fail('Player not found', 404);
        $p['can_edit'] = can_edit_team(['category' => $p['category'], 'owner_id' => val('SELECT owner_id FROM teams WHERE id=?', [$p['team_id']])], $u);
        $recent = rows("SELECT m.id match_id, m.title, i.innings_no, i.id innings_id,
                          (SELECT COALESCE(SUM(runs_bat),0) FROM balls WHERE innings_id=i.id AND batsman_id=?) runs,
                          (SELECT COUNT(*) FROM balls WHERE innings_id=i.id AND batsman_id=? AND (extra_type IS NULL OR extra_type<>'wd')) balls,
                          (SELECT COUNT(*) FROM balls WHERE innings_id=i.id AND dismissed_id=? AND is_wicket=1) out_,
                          t1.short_name t1, t2.short_name t2, m.match_date
                        FROM innings_batters ib JOIN innings i ON i.id=ib.innings_id JOIN matches m ON m.id=i.match_id
                        JOIN teams t1 ON t1.id=m.team1_id JOIN teams t2 ON t2.id=m.team2_id
                        WHERE ib.player_id=? AND " . vis_sql($u, 'm')[0] . " ORDER BY m.id DESC LIMIT 8", array_merge([$p['id'], $p['id'], $p['id'], $p['id']], vis_sql($u, 'm')[1]));
        if (!$p['can_edit'] && empty($u['is_admin'])) q('UPDATE players SET views=views+1 WHERE id=?', [$p['id']]);
        $career = player_career((int)$p['id'], $u);
        return ['player' => $p, 'stats' => $career, 'recent' => $recent, 'views' => (int)$p['views'] + ($p['can_edit'] ? 0 : 1), 'level' => $career['level'], 'filters' => career_filter_options((int)$p['id'], $u)];
    }
    case 'player_matches': return player_matches(int_in($in, 'id'), $u, (array)($in['filters'] ?? []), max(1, int_in($in, 'page', 1)));
    case 'player_career': return ['stats' => player_career(int_in($in, 'id'), $u, (array)($in['filters'] ?? []))];
    case 'player_awards': return ['awards' => player_awards(int_in($in, 'id'), $u)];
    case 'player_badges': return ['badges' => player_badges(int_in($in, 'id'), $u)];
    case 'player_teams': return ['teams' => player_teams(int_in($in, 'id'), $u)];

    /* ===== Match types ===== */
    case 'types_list':
        return ['types' => rows('SELECT * FROM match_types WHERE is_system=1 OR owner_id=? ORDER BY is_system DESC, id', [$u['id']])];

    case 'type_save': {
        $id = int_in($in, 'id');
        $name = str_in($in, 'name', 60);
        if ($name === '') fail('Enter a name');
        $vals = [$name, max(1, min(100, int_in($in, 'overs', 20))), max(1, min(10, int_in($in, 'balls_per_over', 6))),
                 max(1, min(15, int_in($in, 'players_per_side', 11))), max(0, min(50, int_in($in, 'max_overs_bowler', 0))),
                 !empty($in['last_man_stands']) ? 1 : 0, max(0, min(6, int_in($in, 'bouncers_per_over', 1))), str_in($in, 'description', 255) ?: null];
        $xq = extra_quota($in, (int)$vals[4]);
        if ($id) {
            $t = row('SELECT * FROM match_types WHERE id=?', [$id]);
            if (!$t || ($t['is_system'] && !$u['is_admin']) || (!$t['is_system'] && !can_edit_owned($t, $u))) fail('You cannot edit this match type', 403);
            q('UPDATE match_types SET name=?, overs=?, balls_per_over=?, players_per_side=?, max_overs_bowler=?, last_man_stands=?, bouncers_per_over=?, description=? WHERE id=?', array_merge($vals, [$id]));
        } else {
            q('INSERT INTO match_types (name, overs, balls_per_over, players_per_side, max_overs_bowler, last_man_stands, bouncers_per_over, description, owner_id) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($vals, [$u['id']]));
            $id = lastId();
        }
        q('UPDATE match_types SET extra_bowlers=?, extra_overs=? WHERE id=?', [$xq[0], $xq[1], $id]);
        return ['id' => $id];
    }

    case 'type_delete': {
        $t = row('SELECT * FROM match_types WHERE id=?', [int_in($in, 'id')]);
        if (!$t || ($t['is_system'] && !$u['is_admin']) || (!$t['is_system'] && !can_edit_owned($t, $u))) fail('You cannot delete this match type', 403);
        q('DELETE FROM match_types WHERE id=?', [$t['id']]);
        return ['ok' => true];
    }

    /* ===== Matches ===== */
    case 'matches_list': {
        $st = $in['status'] ?? '';
        $where = []; $p = [];
        if (in_array($st, ['scheduled', 'live', 'completed'], true)) { $where[] = $st === 'completed' ? "status IN ('completed','abandoned')" : 'status=?'; if ($st !== 'completed') $p[] = $st; }
        $scope = $in['scope'] ?? (!empty($in['mine']) ? 'mine' : 'all');
        if ($scope === 'mine') { $where[] = '(owner_id=? OR scorer_id=?)'; $p[] = $u['id']; $p[] = $u['id']; }
        elseif ($scope === 'following') { $where[] = "owner_id IN (SELECT f.followee_id FROM follows f JOIN users o ON o.id=f.followee_id WHERE f.follower_id=? AND o.is_private=0)"; $p[] = $u['id']; }
        [$vs, $vp] = vis_sql($u); $where[] = $vs; $p = array_merge($p, $vp);
        $sql = 'SELECT * FROM matches' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . ($st === 'scheduled' ? 'COALESCE(match_date, created_at) ASC' : 'updated_at DESC') . ' LIMIT 100';
        return ['matches' => match_summaries(rows($sql, $p))];
    }

    case 'match_create': {
        $t1 = int_in($in, 'team1_id'); $t2 = int_in($in, 'team2_id');
        if (!$t1 || !$t2) fail('Choose both teams');
        if ($t1 === $t2) fail('Choose two different teams');
        if (!val('SELECT 1 FROM teams WHERE id=?', [$t1]) || !val('SELECT 1 FROM teams WHERE id=?', [$t2])) fail('Team not found');
        require_team_use($t1, $u); require_team_use($t2, $u);
        $tour = nint($in['tournament_id'] ?? null);
        if ($tour && !can_edit_owned(row('SELECT * FROM tournaments WHERE id=?', [$tour]), $u)) fail('You cannot add matches to this tournament', 403);
        $date = str_in($in, 'match_date', 20);
        $date = $date ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s');
        $visibility = ($in['visibility'] ?? '') === 'private' ? 'private' : 'public';
        $test = ($in['format'] ?? '') === 'test';
        if ($tour && ($lcfg = league_of_academy(nint(val('SELECT academy_id FROM tournaments WHERE id=?', [$tour]))))) { $in['players_per_side'] = $lcfg['playing']; $test = false; }   // club league matches use the club's playing number
        $overs = $test ? TEST_OVERS : max(1, min(100, int_in($in, 'overs', 20)));
        q('INSERT INTO matches (owner_id, tournament_id, match_type_id, title, team1_id, team2_id, overs, balls_per_over, players_per_side, max_overs_bowler, last_man_stands, venue, match_date, visibility, powerplay_overs, bouncers_per_over)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $u['id'], $tour, nint($in['match_type_id'] ?? null), str_in($in, 'title', 100) ?: null, $t1, $t2,
            $overs, max(1, min(10, int_in($in, 'balls_per_over', 6))),
            max(1, min(15, int_in($in, 'players_per_side', 11))), max(0, min(50, int_in($in, 'max_overs_bowler', 0))),
            !empty($in['last_man_stands']) ? 1 : 0, str_in($in, 'venue', 150) ?: null, $date, $visibility, max(0, min($overs, int_in($in, 'powerplay_overs', 0))), max(0, min(6, int_in($in, 'bouncers_per_over', 1)))]);
        $mid = lastId();
        $xq = $test ? [0, 0] : extra_quota($in, max(0, min(50, int_in($in, 'max_overs_bowler', 0))));
        if ($xq[0]) q('UPDATE matches SET extra_bowlers=?, extra_overs=? WHERE id=?', [$xq[0], $xq[1], $mid]);
        if ($test) q('UPDATE matches SET format=?, days=?, follow_on=?, powerplay_overs=0 WHERE id=?', ['test', max(1, min(7, int_in($in, 'days', 5))), max(0, min(500, int_in($in, 'follow_on', 200))), $mid]);
        $bt = (string)($in['ball_type'] ?? ''); $round = str_in($in, 'round_name', 60);
        $gid = nint($in['ground_id'] ?? null); if ($gid && !val('SELECT 1 FROM grounds WHERE id=?', [$gid])) $gid = null;
        if (in_array($bt, BALL_TYPES, true) || $round || $gid) q('UPDATE matches SET ball_type=?, round_name=?, ground_id=? WHERE id=?', [in_array($bt, BALL_TYPES, true) ? $bt : null, $round ?: null, $gid, $mid]);
        return ['id' => $mid];
    }

    case 'match_get':
        return match_state(int_in($in, 'id'), $u);

    case 'match_photo_add': case 'match_photo_delete': case 'match_photo_caption': {   // v1.21 match photos
        $m = load_match(int_in($in, 'id'));
        if (!can_view_match($m, $u)) fail('This match is private 🔒', 403);
        if ($action === 'match_photo_add') return match_photo_add($m, $u, $in);
        if ($action === 'match_photo_caption') return match_photo_caption($m, $u, $in);
        return match_photo_delete($m, $u, $in);
    }

    case 'match_dls_preview': {
        $m = load_match(int_in($in, 'id'));
        if (!can_view_match($m, $u)) fail('This match is private 🔒', 403);
        return dls_preview($m, max(1, int_in($in, 'overs', 1)));
    }

    case 'match_scorer': {                            // the creator names who scores (by username), or takes it back
        $m = load_match(int_in($in, 'id'));
        if (!can_edit_owned($m, $u)) fail('Only the player who created this match can choose the scorer', 403);
        $un = strtolower(trim((string)($in['username'] ?? '')));
        if ($un === '') { if ($m['scorer_id']) match_log($m, 'Scoring taken back by the creator', $u); q('UPDATE matches SET scorer_id=NULL WHERE id=?', [$m['id']]); return ['scorer' => null]; }
        $p = row('SELECT id, username, display_name FROM users WHERE username=?', [$un]);
        if (!$p) fail('No player with that exact username');
        if ((int)$p['id'] === (int)$m['owner_id']) fail("That's you - you can always score your own match");
        q('UPDATE matches SET scorer_id=? WHERE id=?', [$p['id'], $m['id']]);
        match_log($m, 'Scoring handed to ' . $p['display_name'], $u);
        return ['scorer' => ['id' => (int)$p['id'], 'username' => $p['username'], 'display_name' => $p['display_name']]];
    }

    case 'match_delete': {
        $m = load_match(int_in($in, 'id'));
        if (!can_edit_owned($m, $u)) fail('Not allowed', 403);
        match_photos_purge((int)$m['id']);
        q('DELETE FROM matches WHERE id=?', [$m['id']]);
        return ['ok' => true];
    }

    case 'match_xi': case 'match_toss': case 'match_start_innings': case 'match_ball': case 'match_batter':
    case 'match_bowler': case 'match_swap': case 'match_undo': case 'match_end_innings': case 'match_abandon':
    case 'match_rain': case 'match_rain_end': case 'match_rain_undo': case 'match_dls_level':
    case 'match_penalty': case 'match_penalty_undo': case 'match_powerplay': case 'match_overs': case 'match_batter_change':
    case 'match_ball_detail': case 'match_ball_edit': case 'match_ball_delete': case 'match_super_over': case 'match_details': case 'match_field': case 'match_draw': {
        db()->beginTransaction();
        $m = load_match(int_in($in, 'id'), true);
        if (!can_score_match($m, $u)) fail('Only the scorer of this match can do that', 403);
        if (!in_array($action, ['match_undo', 'match_rain_undo', 'match_ball_edit', 'match_ball_delete', 'match_ball_detail', 'match_super_over', 'match_details', 'match_penalty', 'match_penalty_undo'], true) && in_array($m['status'], ['completed', 'abandoned'], true)) fail('This match is finished');
        switch ($action) {
            case 'match_xi': set_playing_xi($m, $in); break;
            case 'match_toss': set_toss($m, $in); break;
            case 'match_start_innings': start_innings($m, $in); break;
            case 'match_ball': score_ball($m, $in); break;
            case 'match_batter': set_batter($m, $in); break;
            case 'match_bowler': set_bowler($m, $in); break;
            case 'match_swap': swap_strike($m); break;
            case 'match_undo': undo_ball($m); break;
            case 'match_end_innings': end_innings_manual($m); break;
            case 'match_rain': rain_reduce($m, $in); break;
            case 'match_rain_end': rain_end_match($m); break;
            case 'match_rain_undo': rain_undo($m); break;
            case 'match_dls_level':
                q('UPDATE matches SET dls_g50=? WHERE id=?', [in_array((int)($in['g50'] ?? 200), [200, 245], true) ? (int)$in['g50'] : 200, $m['id']]);
                dls_update_target($m);
                break;
            case 'match_abandon': q("UPDATE matches SET status='abandoned', result_text='No result (match abandoned)', winner_team_id=NULL WHERE id=?", [$m['id']]); break;
            case 'match_penalty': match_penalty($m, $in); break;
            case 'match_penalty_undo': match_penalty_undo($m, $in); break;
            case 'match_powerplay': match_powerplay($m, $in); break;
            case 'match_overs': match_overs_change($m, $in); break;
            case 'match_batter_change': match_batter_change($m, $in); break;
            case 'match_ball_detail': match_ball_detail($m, $in); break;
            case 'match_ball_edit': match_ball_edit($m, $in); break;
            case 'match_ball_delete': match_ball_edit($m, $in, true); break;
            case 'match_super_over': match_super_over($m); break;
            case 'match_draw': test_draw($m); match_log($m, 'Match drawn', $u); break;
            case 'match_details': match_details_save($m, $in); break;
            case 'match_field': match_field_set($m, $in); break;
        }
        $logText = [
            'match_ball_edit' => 'Edited ball ' . (int)($in['seq'] ?? 0), 'match_ball_delete' => 'Deleted ball ' . (int)($in['seq'] ?? 0),
            'match_penalty' => (int)($in['runs'] ?? 5) . ' penalty runs awarded', 'match_penalty_undo' => 'Penalty runs removed',
            'match_overs' => 'Overs changed to ' . (int)($in['overs'] ?? 0), 'match_powerplay' => 'Powerplay set to ' . (int)($in['overs'] ?? 0) . ' overs',
            'match_batter_change' => 'Batter changed', 'match_super_over' => 'Super over started', 'match_abandon' => 'Match abandoned',
            'match_rain' => 'Rain: overs reduced to ' . (int)($in['overs'] ?? 0), 'match_rain_end' => 'Rain stopped play - result by DLS', 'match_end_innings' => 'Innings ended by the scorer',
        ][$action] ?? null;
        if ($logText) match_log($m, $logText, $u);
        db()->commit();
        return match_state((int)$m['id'], $u);
    }

    /* ===== Tournaments ===== */
    case 'tournaments_list': {
        $list = rows('SELECT t.*, mt.name type_name, CASE WHEN u.is_private=1 AND u.id<>' . (int)$u['id'] . ' THEN NULL ELSE u.display_name END owner_name,
                        (SELECT COUNT(*) FROM tournament_teams tt WHERE tt.tournament_id=t.id) teams,
                        (SELECT COUNT(*) FROM matches m WHERE m.tournament_id=t.id) matches,
                        (SELECT COUNT(*) FROM matches m WHERE m.tournament_id=t.id AND m.status IN (\'completed\',\'abandoned\')) done,
                        (SELECT COUNT(*) FROM matches m WHERE m.tournament_id=t.id AND m.status=\'live\') live
                      FROM tournaments t LEFT JOIN match_types mt ON mt.id=t.match_type_id LEFT JOIN users u ON u.id=t.owner_id
                      ORDER BY t.created_at DESC');
        $list = array_values(array_filter($list, fn($t) => can_view_tournament($t, $u)));
        foreach ($list as &$t) { $t['mine'] = (int)$t['owner_id'] === (int)$u['id']; $t['state'] = tournament_state($t); $t['club_name'] = !empty($t['academy_id']) ? val('SELECT name FROM academies WHERE id=?', [$t['academy_id']]) : null; }
        return ['tournaments' => $list, 'my_clubs' => user_league_clubs($u)];
    }

    case 'tournament_get': {
        $t = row('SELECT t.*, mt.name type_name, mt.overs, mt.balls_per_over, mt.players_per_side, mt.max_overs_bowler, mt.last_man_stands, mt.bouncers_per_over, CASE WHEN u.is_private=1 AND u.id<>' . (int)$u['id'] . ' THEN NULL ELSE u.display_name END owner_name
                  FROM tournaments t LEFT JOIN match_types mt ON mt.id=t.match_type_id LEFT JOIN users u ON u.id=t.owner_id WHERE t.id=?', [int_in($in, 'id')]);
        if (!$t) fail('Tournament not found', 404);
        if (!can_view_tournament($t, $u)) fail('This tournament is for club members only 🔒', 403);
        $t['can_edit'] = can_edit_owned($t, $u);
        if (($t['format'] ?? '') === 'league_final') playoff_advance((int)$t['id']);   // before listing matches, so new playoff games show up
        $teams = rows('SELECT t.id, t.name, t.short_name, t.color, t.league_group, t.league_cat, (SELECT COUNT(*) FROM players p WHERE p.team_id=t.id) players FROM tournament_teams tt JOIN teams t ON t.id=tt.team_id WHERE tt.tournament_id=? ORDER BY t.league_cat, t.league_group, t.name', [$t['id']]);
        [$vs, $vp] = vis_sql($u);
        $matches = match_summaries(rows("SELECT * FROM matches WHERE tournament_id=? AND $vs ORDER BY COALESCE(match_date, created_at), id", array_merge([$t['id']], $vp)));
        $t['matches'] = count($matches);
        $t['done'] = count(array_filter($matches, fn($m) => in_array($m['status'], ['completed', 'abandoned'], true)));
        $t['live'] = count(array_filter($matches, fn($m) => $m['status'] === 'live'));
        $t['state'] = tournament_state($t);
        return ['tournament' => $t, 'teams' => $teams, 'matches' => $matches, 'points' => points_table((int)$t['id']), 'leaders' => leaderboards((int)$t['id'], 5, $u), 'mvp' => tournament_mvp((int)$t['id'], 10)] + league_tournament_extra($t) + playoff_info($t);
    }

    case 'tournament_save': {
        $id = int_in($in, 'id');
        $name = str_in($in, 'name', 120);
        if ($name === '') fail('Enter a tournament name');
        $fmt = in_array($in['format'] ?? '', ['league', 'knockout', 'league_final'], true) ? $in['format'] : 'league';
        $teams = array_values(array_unique(array_map('intval', (array)($in['team_ids'] ?? []))));
        if (count($teams) < 2) fail('Pick at least 2 teams');
        $already = $id ? array_map('intval', array_column(rows('SELECT team_id FROM tournament_teams WHERE tournament_id=?', [$id]), 'team_id')) : [];
        foreach ($teams as $tid) if (!in_array($tid, $already, true)) require_team_use($tid, $u);
        $clubId = nint($in['academy_id'] ?? null); $vis = 'public';
        if ($clubId) {
            $cfg = league_of_academy($clubId);
            if (!$cfg || !is_club_member($clubId, $u)) fail('You are not a member of that club');
            $vis = ($in['visibility'] ?? '') === 'club' ? 'club' : 'public';
            foreach ($teams as $tid) { $tt = row('SELECT * FROM teams WHERE id=?', [$tid]); if ($tt && ((int)$tt['academy_id'] !== $clubId || !$tt['league_cat'])) fail($tt['name'] . ' is not set up for ' . $cfg['club_name'] . ' - open the team, tap Edit and pick its group and category'); }
        }
        $poJson = $fmt === 'league_final' ? json_encode(playoff_clean((array)($in['playoffs'] ?? []))) : null;
        $vals = [$name, $fmt, nint($in['match_type_id'] ?? null), str_in($in, 'start_date', 10) ?: null, str_in($in, 'end_date', 10) ?: null, str_in($in, 'venue', 150) ?: null, $clubId, $vis];
        if ($id) {
            $t = row('SELECT * FROM tournaments WHERE id=?', [$id]);
            if (!can_edit_owned($t, $u)) fail('Not allowed', 403);
            q('UPDATE tournaments SET name=?, format=?, match_type_id=?, start_date=?, end_date=?, venue=?, academy_id=?, visibility=? WHERE id=?', array_merge($vals, [$id]));
            q('DELETE FROM tournament_teams WHERE tournament_id=?', [$id]);
        } else {
            q('INSERT INTO tournaments (name, format, match_type_id, start_date, end_date, venue, academy_id, visibility, owner_id) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($vals, [$u['id']]));
            $id = lastId();
        }
        q('UPDATE tournaments SET playoff_json=? WHERE id=?', [$poJson, $id]);
        foreach ($teams as $tid) if (val('SELECT 1 FROM teams WHERE id=?', [$tid])) q('INSERT INTO tournament_teams (tournament_id, team_id) VALUES (?,?)', [$id, $tid]);
        return ['id' => $id];
    }

    case 'tournament_delete': {
        $t = row('SELECT * FROM tournaments WHERE id=?', [int_in($in, 'id')]);
        if (!can_edit_owned($t, $u)) fail('Not allowed', 403);
        if (!empty($in['with_matches'])) q('DELETE FROM matches WHERE tournament_id=?', [$t['id']]);
        q('DELETE FROM tournaments WHERE id=?', [$t['id']]);
        return ['ok' => true];
    }

    case 'tournament_fixtures': {
        $t = row('SELECT t.*, mt.overs, mt.balls_per_over, mt.players_per_side, mt.max_overs_bowler, mt.extra_bowlers, mt.extra_overs, mt.last_man_stands, mt.bouncers_per_over FROM tournaments t LEFT JOIN match_types mt ON mt.id=t.match_type_id WHERE t.id=?', [int_in($in, 'id')]);
        if (!can_edit_owned($t, $u)) fail('Not allowed', 403);
        if (val('SELECT 1 FROM matches WHERE tournament_id=? LIMIT 1', [$t['id']])) fail('Fixtures already exist for this tournament');
        $teams = array_map('intval', array_column(rows('SELECT team_id FROM tournament_teams WHERE tournament_id=?', [$t['id']]), 'team_id'));
        $pairs = [];
        $cfg = league_of_academy(nint($t['academy_id'] ?? null));
        if ($cfg) {
            $pairs = league_fixture_pairs($cfg, $teams);           // club league: same category, different group
            if (!$pairs) fail('No fixtures possible - teams need a category, and only teams from different groups play each other');
            $t['players_per_side'] = $cfg['playing'];
        } elseif ($t['format'] === 'knockout') {
            shuffle($teams);
            for ($i = 0; $i + 1 < count($teams); $i += 2) $pairs[] = [$teams[$i], $teams[$i + 1], 'Round 1 – Match ' . (count($pairs) + 1)];
        } else {
            // Round-robin with the circle method so rounds are balanced
            $list = $teams;
            if (count($list) % 2) $list[] = 0;
            $n = count($list); $num = 0;
            for ($r = 0; $r < $n - 1; $r++) {
                for ($i = 0; $i < $n / 2; $i++) {
                    $a = $list[$i]; $b = $list[$n - 1 - $i];
                    if ($a && $b) $pairs[] = [$a, $b, 'Match ' . (++$num)];
                }
                $list = array_merge([$list[0]], [$list[$n - 1]], array_slice($list, 1, $n - 2));
            }
        }
        $date = $t['start_date'] ? $t['start_date'] . ' 10:00:00' : date('Y-m-d H:i:s');
        foreach ($pairs as [$a, $b, $title]) {
            q('INSERT INTO matches (owner_id, tournament_id, match_type_id, title, team1_id, team2_id, overs, balls_per_over, players_per_side, max_overs_bowler, last_man_stands, venue, match_date, bouncers_per_over)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$t['owner_id'], $t['id'], $t['match_type_id'], $title, $a, $b,
               $t['overs'] ?? 20, $t['balls_per_over'] ?? 6, $t['players_per_side'] ?? 11, $t['max_overs_bowler'] ?? 0, $t['last_man_stands'] ?? 0, $t['venue'], $date, $t['bouncers_per_over'] ?? 1]);
        }
        if (!empty($t['extra_bowlers'])) q('UPDATE matches SET extra_bowlers=?, extra_overs=? WHERE tournament_id=?', [$t['extra_bowlers'], $t['extra_overs'], $t['id']]);
        return ['created' => count($pairs)];
    }

    case 'tournament_playoffs': {                      // v1.27: create round 1 of the playoffs from the league table
        $t = row('SELECT * FROM tournaments WHERE id=?', [int_in($in, 'id')]);
        if (!can_edit_owned($t, $u)) fail('Not allowed', 403);
        return playoff_start((int)$t['id'], !empty($in['force']));
    }

    /* ===== Academy (lessons & quizzes) ===== */
    case 'academy_progress':
        return academy_progress($u);
    case 'academy_submit':
        return academy_submit($u, $in);

    /* ===== Stats ===== */
    case 'leaderboards':
        return leaderboards(nint($in['tournament_id'] ?? null), 15, $u);
    }
    if (strpos($action, 'admin_') === 0) return admin_action($action, $u, $in);
    fail('Unknown action', 404);
}

function rollback_if_needed(): void {
    try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $e) { /* no connection */ }
}

function tournament_state(array $t): string {
    if ((int)$t['matches'] > 0 && (int)$t['done'] === (int)$t['matches']) return 'completed';
    if ((int)$t['done'] > 0 || (int)$t['live'] > 0) return 'ongoing';
    return 'upcoming';
}

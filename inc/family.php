<?php
/**
 * Family profiles, kid logins, bulk import with one-click undo, admin player corrections - v1.34
 *  - One login (a verified WhatsApp number) can hold several player profiles: its own player (users.player_id)
 *    plus family members who share that number (family_profiles). The app has a "Switch profile" control.
 *  - A family member without a phone can get their own username + password; the parent keeps managing them.
 *  - Admin import: players with team, gender, age (birth year), flat, city / state / country and WhatsApp number,
 *    recorded as one batch that can be undone with one tap (until any of those players has played a match).
 *  - Admin can correct any player: name, team, role, gender, age, number, flat, location, who manages them.
 *  - Flat / location / phone of players are only returned to the team owner, the family manager and the admin.
 */
declare(strict_types=1);

function schema_v134(): void {
    if (!val("SHOW COLUMNS FROM players LIKE 'flat'")) db()->exec("ALTER TABLE players ADD COLUMN flat VARCHAR(60) NULL AFTER birth_year, ADD COLUMN home_country CHAR(2) NULL AFTER flat,
        ADD COLUMN home_state VARCHAR(80) NULL AFTER home_country, ADD COLUMN home_city VARCHAR(80) NULL AFTER home_state, ADD COLUMN import_batch INT NULL AFTER home_city");
    if (!val("SHOW TABLES LIKE 'family_profiles'")) db()->exec("CREATE TABLE IF NOT EXISTS family_profiles (player_id INT NOT NULL PRIMARY KEY, user_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (user_id),
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!val("SHOW TABLES LIKE 'import_batches'")) db()->exec("CREATE TABLE IF NOT EXISTS import_batches (id INT AUTO_INCREMENT PRIMARY KEY, label VARCHAR(120) NOT NULL,
        created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, info MEDIUMTEXT NULL, undone_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---------- profiles of a login ---------- */

/** The login's own player first, then the family members it manages */
function user_profiles(int $uid): array {
    $own = (int)val('SELECT player_id FROM users WHERE id=?', [$uid]);
    $list = rows('SELECT p.id, p.name, p.gender, p.birth_year, t.id team_id, t.name team, t.short_name, t.color,
                    (SELECT username FROM users x WHERE x.player_id=p.id LIMIT 1) login
                  FROM players p JOIN teams t ON t.id=p.team_id
                  WHERE p.id=? OR p.id IN (SELECT player_id FROM family_profiles WHERE user_id=?)', [$own, $uid]);
    $out = [];
    foreach ($list as $p) $out[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'self' => (int)$p['id'] === $own, 'team_id' => (int)$p['team_id'], 'team' => $p['team'],
        'short_name' => $p['short_name'], 'color' => $p['color'], 'gender' => $p['gender'], 'age' => $p['birth_year'] ? (int)date('Y') - (int)$p['birth_year'] : null,
        'login' => (int)$p['id'] === $own ? null : $p['login']];
    usort($out, fn($a, $b) => [$b['self'], $b['age'] ?? 0] <=> [$a['self'], $a['age'] ?? 0]);
    return $out;
}

/** May this login manage (see private details of / create a login for) the player? */
function manages_player(array $u, int $pid): bool {
    if (!empty($u['is_admin'])) return true;
    if ((int)val('SELECT player_id FROM users WHERE id=?', [$u['id']]) === $pid) return true;
    return (bool)val('SELECT 1 FROM family_profiles WHERE player_id=? AND user_id=?', [$pid, $u['id']]);
}

/**
 * Attach every player carrying this WhatsApp number to the login that verified it: the oldest becomes the
 * login's own player (if it has none), the rest become family profiles. Returns what changed (for undo).
 */
function family_link_number(string $mobile): array {
    $chg = ['own' => [], 'family' => []];
    $u = row('SELECT id, player_id FROM users WHERE mobile=? AND mobile_verified_at IS NOT NULL', [$mobile]);
    if (!$u) return $chg;
    $ps = rows('SELECT p.id FROM players p JOIN teams t ON t.id=p.team_id WHERE p.mobile=? AND t.category=\'custom\' ORDER BY COALESCE(p.birth_year, 9999) ASC, p.id ASC', [$mobile]);
    if (!$ps) return $chg;
    $own = $u['player_id'] ? (int)$u['player_id'] : null;
    foreach ($ps as $p) {
        $pid = (int)$p['id'];
        if ($pid === $own) continue;
        if (!$own && !val('SELECT 1 FROM users WHERE player_id=?', [$pid])) {
            q('UPDATE users SET player_id=? WHERE id=?', [$pid, $u['id']]); $own = $pid;
            $chg['own'][] = ['user_id' => (int)$u['id'], 'player_id' => $pid];
            continue;
        }
        if (!val('SELECT 1 FROM family_profiles WHERE player_id=?', [$pid])) {
            q('INSERT INTO family_profiles (player_id, user_id) VALUES (?,?)', [$pid, $u['id']]);
            $chg['family'][] = ['user_id' => (int)$u['id'], 'player_id' => $pid];
        }
    }
    return $chg;
}

/** A login for a family member who has no phone: username + password made by the parent (or admin) */
function family_create_login(array $u, array $in): array {
    $pid = (int)($in['player_id'] ?? 0);
    if (!manages_player($u, $pid)) fail('You can only make a login for your own family profiles', 403);
    if ((int)val('SELECT player_id FROM users WHERE id=?', [$u['id']]) === $pid && empty($u['is_admin'])) fail("That's your own profile");
    if ($x = val('SELECT username FROM users WHERE player_id=?', [$pid])) fail("This profile already has a login: @$x");
    $p = row('SELECT id, name FROM players WHERE id=?', [$pid]);
    if (!$p) fail('Player not found');
    $un = strtolower(trim((string)($in['username'] ?? '')));
    $pw = (string)($in['password'] ?? '');
    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $un)) fail('Username: 3–30 letters, numbers, dot or underscore');
    if (strlen($pw) < 6) fail('Password must be at least 6 characters');
    if (val('SELECT 1 FROM users WHERE username=?', [$un])) fail('That username is taken');
    q('INSERT INTO users (username, password_hash, display_name, player_id, terms_accepted_at) VALUES (?,?,?,?,NOW())', [$un, password_hash($pw, PASSWORD_DEFAULT), $p['name'], $pid]);
    // the parent keeps managing this profile (switch profile), so make sure the family link exists
    if (!val('SELECT 1 FROM family_profiles WHERE player_id=?', [$pid]) && empty($u['is_admin'])) q('INSERT INTO family_profiles (player_id, user_id) VALUES (?,?)', [$pid, $u['id']]);
    return ['username' => $un, 'profiles' => user_profiles((int)$u['id'])];
}

/** Link an existing login to a player as its own profile (admin) */
function admin_link_login(int $pid, string $username): array {
    $x = row('SELECT id, username, player_id FROM users WHERE username=?', [strtolower(trim($username))]);
    if (!$x) fail('No login with that username');
    if ($o = val('SELECT username FROM users WHERE player_id=? AND id<>?', [$pid, $x['id']])) fail("That player is already linked to @$o");
    q('UPDATE users SET player_id=? WHERE id=?', [$pid, $x['id']]);
    return ['user_id' => (int)$x['id'], 'player_id' => $pid, 'old' => $x['player_id'] ? (int)$x['player_id'] : null];
}

/* ---------- private player details ---------- */

const PLAYER_PRIVATE = ['mobile', 'flat', 'home_country', 'home_state', 'home_city', 'import_batch'];

/** Hide phone / flat / location unless the viewer runs the team, manages the player, or is admin */
function player_privacy(array $p, ?array $u, bool $teamEditor): array {
    if ($teamEditor || ($u && manages_player($u, (int)$p['id']))) return $p;
    foreach (PLAYER_PRIVATE as $k) unset($p[$k]);
    return $p;
}

/* ---------- admin: import with undo ---------- */

/**
 * rows: [{team, name, gender, age, flat, city, state, country, mobile}], links: [{name, team, username}] (own login)
 * Teams are matched by exact name among custom teams. Everything done is recorded in import_batches.info.
 */
function admin_import_players(array $u, array $in): array {
    $rows = (array)($in['rows'] ?? []);
    if (!$rows || count($rows) > 1000) fail('Nothing to import (max 1000 rows)');
    $teams = [];
    foreach (rows("SELECT id, name FROM teams WHERE category='custom'") as $t) $teams[mb_strtolower(trim($t['name']))] = (int)$t['id'];
    $year = (int)date('Y');
    $plan = [];
    foreach ($rows as $i => $r) {
        $tid = $teams[mb_strtolower(trim((string)($r['team'] ?? '')))] ?? null;
        $name = trim(preg_replace('/\s+/u', ' ', (string)($r['name'] ?? '')));
        if (!$tid) fail('Row ' . ($i + 1) . ': no team called "' . ($r['team'] ?? '') . '"');
        if ($name === '') fail('Row ' . ($i + 1) . ': name missing');
        $mob = trim((string)($r['mobile'] ?? '')); $mob = $mob === '' ? null : normalize_mobile($mob);
        if (($r['mobile'] ?? '') !== '' && !$mob) fail("Row " . ($i + 1) . " ($name): WhatsApp number looks wrong");
        $age = ($r['age'] ?? '') === '' ? null : (int)$r['age'];
        $cc = strtoupper(trim((string)($r['country'] ?? '')));
        if ($cc !== '' && strlen($cc) !== 2) { $map = json_decode((string)@file_get_contents(dirname(__DIR__) . '/assets/geo/countries.json'), true) ?: []; foreach ($map as $c) if (strcasecmp($c[1], $cc) === 0) { $cc = $c[0]; break; } }
        $plan[] = [$tid, mb_substr($name, 0, 100), in_array($r['gender'] ?? '', ['M', 'F'], true) ? $r['gender'] : null, $age !== null && $age > 0 && $age < 110 ? $year - $age : null,
                   mb_substr(trim((string)($r['flat'] ?? '')), 0, 60) ?: null, strlen($cc) === 2 ? $cc : null, mb_substr(trim((string)($r['state'] ?? '')), 0, 80) ?: null,
                   mb_substr(trim((string)($r['city'] ?? '')), 0, 80) ?: null, $mob, in_array($r['role'] ?? '', PROFILE_ROLES, true) ? $r['role'] : 'Batter'];
    }
    db()->beginTransaction();
    q('INSERT INTO import_batches (label, created_by) VALUES (?,?)', [mb_substr(str_in($in, 'label', 120) ?: ('Import of ' . count($plan) . ' players'), 0, 120), $u['id']]);
    $bid = lastId();
    $ids = []; $mobiles = [];
    foreach ($plan as $p) {
        q('INSERT INTO players (team_id, name, gender, birth_year, flat, home_country, home_state, home_city, mobile, role, import_batch) VALUES (?,?,?,?,?,?,?,?,?,?,?)', array_merge($p, [$bid]));
        $ids[] = lastId();
        if ($p[8]) $mobiles[$p[8]] = true;
    }
    $own = []; $fam = [];
    // explicit logins first (e.g. a child who already has their own username)
    foreach ((array)($in['links'] ?? []) as $l) {
        $pid = (int)val('SELECT id FROM players WHERE import_batch=? AND name=? AND team_id=?', [$bid, trim((string)$l['name']), $teams[mb_strtolower(trim((string)$l['team']))] ?? 0]);
        if (!$pid) fail('Link: player "' . $l['name'] . '" not found in this import');
        $own[] = admin_link_login($pid, (string)$l['username']);
    }
    foreach (array_keys($mobiles) as $m) { $c = family_link_number((string)$m); $own = array_merge($own, $c['own']); $fam = array_merge($fam, $c['family']); }
    // explicitly linked children whose number belongs to a parent's login: the parent manages them too
    foreach ($own as $o) {
        $m = val('SELECT mobile FROM players WHERE id=?', [$o['player_id']]);
        $parent = $m ? row('SELECT id FROM users WHERE mobile=? AND mobile_verified_at IS NOT NULL AND id<>?', [$m, $o['user_id']]) : null;
        if ($parent && !val('SELECT 1 FROM family_profiles WHERE player_id=?', [$o['player_id']]) && (int)val('SELECT player_id FROM users WHERE id=?', [$parent['id']]) !== (int)$o['player_id']) {
            q('INSERT INTO family_profiles (player_id, user_id) VALUES (?,?)', [$o['player_id'], $parent['id']]); $fam[] = ['user_id' => (int)$parent['id'], 'player_id' => (int)$o['player_id']];
        }
    }
    q('UPDATE import_batches SET info=? WHERE id=?', [json_encode(['players' => $ids, 'own' => $own, 'family' => $fam]), $bid]);
    db()->commit();
    return ['batch' => $bid, 'players' => count($ids), 'linked' => count($own), 'family' => count($fam)];
}

function admin_import_list(): array {
    $out = [];
    foreach (rows('SELECT b.*, u.display_name FROM import_batches b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.id DESC LIMIT 30') as $b) {
        $i = json_decode((string)$b['info'], true) ?: [];
        $ids = array_map('intval', $i['players'] ?? []);
        $played = $ids ? (int)val('SELECT COUNT(DISTINCT player_id) FROM match_players WHERE player_id IN (' . implode(',', $ids) . ')') : 0;
        $left = $ids ? (int)val('SELECT COUNT(*) FROM players WHERE id IN (' . implode(',', $ids) . ')') : 0;
        $teams = $ids ? rows('SELECT t.name, COUNT(*) n FROM players p JOIN teams t ON t.id=p.team_id WHERE p.id IN (' . implode(',', $ids) . ') GROUP BY t.name ORDER BY t.name') : [];
        $out[] = ['id' => (int)$b['id'], 'label' => $b['label'], 'by' => $b['display_name'], 'at' => $b['created_at'], 'undone_at' => $b['undone_at'],
                  'players' => count($ids), 'still_there' => $left, 'played' => $played, 'linked' => count($i['own'] ?? []), 'family' => count($i['family'] ?? []), 'teams' => $teams];
    }
    return ['imports' => $out];
}

/** One-tap undo: remove the batch's players and the logins / family links it made */
function admin_import_undo(int $bid): array {
    $b = row('SELECT * FROM import_batches WHERE id=?', [$bid]);
    if (!$b) fail('Import not found');
    if ($b['undone_at']) fail('This import was already undone');
    $i = json_decode((string)$b['info'], true) ?: [];
    $ids = array_map('intval', $i['players'] ?? []);
    if ($ids && val('SELECT 1 FROM match_players WHERE player_id IN (' . implode(',', $ids) . ') LIMIT 1'))
        fail('Some of these players have already played a match - fix them one by one under Admin → Players instead');
    db()->beginTransaction();
    foreach ($i['own'] ?? [] as $o) q('UPDATE users SET player_id=? WHERE id=? AND player_id=?', [$o['old'] ?? null, $o['user_id'], $o['player_id']]);
    if ($ids) {
        $in_ = implode(',', $ids);
        q("UPDATE users SET player_id=NULL WHERE player_id IN ($in_)");
        q("DELETE FROM family_profiles WHERE player_id IN ($in_)");
        q("UPDATE teams t JOIN players p ON p.team_id=t.id AND p.name=t.captain SET t.captain=NULL WHERE p.id IN ($in_)");
        q("DELETE FROM players WHERE id IN ($in_)");
    }
    q('UPDATE import_batches SET undone_at=NOW() WHERE id=?', [$bid]);
    db()->commit();
    return ['removed' => count($ids)] + admin_import_list();
}

/* ---------- admin: correct any player ---------- */

function admin_player_search(array $in): array {
    $q = trim((string)($in['q'] ?? '')); $team = (int)($in['team_id'] ?? 0);
    $where = ["t.category='custom'"]; $p = [];
    if ($team) { $where[] = 't.id=?'; $p[] = $team; }
    if ($q !== '') {
        $mob = preg_match('/^[+\d][\d\s\-()]{8,}$/', $q) ? normalize_mobile($q) : null;
        if ($mob) { $where[] = 'p.mobile=?'; $p[] = $mob; } else { $where[] = '(LOCATE(?, p.name) > 0 OR LOCATE(?, p.flat) > 0)'; $p[] = $q; $p[] = $q; }
    }
    $list = rows('SELECT p.*, t.name team, t.short_name, t.color, (SELECT username FROM users x WHERE x.player_id=p.id LIMIT 1) login,
                    (SELECT u.username FROM family_profiles f JOIN users u ON u.id=f.user_id WHERE f.player_id=p.id) managed_by,
                    (SELECT COUNT(DISTINCT match_id) FROM match_players mp WHERE mp.player_id=p.id) matches
                  FROM players p JOIN teams t ON t.id=p.team_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.name, p.name LIMIT 300', $p);
    foreach ($list as &$r) { $r['age'] = $r['birth_year'] ? (int)date('Y') - (int)$r['birth_year'] : null; $r['mobile_pretty'] = $r['mobile'] ? pretty_mobile($r['mobile']) : null; }
    unset($r);
    return ['players' => $list, 'teams' => rows("SELECT id, name, short_name FROM teams WHERE category='custom' ORDER BY name")];
}

function admin_player_save(array $in): array {
    $p = row('SELECT p.*, t.category FROM players p JOIN teams t ON t.id=p.team_id WHERE p.id=?', [(int)($in['id'] ?? 0)]);
    if (!$p) fail('Player not found');
    $name = trim(preg_replace('/\s+/u', ' ', (string)($in['name'] ?? '')));
    if ($name === '') fail('Enter a name');
    $team = (int)($in['team_id'] ?? $p['team_id']);
    if ($team !== (int)$p['team_id'] && !val("SELECT 1 FROM teams WHERE id=? AND category='custom'", [$team])) fail('Pick one of the custom teams');
    $mobRaw = trim((string)($in['mobile'] ?? '')); $mob = $mobRaw === '' ? null : normalize_mobile($mobRaw);
    if ($mobRaw !== '' && !$mob) fail('That WhatsApp number does not look right');
    $age = trim((string)($in['age'] ?? ''));
    $cc = strtoupper(trim((string)($in['home_country'] ?? '')));
    q('UPDATE players SET name=?, team_id=?, role=?, gender=?, birth_year=?, mobile=?, flat=?, home_country=?, home_state=?, home_city=? WHERE id=?', [
        mb_substr($name, 0, 100), $team, in_array($in['role'] ?? '', PROFILE_ROLES, true) ? $in['role'] : $p['role'], in_array($in['gender'] ?? '', ['M', 'F'], true) ? $in['gender'] : null,
        $age === '' ? null : (int)date('Y') - max(1, min(109, (int)$age)), $mob, mb_substr(trim((string)($in['flat'] ?? '')), 0, 60) ?: null,
        strlen($cc) === 2 ? $cc : null, mb_substr(trim((string)($in['home_state'] ?? '')), 0, 80) ?: null, mb_substr(trim((string)($in['home_city'] ?? '')), 0, 80) ?: null, $p['id']]);
    if ($p['name'] !== $name) q('UPDATE teams SET captain=? WHERE id=? AND captain=?', [$name, $p['team_id'], $p['name']]);
    // who manages / owns this profile
    if (array_key_exists('managed_by', $in)) {
        $mu = trim((string)$in['managed_by']);
        q('DELETE FROM family_profiles WHERE player_id=?', [$p['id']]);
        if ($mu !== '') { $x = row('SELECT id FROM users WHERE username=?', [strtolower($mu)]); if (!$x) fail("No login @$mu"); q('INSERT INTO family_profiles (player_id, user_id) VALUES (?,?)', [$p['id'], $x['id']]); }
    }
    if (array_key_exists('login', $in)) {
        $lu = trim((string)$in['login']);
        $cur = val('SELECT username FROM users WHERE player_id=?', [$p['id']]);
        if ($lu === '' && $cur) q('UPDATE users SET player_id=NULL WHERE player_id=?', [$p['id']]);
        elseif ($lu !== '' && strtolower($lu) !== $cur) { if ($cur) q('UPDATE users SET player_id=NULL WHERE player_id=?', [$p['id']]); admin_link_login((int)$p['id'], $lu); }
    }
    return admin_player_search(['q' => (string)($in['_q'] ?? ''), 'team_id' => (int)($in['_team'] ?? 0)]);
}

function admin_player_delete(int $pid): array {
    if (val('SELECT 1 FROM match_players WHERE player_id=? LIMIT 1', [$pid])) fail('This player has played matches - move or rename them instead of deleting');
    $p = row('SELECT * FROM players WHERE id=?', [$pid]);
    if (!$p) fail('Player not found');
    q('UPDATE users SET player_id=NULL WHERE player_id=?', [$pid]);
    q('UPDATE teams SET captain=NULL WHERE id=? AND captain=?', [$p['team_id'], $p['name']]);
    q('DELETE FROM players WHERE id=?', [$pid]);
    return ['ok' => true];
}

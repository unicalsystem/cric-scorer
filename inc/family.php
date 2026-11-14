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
                    (SELECT username FROM users x WHERE x.player_id=p.id AND (x.placeholder=0 OR x.mobile IS NOT NULL) LIMIT 1) login
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
    $ex = row('SELECT id, username, placeholder, mobile FROM users WHERE player_id=?', [$pid]);
    if ($ex && (!$ex['placeholder'] || $ex['mobile'])) fail("This profile already has a login: @{$ex['username']}");
    $p = row('SELECT id, name FROM players WHERE id=?', [$pid]);
    if (!$p) fail('Player not found');
    $un = strtolower(trim((string)($in['username'] ?? '')));
    $pw = (string)($in['password'] ?? '');
    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $un)) fail('Username: 3–30 letters, numbers, dot or underscore');
    if (strlen($pw) < 6) fail('Password must be at least 6 characters');
    if (val('SELECT 1 FROM users WHERE username=? AND id<>?', [$un, $ex['id'] ?? 0])) fail('That username is taken');
    if ($ex) q('UPDATE users SET username=?, password_hash=?, placeholder=0, terms_accepted_at=NOW() WHERE id=?', [$un, password_hash($pw, PASSWORD_DEFAULT), $ex['id']]);   // the account made at import
    else q('INSERT INTO users (username, password_hash, display_name, player_id, terms_accepted_at) VALUES (?,?,?,?,NOW())', [$un, password_hash($pw, PASSWORD_DEFAULT), $p['name'], $pid]);
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
    foreach (rows("SELECT b.*, u.display_name FROM import_batches b LEFT JOIN users u ON u.id=b.created_by WHERE b.info IS NULL OR b.info NOT LIKE '{\"merge\"%' ORDER BY b.id DESC LIMIT 30") as $b) {
        $i = json_decode((string)$b['info'], true) ?: [];
        $ids = array_map('intval', $i['players'] ?? []);
        $played = $ids ? (int)val('SELECT COUNT(DISTINCT player_id) FROM match_players WHERE player_id IN (' . implode(',', $ids) . ')') : 0;
        $left = $ids ? (int)val('SELECT COUNT(*) FROM players WHERE id IN (' . implode(',', $ids) . ')') : 0;
        $teams = $ids ? rows('SELECT t.name, COUNT(*) n FROM players p JOIN teams t ON t.id=p.team_id WHERE p.id IN (' . implode(',', $ids) . ') GROUP BY t.name ORDER BY t.name') : [];
        $uids = array_map('intval', $i['users'] ?? []);
        $claimed = $uids ? (int)val('SELECT COUNT(*) FROM users WHERE placeholder=0 AND id IN (' . implode(',', $uids) . ')') : 0;
        $out[] = ['id' => (int)$b['id'], 'label' => $b['label'], 'accounts' => count($uids), 'claimed' => $claimed, 'for_batch' => $i['for_batch'] ?? null, 'by' => $b['display_name'], 'at' => $b['created_at'], 'undone_at' => $b['undone_at'],
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
    $uids = array_map('intval', $i['users'] ?? []);
    if ($uids && ($n = (int)val('SELECT COUNT(*) FROM users WHERE placeholder=0 AND id IN (' . implode(',', $uids) . ')')))
        fail("$n of these accounts have already been claimed by their players - remove them one by one instead");
    if ($ids && ($acc = row("SELECT id FROM import_batches WHERE undone_at IS NULL AND info LIKE ?", ['%\"for_batch\":' . $bid . '}%'])))
        fail('Undo "Accounts for…" (import #' . $acc['id'] . ') first');
    db()->beginTransaction();
    foreach ($i['family'] ?? [] as $f) q('DELETE FROM family_profiles WHERE player_id=? AND user_id=?', [$f['player_id'], $f['user_id']]);
    if ($uids) q('DELETE FROM users WHERE placeholder=1 AND id IN (' . implode(',', $uids) . ')');
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

/* ---------- v1.35: accounts for imported players (claimed later with a WhatsApp code) ---------- */

function schema_v135(): void {
    if (!val("SHOW COLUMNS FROM users LIKE 'placeholder'")) db()->exec("ALTER TABLE users ADD COLUMN placeholder TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin");
}

/** name + last 4 digits of the number (or the flat's digits), e.g. arjunj9750 - unique, never the full number */
function username_for(string $name, ?string $mobile, ?string $flat): string {
    $base = substr(preg_replace('/[^a-z0-9]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name)), 0, 20) ?: 'player';
    $tail = $mobile ? substr($mobile, -4) : (preg_replace('/\D/', '', (string)$flat) ?: '');
    $un = $base . $tail; if (strlen($un) < 3) $un .= 'rv';
    $try = $un; $n = 1;
    while (val('SELECT 1 FROM users WHERE username=?', [$try])) $try = $un . '_' . (++$n);
    return $try;
}

/**
 * Give every player of an import who has no login a registered account (shown under players in the app).
 * The oldest person on each WhatsApp number holds that number (unverified) and claims the account with a WhatsApp
 * code; the others on the number get their own account without a number and become that person's family profiles.
 */
function admin_create_accounts(array $u, int $batchId): array {
    $b = row('SELECT * FROM import_batches WHERE id=? AND undone_at IS NULL', [$batchId]);
    if (!$b) fail('Import not found');
    $pids = array_map('intval', (json_decode((string)$b['info'], true) ?: [])['players'] ?? []);
    if (!$pids) fail('No players in that import');
    $ps = rows('SELECT * FROM players WHERE id IN (' . implode(',', $pids) . ') ORDER BY COALESCE(birth_year, 9999) ASC, id ASC');
    db()->beginTransaction();
    $made = []; $fam = []; $head = [];                   // number => user id that holds it
    $year = (int)date('Y');
    foreach ($ps as $p) {
        if (val('SELECT 1 FROM users WHERE player_id=?', [$p['id']])) {   // already has a login (e.g. you, @aarush)
            if ($p['mobile'] && !isset($head[$p['mobile']]) && ($x = val('SELECT id FROM users WHERE mobile=?', [$p['mobile']]))) $head[$p['mobile']] = (int)$x;
            continue;
        }
        $mob = $p['mobile'];
        $holder = $mob ? ($head[$mob] ?? (int)val('SELECT id FROM users WHERE mobile=?', [$mob])) : 0;
        $takeNumber = $mob && !$holder;                  // first (oldest) on a free number holds it
        $age = $p['birth_year'] ? $year - (int)$p['birth_year'] : null;
        q('INSERT INTO users (username, password_hash, display_name, placeholder, mobile, player_id, gender, dob, dob_exact, country, state, city, address) VALUES (?,?,?,1,?,?,?,?,0,?,?,?,?)', [
            username_for($p['name'], $mob, $p['flat']), '', $p['name'], $takeNumber ? $mob : null, $p['id'], $p['gender'],
            $age !== null ? (new DateTime('today'))->modify("-$age years")->format('Y-m-d') : null, $p['home_country'], $p['home_state'], $p['home_city'], $p['flat']]);
        $uid = lastId(); $made[] = $uid;
        if ($takeNumber) $head[$mob] = $uid;
        elseif ($holder && !val('SELECT 1 FROM family_profiles WHERE player_id=?', [$p['id']])) {   // a family member on someone else's number
            q('INSERT INTO family_profiles (player_id, user_id) VALUES (?,?)', [$p['id'], $holder]); $fam[] = ['user_id' => $holder, 'player_id' => (int)$p['id']];
        }
    }
    q('INSERT INTO import_batches (label, created_by, info) VALUES (?,?,?)', ['Accounts for: ' . $b['label'], $u['id'], json_encode(['players' => [], 'users' => $made, 'family' => $fam, 'for_batch' => $batchId])]);
    db()->commit();
    return ['accounts' => count($made), 'family' => count($fam), 'holders' => count($head)];
}

/* ---------- v1.37: admin merges two accounts of the same person (e.g. the one made at import + one made with Gmail) ---------- */

const MERGE_FIELDS = ['mobile', 'mobile_verified_at', 'google_sub', 'email', 'avatar_url', 'avatar_preset', 'password_hash', 'bio', 'role', 'batting_style', 'bowling_style',
                      'experience_years', 'dob', 'dob_exact', 'gender', 'address', 'country', 'state', 'city', 'terms_accepted_at'];

function merge_user_summary(array $x): array {
    $id = (int)$x['id'];
    $pl = $x['player_id'] ? row('SELECT p.name, t.name team FROM players p JOIN teams t ON t.id=p.team_id WHERE p.id=?', [$x['player_id']]) : null;
    return ['id' => $id, 'username' => $x['username'], 'display_name' => $x['display_name'], 'created_at' => $x['created_at'] ?? null, 'last_seen_at' => $x['last_seen_at'] ?? null,
        'mobile' => $x['mobile'] ? pretty_mobile($x['mobile']) : null, 'mobile_verified' => !empty($x['mobile_verified_at']), 'google' => $x['email'] ?? (!empty($x['google_sub']) ? 'Google' : null),
        'has_password' => (string)$x['password_hash'] !== '', 'placeholder' => !empty($x['placeholder']), 'is_admin' => !empty($x['is_admin']),
        'player' => $pl ? $pl['name'] . ' · ' . $pl['team'] : null,
        'family' => (int)val('SELECT COUNT(*) FROM family_profiles WHERE user_id=?', [$id]),
        'matches' => (int)val('SELECT COUNT(*) FROM matches WHERE owner_id=? OR scorer_id=?', [$id, $id]),
        'teams' => (int)val('SELECT COUNT(*) FROM teams WHERE owner_id=?', [$id]),
        'followers' => (int)val('SELECT COUNT(*) FROM follows WHERE followee_id=?', [$id])];
}

function admin_merge_preview(array $in): array {
    $a = row('SELECT * FROM users WHERE username=?', [strtolower(trim((string)($in['a'] ?? '')))]);
    $b = row('SELECT * FROM users WHERE username=?', [strtolower(trim((string)($in['b'] ?? '')))]);
    if (!$a || !$b) fail('Enter two existing usernames');
    if ((int)$a['id'] === (int)$b['id']) fail('That is the same account twice');
    return ['a' => merge_user_summary($a), 'b' => merge_user_summary($b)];
}

/**
 * Fold account $dropId into $keepId: sign-in methods (number, Google, password), the linked player, family profiles,
 * matches, teams, tournaments, follows and everything else move over; empty profile details are filled from the other
 * account; then the other account is deleted. $username: 'keep' or 'drop' - which username survives.
 */
function admin_merge_users(array $u, array $in): array {
    $keep = row('SELECT * FROM users WHERE id=?', [(int)($in['keep'] ?? 0)]);
    $drop = row('SELECT * FROM users WHERE id=?', [(int)($in['drop'] ?? 0)]);
    if (!$keep || !$drop || (int)$keep['id'] === (int)$drop['id']) fail('Pick two different accounts');
    if (!empty($drop['is_admin']) && empty($keep['is_admin'])) fail('Keep the administrator account, not the other one');
    $K = (int)$keep['id']; $D = (int)$drop['id'];
    db()->beginTransaction();
    // 1. profile fields: copy what the kept account is missing (unique ones are freed on the other account first)
    $set = []; $vals = [];
    foreach (MERGE_FIELDS as $f) {
        if (!array_key_exists($f, $keep)) continue;
        $empty = $keep[$f] === null || $keep[$f] === '' || ($f === 'dob_exact' && !$keep['dob']);
        if ($empty && $drop[$f] !== null && $drop[$f] !== '') { $set[] = "$f=?"; $vals[] = $drop[$f]; }
    }
    if ($drop['mobile'] && $keep['mobile'] && $drop['mobile'] !== $keep['mobile'] && !$keep['mobile_verified_at'] && $drop['mobile_verified_at']) {   // prefer the verified number
        $set[] = 'mobile=?'; $vals[] = $drop['mobile']; $set[] = 'mobile_verified_at=?'; $vals[] = $drop['mobile_verified_at'];
    }
    $usernameFromDrop = ($in['username'] ?? 'keep') === 'drop';
    q('UPDATE users SET mobile=NULL, google_sub=NULL, player_id=NULL, username=? WHERE id=?', ['merged_' . $D . '_' . bin2hex(random_bytes(3)), $D]);
    if ($usernameFromDrop) { $set[] = 'username=?'; $vals[] = $drop['username']; }
    if (!empty($drop['is_admin'])) $set[] = 'is_admin=1';
    $set[] = 'placeholder=?'; $vals[] = (!empty($keep['placeholder']) && !empty($drop['placeholder'])) ? 1 : 0;
    // 2. linked players: keep the kept account's own player; the other one's player becomes a family profile
    $dropPid = $drop['player_id'] ? (int)$drop['player_id'] : null;
    if (!$keep['player_id'] && $dropPid) { $set[] = 'player_id=?'; $vals[] = $dropPid; $dropPid = null; }
    q('UPDATE users SET ' . implode(', ', $set) . ' WHERE id=?', array_merge($vals, [$K]));
    if ($dropPid && !val('SELECT 1 FROM family_profiles WHERE player_id=?', [$dropPid])) q('INSERT INTO family_profiles (player_id, user_id) VALUES (?,?)', [$dropPid, $K]);
    // 3. everything the other account owns or did
    foreach (['academies.owner_id', 'academy_students.user_id', 'auc_events.user_id', 'auc_rooms.host_id', 'grounds.owner_id', 'lookings.user_id', 'looking_replies.user_id',
              'matches.owner_id', 'matches.scorer_id', 'match_log.user_id', 'match_photos.user_id', 'match_types.owner_id', 'teams.owner_id', 'tournaments.owner_id',
              'tickets.user_id', 'import_batches.created_by', 'games.p0', 'games.p1', 'family_profiles.user_id'] as $tc) {
        [$t, $c] = explode('.', $tc);
        if (val("SHOW TABLES LIKE '$t'") && val("SHOW COLUMNS FROM `$t` LIKE '$c'")) q("UPDATE `$t` SET `$c`=? WHERE `$c`=?", [$K, $D]);
    }
    q('UPDATE matches SET scorer_id=NULL WHERE scorer_id=owner_id');
    q('DELETE FROM family_profiles WHERE user_id=? AND player_id=(SELECT player_id FROM users WHERE id=?)', [$K, $K]);   // own player is not a family profile
    // tables with a unique key per user: move what doesn't clash, drop the rest
    foreach ([['follows', 'follower_id'], ['follows', 'followee_id'], ['team_access', 'user_id'], ['feature_users', 'user_id'], ['academy_progress', 'user_id'],
              ['auc_participants', 'user_id'], ['game_profiles', 'user_id']] as [$t, $c]) {
        if (!val("SHOW TABLES LIKE '$t'")) continue;
        q("UPDATE IGNORE `$t` SET `$c`=? WHERE `$c`=?", [$K, $D]);
        q("DELETE FROM `$t` WHERE `$c`=?", [$D]);
    }
    q('DELETE FROM follows WHERE follower_id=followee_id');
    q('DELETE FROM team_access WHERE user_id=? AND team_id IN (SELECT id FROM teams WHERE owner_id=?)', [$K, $K]);
    q('DELETE FROM user_tokens WHERE user_id=?', [$D]);
    q('DELETE FROM otp_tokens WHERE user_id=?', [$D]);
    q('DELETE FROM users WHERE id=?', [$D]);
    q('INSERT INTO import_batches (label, created_by, info, undone_at) VALUES (?,?,?,NOW())', ['Merged @' . $drop['username'] . ' into @' . ($usernameFromDrop ? $drop['username'] : $keep['username']) . ' (cannot be undone)', $u['id'],
      json_encode(['merge' => ['kept' => $K, 'removed' => $D, 'removed_username' => $drop['username']]])]);
    db()->commit();
    return ['user' => merge_user_summary(row('SELECT * FROM users WHERE id=?', [$K]))];
}

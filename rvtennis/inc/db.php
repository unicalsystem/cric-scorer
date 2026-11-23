<?php
/* RV Tennis - database, JSON output and sign-in.
   Accounts are shared with Cric-Scorer (tables users, user_tokens, otp_tokens in the same database), so a club
   member has one account for both sports. Everything tennis keeps for itself is in tables starting with tn_. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/tour.php';

if (!function_exists('mb_substr')) { function mb_substr($s, $start, $len = null) { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function lastId(): int { return (int)db()->lastInsertId(); }

class ApiError extends Exception {
    public int $status;
    public function __construct(string $msg, int $status = 400) { parent::__construct($msg); $this->status = $status; }
}
function fail(string $msg, int $status = 400): void { throw new ApiError($msg, $status); }
function str_in(array $in, string $k, int $max = 150): string { return mb_substr(trim((string)($in[$k] ?? '')), 0, $max); }
function int_in(array $in, string $k, int $def = 0): int { return isset($in[$k]) && $in[$k] !== '' ? (int)$in[$k] : $def; }

/* ---------- Tables (created on first use, upgraded in place) ---------- */
function tn_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if (!val("SHOW TABLES LIKE 'tn_matches'")) tn_schema_v1();
    if (!val("SHOW TABLES LIKE 'tn_teams'")) tn_schema_teams();
    if (!val("SHOW TABLES LIKE 'tn_meta'")) {
        db()->exec("CREATE TABLE IF NOT EXISTS tn_meta (k VARCHAR(40) PRIMARY KEY, v TEXT NULL, at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        tn_fix_first_to_five();
    }
}
/** v1.1: club teams (with their logos) and which team each player belongs to */
function tn_schema_teams(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS tn_teams (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL, logo VARCHAR(80) NULL, color CHAR(7) NOT NULL DEFAULT '#0f2440',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_team (name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!val("SHOW COLUMNS FROM tn_players LIKE 'team_id'")) db()->exec("ALTER TABLE tn_players ADD COLUMN team_id INT NULL AFTER gender, ADD INDEX idx_team (team_id)");
    foreach ([['Phoenix', 'phoenix.jpg', '#d6281c'], ['Rangers', 'rangers.jpg', '#d9a400'], ['Titans', 'titans.jpg', '#1c7fd6'], ['Warriors', 'warriors.jpg', '#e8641b']] as $t)
        q('INSERT IGNORE INTO tn_teams (name, logo, color) VALUES (?,?,?)', ['' . $t[0], 'assets/teams/' . $t[1], $t[2]]);
}
function tn_schema_v1(): void {
    $e = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    // The shared account tables exist already on the club server; these lines only matter on a fresh database.
    db()->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
        display_name VARCHAR(100) NOT NULL, email VARCHAR(190) NULL, mobile VARCHAR(20) NULL, mobile_verified_at DATETIME NULL, google_sub VARCHAR(64) NULL,
        avatar_url VARCHAR(255) NULL, terms_accepted_at DATETIME NULL, is_admin TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_google_sub (google_sub), UNIQUE KEY uniq_mobile (mobile), INDEX idx_email (email))$e");
    db()->exec("CREATE TABLE IF NOT EXISTS user_tokens (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL,
        INDEX (user_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)$e");
    db()->exec("CREATE TABLE IF NOT EXISTS otp_tokens (id INT AUTO_INCREMENT PRIMARY KEY, token_hash CHAR(64) NOT NULL, code_hash VARCHAR(255) NOT NULL, mobile VARCHAR(20) NOT NULL,
        purpose VARCHAR(10) NOT NULL, user_id INT NULL, data TEXT NULL, tries TINYINT NOT NULL DEFAULT 0, used_at DATETIME NULL, expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (token_hash), INDEX (mobile, created_at))$e");

    if (!val("SHOW COLUMNS FROM users LIKE 'mobile'"))   // Cric-Scorer adds these itself from v1.14; only an older database needs them here
        db()->exec("ALTER TABLE users ADD COLUMN mobile VARCHAR(20) NULL AFTER email, ADD COLUMN mobile_verified_at DATETIME NULL AFTER mobile, ADD UNIQUE KEY uniq_mobile (mobile)");
    db()->exec("CREATE TABLE IF NOT EXISTS tn_tries (k VARCHAR(80) NOT NULL, at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (k, at))$e");
    db()->exec("CREATE TABLE IF NOT EXISTS tn_admins (user_id INT PRIMARY KEY, added_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)$e");
    db()->exec("CREATE TABLE IF NOT EXISTS tn_players (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, gender CHAR(1) NULL, user_id INT NULL,
        created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_name (name), INDEX (user_id))$e");
    db()->exec("CREATE TABLE IF NOT EXISTS tn_tours (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, kind ENUM('rr','ko') NOT NULL, doubles TINYINT(1) NOT NULL DEFAULT 0,
        format TEXT NOT NULL, grp_count TINYINT NOT NULL DEFAULT 1, owner_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)$e");
    db()->exec("CREATE TABLE IF NOT EXISTS tn_entries (id INT AUTO_INCREMENT PRIMARY KEY, tour_id INT NOT NULL, pos SMALLINT NOT NULL, grp TINYINT NOT NULL DEFAULT 0,
        p1 INT NOT NULL, p2 INT NULL, name VARCHAR(170) NOT NULL, INDEX (tour_id), FOREIGN KEY (tour_id) REFERENCES tn_tours(id) ON DELETE CASCADE)$e");
    db()->exec("CREATE TABLE IF NOT EXISTS tn_matches (id INT AUTO_INCREMENT PRIMARY KEY, owner_id INT NOT NULL, scorer_id INT NULL,
        tour_id INT NULL, round TINYINT NULL, slot SMALLINT NULL, grp TINYINT NULL, entry_a INT NULL, entry_b INT NULL,
        doubles TINYINT(1) NOT NULL DEFAULT 0, a1 INT NULL, a2 INT NULL, b1 INT NULL, b2 INT NULL, a_name VARCHAR(170) NOT NULL DEFAULT '', b_name VARCHAR(170) NOT NULL DEFAULT '',
        format TEXT NOT NULL, first_srv TINYINT(1) NOT NULL DEFAULT 0, court VARCHAR(40) NULL,
        status ENUM('scheduled','live','done','bye') NOT NULL DEFAULT 'scheduled', winner TINYINT NULL, summary VARCHAR(90) NULL,
        events MEDIUMTEXT NULL, state MEDIUMTEXT NULL, sched_at DATETIME NULL, started_at DATETIME NULL, finished_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (status, updated_at), INDEX (tour_id, round, slot), INDEX (a1), INDEX (b1),
        FOREIGN KEY (tour_id) REFERENCES tn_tours(id) ON DELETE CASCADE)$e");
}

/* ---------- Sign-in (cookie + user_tokens row, the same scheme Cric-Scorer uses) ---------- */
function tn_secure(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; }
function tn_login(int $uid): void {
    $tok = bin2hex(random_bytes(32));
    q('INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 90 DAY))', [$uid, hash('sha256', $tok)]);
    setcookie('tn_rt', lastId() . ':' . $tok, ['expires' => time() + 90 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => tn_secure()]);
}
function tn_logout(): void {
    if (!empty($_COOKIE['tn_rt']) && strpos($_COOKIE['tn_rt'], ':') !== false) q('DELETE FROM user_tokens WHERE id=?', [(int)explode(':', $_COOKIE['tn_rt'], 2)[0]]);
    setcookie('tn_rt', '', ['expires' => time() - 3600, 'path' => '/']);
}
function tn_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    $c = (string)($_COOKIE['tn_rt'] ?? '');
    if (strpos($c, ':') !== false) {
        [$tid, $tok] = explode(':', $c, 2);
        $t = row('SELECT * FROM user_tokens WHERE id=? AND expires_at > NOW()', [(int)$tid]);
        if ($t && hash_equals($t['token_hash'], hash('sha256', $tok))) $u = row('SELECT * FROM users WHERE id=?', [$t['user_id']]);
    }
    if ($u) $u['tn_admin'] = tn_is_admin($u);
    return $u;
}
function tn_require(): array { $u = tn_user(); if (!$u) fail('Please sign in first', 401); return $u; }
function tn_require_admin(): array { $u = tn_require(); if (!$u['tn_admin']) fail('Only a club admin can do that', 403); return $u; }

function tn_mobile(?string $raw): ?string {
    $d = preg_replace('/\D/', '', (string)$raw);
    if ($d === '') return null;
    if (strlen($d) === 10) $d = DEFAULT_COUNTRY_CODE . $d;
    elseif (strlen($d) === 11 && $d[0] === '0') $d = DEFAULT_COUNTRY_CODE . substr($d, 1);
    return strlen($d) < 10 || strlen($d) > 15 ? null : $d;
}
function tn_mask(?string $m): string { return $m ? '+' . substr($m, 0, 2) . ' ••••• ' . substr($m, -3) : ''; }

/** A club admin: listed in config (verified Google e-mail or verified WhatsApp number), or added in the app */
function tn_is_admin(array $u): bool {
    $hashes = array_filter(array_map('trim', explode(',', strtolower((string)TN_ADMIN_HASHES))));
    $emails = array_filter(array_map('trim', explode(',', strtolower((string)TN_ADMIN_EMAILS))));
    $mobiles = array_filter(array_map('tn_mobile', explode(',', (string)TN_ADMIN_MOBILES)));
    $email = strtolower(trim((string)($u['email'] ?? '')));
    if ($email !== '' && !empty($u['google_sub']) && (in_array($email, $emails, true) || in_array(hash('sha256', $email), $hashes, true))) return true;
    $mob = (string)($u['mobile'] ?? '');
    if ($mob !== '' && !empty($u['mobile_verified_at']) && (in_array($mob, $mobiles, true) || in_array(hash('sha256', $mob), $hashes, true))) return true;
    return (bool)val('SELECT 1 FROM tn_admins WHERE user_id=?', [$u['id']]);
}
function tn_public_user(?array $u): ?array {
    if (!$u) return null;
    return ['id' => (int)$u['id'], 'name' => $u['display_name'], 'username' => $u['username'], 'avatar' => preg_match('#^https://#', (string)($u['avatar_url'] ?? '')) ? $u['avatar_url'] : null,
        'admin' => !empty($u['tn_admin']), 'google' => !empty($u['google_sub']), 'mobile' => !empty($u['mobile']) ? tn_mask($u['mobile']) : null];
}
function tn_username(string $base): string {
    $base = substr(trim(preg_replace('/[^a-z0-9_.]/', '', strtolower($base)), '.'), 0, 26);
    if (strlen($base) < 3) $base = 'player' . $base;
    $name = $base; $n = 1;
    while (val('SELECT 1 FROM users WHERE username=?', [$name])) $name = $base . (++$n);
    return $name;
}

function tn_http(string $url, ?string $body = null, array $headers = []): array {
    $headers[] = 'Accept: application/json';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => $headers]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $res = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    } else {
        $o = ['http' => ['timeout' => 15, 'ignore_errors' => true, 'header' => implode("\r\n", $headers) . "\r\n"]];
        if ($body !== null) { $o['http']['method'] = 'POST'; $o['http']['content'] = $body; }
        $res = @file_get_contents($url, false, stream_context_create($o));
        $http = 0; foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $http = (int)$m[1];
    }
    $j = is_string($res) ? json_decode($res, true) : null;
    return ['http' => $http, 'json' => is_array($j) ? $j : null];
}

/* ---------- Sign in with Google ---------- */
function tn_google_client(): string { return trim((string)TN_GOOGLE_CLIENT_ID) ?: trim((string)GOOGLE_CLIENT_ID); }
function tn_google(array $in): array {
    if (tn_google_client() === '') fail('Google sign-in is not set up on this server');
    $cred = (string)($in['credential'] ?? '');
    if ($cred === '' || strlen($cred) > 4096) fail('Google sign-in failed - please try again');
    $t = tn_http(GOOGLE_TOKENINFO_URL . '?id_token=' . rawurlencode($cred))['json'];
    if (!$t) fail('Could not reach Google to check your sign-in. Please try again.');
    if (!empty($t['error']) || !empty($t['error_description']) || (int)($t['exp'] ?? 0) < time()) fail('Google sign-in expired - please try again');
    if (($t['aud'] ?? '') !== tn_google_client() || !in_array($t['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) fail('Google sign-in could not be verified');
    if (!in_array($t['email_verified'] ?? false, [true, 'true'], true) || empty($t['sub']) || empty($t['email'])) fail('Your Google e-mail address is not verified');
    $email = strtolower((string)$t['email']); $name = trim((string)($t['name'] ?? ''));
    $u = row('SELECT * FROM users WHERE google_sub=?', [(string)$t['sub']]);
    if (!$u) {
        $u = row('SELECT * FROM users WHERE email=? AND google_sub IS NULL', [$email]);
        if ($u) q('UPDATE users SET google_sub=? WHERE id=?', [(string)$t['sub'], $u['id']]);
    }
    if (!$u) {
        if (empty($in['consent'])) return ['needs_consent' => true, 'name' => $name, 'email' => $email];
        q('INSERT INTO users (username, password_hash, display_name, email, google_sub, avatar_url, terms_accepted_at) VALUES (?,?,?,?,?,?,NOW())',
          [tn_username(explode('@', $email)[0]), '', mb_substr($name ?: explode('@', $email)[0], 0, 100), $email, (string)$t['sub'], mb_substr((string)($t['picture'] ?? ''), 0, 255) ?: null]);
        $u = ['id' => lastId()];
    }
    tn_login((int)$u['id']);
    $u = row('SELECT * FROM users WHERE id=?', [$u['id']]); $u['tn_admin'] = tn_is_admin($u);
    return ['user' => tn_public_user($u)];
}

/* ---------- Sign in with WhatsApp (a 6-digit code sent through the Meta Cloud API) ---------- */
function tn_wa_enabled(): bool { return trim((string)WHATSAPP_TOKEN) !== '' && trim((string)WHATSAPP_PHONE_ID) !== ''; }
function tn_wa_send(string $mobile, string $code, string $token): array {
    if (!tn_wa_enabled()) return ['ok' => false, 'error' => 'WhatsApp sign-in is not set up on this server yet'];
    $payload = ['messaging_product' => 'whatsapp', 'to' => $mobile, 'type' => 'template', 'template' => [
        'name' => WHATSAPP_TEMPLATE, 'language' => ['code' => WHATSAPP_TEMPLATE_LANG],
        'components' => [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => WHATSAPP_TEMPLATE_KIND === 'code' ? $code : $token]]],
        ]]];
    $r = tn_http(rtrim(WHATSAPP_API_URL, '/') . '/' . WHATSAPP_PHONE_ID . '/messages', json_encode($payload), ['Content-Type: application/json', 'Authorization: Bearer ' . WHATSAPP_TOKEN]);
    if ($r['http'] >= 200 && $r['http'] < 300 && !empty($r['json']['messages'])) return ['ok' => true];
    $msg = $r['json']['error']['message'] ?? "HTTP {$r['http']}";
    error_log('RV Tennis WhatsApp send failed for ' . tn_mask($mobile) . ": $msg");
    return ['ok' => false, 'error' => "WhatsApp message could not be sent ($msg)"];
}
/** Step 1: send the code. For a number with no account yet the phone first collects a name and consent. */
function tn_wa_request(array $in): array {
    $mobile = tn_mobile((string)($in['mobile'] ?? ''));
    if (!$mobile) fail('Enter a valid mobile number, e.g. 98765 43210');
    $owner = row('SELECT id FROM users WHERE mobile=?', [$mobile]);
    $name = str_in($in, 'name', 100);
    if (!$owner && ($name === '' || empty($in['consent']))) return ['new' => true, 'mobile' => tn_mask($mobile)];
    if ((int)val('SELECT COUNT(*) FROM otp_tokens WHERE mobile=? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)', [$mobile]) >= 3) fail('Too many messages to that number. Please wait a few minutes and try again.');
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $token = bin2hex(random_bytes(16));
    q('INSERT INTO otp_tokens (token_hash, code_hash, mobile, purpose, user_id, data, expires_at) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE))',
      [hash('sha256', $token), password_hash($code, PASSWORD_DEFAULT), $mobile, $owner ? 'login' : 'signup', $owner['id'] ?? null, json_encode($owner ? [] : ['display_name' => $name]), (int)OTP_MINUTES]);
    $id = lastId();
    $sent = tn_wa_send($mobile, $code, $token);
    $out = ['id' => $id, 'mobile' => tn_mask($mobile), 'minutes' => (int)OTP_MINUTES];
    if (!$sent['ok']) {
        if (!DEBUG) { q('DELETE FROM otp_tokens WHERE id=?', [$id]); fail($sent['error']); }
        $out['dev_code'] = $code;   // DEBUG only: lets the site owner test before WhatsApp is switched on
    }
    return $out;
}
/** Step 2: check the code and sign in (creating the account for a new number) */
function tn_wa_verify(array $in): array {
    $t = row('SELECT * FROM otp_tokens WHERE id=? AND used_at IS NULL', [int_in($in, 'id')]);
    if (!$t || strtotime($t['expires_at']) < time()) fail('That code has expired. Ask for a new one.');
    if ((int)$t['tries'] >= 5) fail('Too many wrong codes. Ask for a new one.');
    if (!password_verify(preg_replace('/\D/', '', (string)($in['code'] ?? '')), $t['code_hash'])) { q('UPDATE otp_tokens SET tries=tries+1 WHERE id=?', [$t['id']]); fail('That code is not right'); }
    q('UPDATE otp_tokens SET used_at=NOW() WHERE id=?', [$t['id']]);
    $mobile = $t['mobile'];
    $u = row('SELECT * FROM users WHERE mobile=?', [$mobile]);
    if ($u) q('UPDATE users SET mobile_verified_at=COALESCE(mobile_verified_at, NOW()) WHERE id=?', [$u['id']]);
    else {
        $name = (json_decode((string)$t['data'], true) ?: [])['display_name'] ?? ('Player ' . substr($mobile, -4));
        $base = preg_replace('/[^a-z0-9]/', '', strtolower(explode(' ', trim($name))[0])) ?: 'player';
        q('INSERT INTO users (username, password_hash, display_name, mobile, mobile_verified_at, terms_accepted_at) VALUES (?,?,?,?,NOW(),NOW())', [tn_username($base . substr($mobile, -4)), '', $name, $mobile]);
        $u = ['id' => lastId()];
    }
    tn_login((int)$u['id']);
    $u = row('SELECT * FROM users WHERE id=?', [$u['id']]); $u['tn_admin'] = tn_is_admin($u);
    return ['user' => tn_public_user($u)];
}
/** Username (or e-mail / mobile) + password of an existing Cric-Scorer account */
function tn_password_login(array $in): array {
    $id = str_in($in, 'username', 190); $pw = (string)($in['password'] ?? '');
    if ($id === '' || $pw === '') fail('Enter your username and password');
    // No more than 8 wrong passwords in 15 minutes from one address or for one account
    $keys = ['ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 'id:' . mb_substr(strtolower($id), 0, 70)];
    foreach ($keys as $k) if ((int)val('SELECT COUNT(*) FROM tn_tries WHERE k=? AND at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)', [$k]) >= 8) fail('Too many wrong passwords. Wait 15 minutes, or sign in with Google or WhatsApp.', 429);
    usleep(250000);
    $u = row('SELECT * FROM users WHERE username=?', [$id]) ?: row('SELECT * FROM users WHERE email=? LIMIT 1', [strtolower($id)]);
    if (!$u && ($m = tn_mobile($id))) $u = row('SELECT * FROM users WHERE mobile=?', [$m]);
    if (!$u || (string)$u['password_hash'] === '' || !password_verify($pw, $u['password_hash'])) {
        foreach ($keys as $k) q('INSERT INTO tn_tries (k) VALUES (?)', [$k]);
        if (random_int(1, 20) === 1) q('DELETE FROM tn_tries WHERE at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        fail('That username and password do not match');
    }
    tn_login((int)$u['id']);
    $u['tn_admin'] = tn_is_admin($u);
    return ['user' => tn_public_user($u)];
}

<?php
/* Cric-Scorer - core helpers: database, JSON output, authentication */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/google.php';
require_once __DIR__ . '/social.php';
require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/updater.php';
require_once __DIR__ . '/game.php';
require_once __DIR__ . '/teams.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/league.php';
require_once __DIR__ . '/playoffs.php';
require_once __DIR__ . '/academies.php';
require_once __DIR__ . '/community.php';
require_once __DIR__ . '/auction.php';

if (!function_exists('mb_substr')) { function mb_substr($s, $start, $len = null) { return $len === null ? substr($s, $start) : substr($s, $start, $len); } }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function lastId(): int { return (int) db()->lastInsertId(); }

class ApiError extends Exception {
    public int $status;
    public function __construct(string $msg, int $status = 400) { parent::__construct($msg); $this->status = $status; }
}
function fail(string $msg, int $status = 400): void { throw new ApiError($msg, $status); }
function out($data): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function str_in(array $in, string $k, int $max = 150): string {
    $v = trim((string)($in[$k] ?? ''));
    return mb_substr($v, 0, $max);
}
function int_in(array $in, string $k, int $def = 0): int { return isset($in[$k]) && $in[$k] !== '' && $in[$k] !== null ? (int)$in[$k] : $def; }
function nint($v): ?int { return ($v === null || $v === '' || (int)$v === 0) ? null : (int)$v; }
function valid_color(string $c, string $def = '#0d4a36'): string { return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $def; }

/* ---------- Automatic database upgrades (safe to run on every request) ---------- */
function ensure_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if (!val("SHOW COLUMNS FROM users LIKE 'google_sub'")) {
        db()->exec("ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER display_name, ADD COLUMN google_sub VARCHAR(64) NULL AFTER email,
                    ADD COLUMN avatar_url VARCHAR(255) NULL AFTER google_sub, ADD UNIQUE KEY uniq_google_sub (google_sub), ADD INDEX idx_email (email)");
    }
    if (!val("SHOW COLUMNS FROM users LIKE 'bio'")) {
        if (!val("SHOW COLUMNS FROM users LIKE 'is_private'")) db()->exec("ALTER TABLE users ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0 AFTER avatar_url");
        db()->exec("ALTER TABLE users ADD COLUMN avatar_preset VARCHAR(20) NULL AFTER avatar_url, ADD COLUMN bio VARCHAR(200) NULL AFTER avatar_preset,
                    ADD COLUMN role VARCHAR(20) NULL AFTER bio, ADD COLUMN batting_style VARCHAR(30) NULL AFTER role, ADD COLUMN bowling_style VARCHAR(40) NULL AFTER batting_style,
                    ADD COLUMN experience_years TINYINT NULL AFTER bowling_style, ADD COLUMN player_id INT NULL AFTER experience_years, ADD UNIQUE KEY uniq_player (player_id)");
    }
    if (!val("SHOW COLUMNS FROM users LIKE 'is_private'")) db()->exec("ALTER TABLE users ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0 AFTER avatar_url");
    if (!val("SHOW TABLES LIKE 'follows'")) {
        db()->exec("CREATE TABLE IF NOT EXISTS follows (follower_id INT NOT NULL, followee_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (follower_id, followee_id), INDEX (followee_id),
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    if (!val("SHOW TABLES LIKE 'academy_progress'")) {
        db()->exec("CREATE TABLE IF NOT EXISTS academy_progress (
            user_id INT NOT NULL, lesson_id VARCHAR(20) NOT NULL, best_correct INT NOT NULL DEFAULT 0, total INT NOT NULL DEFAULT 0,
            stars TINYINT NOT NULL DEFAULT 0, attempts INT NOT NULL DEFAULT 0, xp INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, lesson_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    if (!val("SHOW COLUMNS FROM matches LIKE 'visibility'")) db()->exec("ALTER TABLE matches ADD COLUMN visibility ENUM('public','private') NOT NULL DEFAULT 'public' AFTER status, ADD INDEX idx_visibility (visibility)");
    if (!val("SHOW COLUMNS FROM users LIKE 'last_seen_at'")) schema_v19();
    if (!val("SHOW COLUMNS FROM teams LIKE 'share_mode'")) {   // v1.11: owners decide who may use their custom teams
        db()->exec("ALTER TABLE teams ADD COLUMN share_mode ENUM('approved','everyone') NOT NULL DEFAULT 'approved' AFTER owner_id");
        db()->exec("CREATE TABLE IF NOT EXISTS team_access (team_id INT NOT NULL, user_id INT NOT NULL, status ENUM('pending','allowed') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (team_id, user_id), INDEX (user_id),
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    if (!val("SHOW TABLES LIKE 'game_profiles'")) db()->exec("CREATE TABLE IF NOT EXISTS game_profiles (user_id INT PRIMARY KEY, coins INT NOT NULL DEFAULT 100, wins INT NOT NULL DEFAULT 0, losses INT NOT NULL DEFAULT 0,
        chars TEXT NULL, squad TEXT NULL, stadium VARCHAR(20) NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!val("SHOW COLUMNS FROM matches LIKE 'scorer_id'")) db()->exec("ALTER TABLE matches ADD COLUMN scorer_id INT NULL AFTER owner_id, ADD INDEX idx_scorer (scorer_id)");
    if (!val("SHOW COLUMNS FROM users LIKE 'mobile'")) {   // v1.14: mobile numbers, WhatsApp verification, academies
        db()->exec("ALTER TABLE users ADD COLUMN mobile VARCHAR(20) NULL AFTER email, ADD COLUMN mobile_verified_at DATETIME NULL AFTER mobile, ADD UNIQUE KEY uniq_mobile (mobile)");
        db()->exec("CREATE TABLE IF NOT EXISTS otp_tokens (id INT AUTO_INCREMENT PRIMARY KEY, token_hash CHAR(64) NOT NULL, code_hash VARCHAR(255) NOT NULL, mobile VARCHAR(20) NOT NULL,
            purpose VARCHAR(10) NOT NULL, user_id INT NULL, data TEXT NULL, tries TINYINT NOT NULL DEFAULT 0, used_at DATETIME NULL, expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (token_hash), INDEX (mobile, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS academies (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, address VARCHAR(250) NULL, city VARCHAR(80) NULL, phone VARCHAR(20) NULL,
            coach VARCHAR(100) NULL, about VARCHAR(600) NULL, owner_id INT NOT NULL, team_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (owner_id), INDEX (city), FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS academy_students (id INT AUTO_INCREMENT PRIMARY KEY, academy_id INT NOT NULL, user_id INT NULL, player_id INT NULL, name VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (academy_id), INDEX (user_id),
            FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    if (!val("SHOW COLUMNS FROM players LIKE 'mobile'")) db()->exec("ALTER TABLE players ADD COLUMN mobile VARCHAR(20) NULL AFTER jersey_no, ADD INDEX idx_player_mobile (mobile)");
    if (!val("SHOW COLUMNS FROM users LIKE 'terms_accepted_at'")) db()->exec("ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL AFTER player_id");
    schema_v116();   // v1.16: pro scoring
    schema_v117();   // v1.17: career pages
    schema_v118();   // v1.18: grounds & looking for
    schema_v119();   // v1.19: APL Auction
    schema_v121();   // v1.21: match photos
    schema_v122();   // v1.22: club leagues
    schema_v123();   // v1.23: bouncer limit
    schema_v127();   // v1.27: playoffs & bowling quotas
    if (val("SHOW COLUMNS FROM innings LIKE 'max_overs'")) return;   // already on the DLS schema
    db()->exec("ALTER TABLE innings ADD COLUMN max_overs INT NOT NULL DEFAULT 20 AFTER target, ADD COLUMN start_overs INT NOT NULL DEFAULT 20 AFTER max_overs, ADD COLUMN dls TINYINT(1) NOT NULL DEFAULT 0 AFTER start_overs");
    db()->exec("UPDATE innings i JOIN matches m ON m.id=i.match_id SET i.max_overs=m.overs, i.start_overs=m.overs");
    if (!val("SHOW COLUMNS FROM matches LIKE 'dls_g50'"))
        db()->exec("ALTER TABLE matches ADD COLUMN dls_g50 INT NOT NULL DEFAULT 200 AFTER last_man_stands, ADD COLUMN inn2_overs INT NULL AFTER dls_g50, ADD COLUMN result_method VARCHAR(10) NULL AFTER inn2_overs");
    db()->exec("CREATE TABLE IF NOT EXISTS interruptions (
        id INT AUTO_INCREMENT PRIMARY KEY, match_id INT NOT NULL, innings_no TINYINT NOT NULL, legal_balls INT NOT NULL,
        wickets INT NOT NULL, overs_before INT NOT NULL, overs_after INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (match_id),
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** v1.9: admin portal, feature roll-out, help desk, announcements, error reports, Gully Duel games */
function schema_v19(): void {
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS app_settings (k VARCHAR(60) PRIMARY KEY, v TEXT NULL) $t");
    db()->exec("CREATE TABLE IF NOT EXISTS feature_users (feature VARCHAR(30) NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (feature, user_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
    db()->exec("CREATE TABLE IF NOT EXISTS games (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(6) NOT NULL DEFAULT '', mode ENUM('online','local','cpu') NOT NULL DEFAULT 'online',
        p0 INT NULL, p1 INT NULL, status ENUM('waiting','live','done','abandoned') NOT NULL DEFAULT 'waiting', overs INT NOT NULL DEFAULT 2, wickets INT NOT NULL DEFAULT 3,
        state MEDIUMTEXT NULL, version INT NOT NULL DEFAULT 0, winner TINYINT NULL, balls INT NOT NULL DEFAULT 0, rematch_code VARCHAR(6) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code, status), INDEX idx_created (created_at), INDEX idx_p0 (p0), INDEX idx_p1 (p1)) $t");
    db()->exec("CREATE TABLE IF NOT EXISTS tickets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, kind VARCHAR(10) NOT NULL DEFAULT 'help',
        status ENUM('open','answered','closed') NOT NULL DEFAULT 'open', user_unread TINYINT(1) NOT NULL DEFAULT 0, admin_unread TINYINT(1) NOT NULL DEFAULT 1,
        app_version VARCHAR(20) NULL, device VARCHAR(20) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX (status), INDEX (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) $t");
    db()->exec("CREATE TABLE IF NOT EXISTS ticket_msgs (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, from_admin TINYINT(1) NOT NULL DEFAULT 0, body TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (ticket_id), FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE) $t");
    db()->exec("CREATE TABLE IF NOT EXISTS announcements (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(120) NOT NULL, body VARCHAR(600) NULL,
        kind VARCHAR(10) NOT NULL DEFAULT 'info', active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) $t");
    db()->exec("CREATE TABLE IF NOT EXISTS error_reports (id INT AUTO_INCREMENT PRIMARY KEY, ref VARCHAR(10) NOT NULL, source VARCHAR(10) NOT NULL DEFAULT 'server',
        action VARCHAR(40) NULL, message VARCHAR(700) NULL, app_version VARCHAR(20) NULL, device VARCHAR(20) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (ref), INDEX (created_at)) $t");
    db()->exec("ALTER TABLE users ADD COLUMN last_seen_at DATETIME NULL, ADD COLUMN app_version VARCHAR(20) NULL, ADD COLUMN platform VARCHAR(20) NULL");
}

/* ---------- Sessions & auth ---------- */
function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('CRICSESS');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
}

function public_user(array $u): array {
    return ['id' => (int)$u['id'], 'username' => $u['username'], 'display_name' => $u['display_name'], 'is_admin' => (bool)$u['is_admin'],
            'email' => $u['email'] ?? null, 'google' => !empty($u['google_sub']), 'avatar' => $u['avatar_url'] ?? null,
            'has_password' => (string)($u['password_hash'] ?? '') !== '', 'is_private' => !empty($u['is_private']), 'features' => user_features($u),
            'mobile' => !empty($u['mobile']) ? pretty_mobile($u['mobile']) : null, 'mobile_verified' => !empty($u['mobile_verified_at']), 'academies' => user_academies((int)$u['id'])] + cricket_profile($u);
}

function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    start_session();
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = row('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]);
    }
    if (!$u && !empty($_COOKIE['cs_rt']) && strpos($_COOKIE['cs_rt'], ':') !== false) {
        [$tid, $tok] = explode(':', $_COOKIE['cs_rt'], 2);
        $t = row('SELECT * FROM user_tokens WHERE id=? AND expires_at > NOW()', [(int)$tid]);
        if ($t && hash_equals($t['token_hash'], hash('sha256', $tok))) {
            $u = row('SELECT * FROM users WHERE id=?', [$t['user_id']]);
            if ($u) { session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; }
        }
    }
    if ($u) { try { touch_presence($u); } catch (Throwable $e) { /* the totals are not worth an error */ } }
    $cache = $u;
    return $u;
}

function issue_remember_token(int $uid): void {
    $tok = bin2hex(random_bytes(32));
    q('INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 90 DAY))', [$uid, hash('sha256', $tok)]);
    $id = lastId();
    setcookie('cs_rt', $id . ':' . $tok, [
        'expires' => time() + 90 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function clear_remember_token(): void {
    if (!empty($_COOKIE['cs_rt']) && strpos($_COOKIE['cs_rt'], ':') !== false) {
        [$tid] = explode(':', $_COOKIE['cs_rt'], 2);
        q('DELETE FROM user_tokens WHERE id=?', [(int)$tid]);
    }
    setcookie('cs_rt', '', ['expires' => time() - 3600, 'path' => '/']);
}

function login_user(array $u, bool $remember = true): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    if ($remember) issue_remember_token((int)$u['id']);
}

function require_user(): array {
    $u = current_user();
    if (!$u) fail('Please log in', 401);
    return $u;
}

/* ---------- Permissions ---------- */
function can_edit_team(?array $team, array $u): bool {
    if (!$team) return false;
    if ($u['is_admin']) return true;
    return $team['category'] === 'custom' && (int)$team['owner_id'] === (int)$u['id'];
}
/** Who may score a match: the player who created it, the admin, or the scorer they named */
function can_score_match(?array $m, ?array $u): bool {
    if (!$m || !$u) return false;
    return can_edit_owned($m, $u) || (!empty($m['scorer_id']) && (int)$m['scorer_id'] === (int)$u['id']);
}
function can_edit_owned(?array $obj, ?array $u): bool {
    if (!$obj || !$u) return false;
    return $u['is_admin'] || (int)$obj['owner_id'] === (int)$u['id'];
}

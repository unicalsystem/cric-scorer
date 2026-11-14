<?php
/* Cric-Scorer - admin portal: settings, feature roll-out, anonymous usage stats, help desk, announcements, error reports.
   The portal shows counts and charts only - never names, usernames, emails or photos. */

/* ---------- Settings ---------- */
function setting(string $k, $default = null) {
    static $cache = null;
    if ($cache === null) { $cache = []; foreach (rows('SELECT k, v FROM app_settings') as $r) $cache[$r['k']] = $r['v']; }
    return array_key_exists($k, $cache) ? $cache[$k] : $default;
}
function setting_set(string $k, ?string $v): void {
    if ($v === null) q('DELETE FROM app_settings WHERE k=?', [$k]);
    else q('INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [$k, $v]);
}

/* ---------- Feature roll-out: off, selected players, or everyone ---------- */
const FEATURES = [
    'game' => ['name' => 'Gully Duel (bat vs bowl game)', 'emoji' => '🎮', 'about' => 'Two players - one bowls, one bats - on two phones, one phone, or against the computer.'],
    'auction' => ['name' => 'APL Auction (IPL-style player auction)', 'emoji' => '🔨', 'about' => 'Friends and family join a private room with a code and bid virtual coins for IPL players in real time.', 'default' => 'all'],
];
function feature_mode(string $f): string {
    $m = setting('feature_' . $f, FEATURES[$f]['default'] ?? 'some');
    return in_array($m, ['off', 'some', 'all'], true) ? $m : 'some';
}
function feature_on(?array $u, string $f): bool {
    if (!$u) return false;
    if (!empty($u['is_admin'])) return true;                      // the admin can always try a new feature
    $m = feature_mode($f);
    if ($m === 'all') return true;
    if ($m === 'off') return false;
    return (bool)val('SELECT 1 FROM feature_users WHERE feature=? AND user_id=?', [$f, $u['id']]);
}
function user_features(array $u): array {
    $o = [];
    foreach (FEATURES as $k => $_) $o[$k] = feature_on($u, $k);
    return $o;
}

/* ---------- Last seen, app version and kind of device (stored for the totals only) ---------- */
function touch_presence(array $u): void {
    $v = substr(preg_replace('/[^0-9A-Za-z.\-]/', '', $_SERVER['HTTP_X_APP_VERSION'] ?? ''), 0, 20);
    $recent = !empty($u['last_seen_at']) && strtotime($u['last_seen_at']) > time() - 300;
    if ($recent && ($v === '' || $v === ($u['app_version'] ?? ''))) return;
    q('UPDATE users SET last_seen_at=NOW(), app_version=COALESCE(NULLIF(?, \'\'), app_version), platform=? WHERE id=?', [$v, device_kind(), $u['id']]);
}
function device_kind(): string {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (strpos($ua, 'android') !== false) return 'Android';
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipod') !== false) return 'iPhone';
    if (strpos($ua, 'ipad') !== false) return 'iPad';
    if (strpos($ua, 'windows') !== false) return 'Windows';
    if (strpos($ua, 'mac os') !== false || strpos($ua, 'macintosh') !== false) return 'Mac';
    if (strpos($ua, 'cros') !== false) return 'Chromebook';
    if (strpos($ua, 'linux') !== false) return 'Linux';
    return 'Other';
}

/* ---------- Error reports (personal details are blanked out before saving) ---------- */
function redact(string $s): string {
    $s = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $s);
    $s = preg_replace("/'[^']{1,200}'/", "'…'", $s);
    $s = preg_replace('/"[^"]{1,200}"/', '"…"', $s);
    return mb_substr($s, 0, 700);
}
function log_error_report(string $ref, string $source, string $action, string $msg): void {
    try {
        $v = substr(preg_replace('/[^0-9A-Za-z.\-]/', '', $_SERVER['HTTP_X_APP_VERSION'] ?? ''), 0, 20) ?: APP_VERSION;
        q('INSERT INTO error_reports (ref, source, action, message, app_version, device) VALUES (?,?,?,?,?,?)',
          [$ref, $source, substr($action, 0, 40), redact($msg), $v, device_kind()]);
    } catch (Throwable $e) { /* never let error logging cause another error */ }
}

/* ---------- Usage stats: totals only ---------- */
function admin_stats(): array {
    $n = fn(string $sql, array $p = []) => (int)val($sql, $p);
    $days = function (string $table, string $col, string $where = '1=1') {
        $out = [];
        for ($i = 29; $i >= 0; $i--) $out[date('Y-m-d', strtotime("-$i day"))] = 0;
        foreach (rows("SELECT DATE($col) d, COUNT(*) c FROM $table WHERE $col >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND $where GROUP BY DATE($col)") as $r)
            if (isset($out[$r['d']])) $out[$r['d']] = (int)$r['c'];
        return $out;
    };
    $group = function (string $sql): array { $o = []; foreach (rows($sql) as $r) $o[(string)($r['k'] ?? 'Unknown')] = (int)$r['c']; return $o; };
    return [
        'users' => [
            'total' => $n('SELECT COUNT(*) FROM users'),
            'today' => $n('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()'),
            'week' => $n('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'month' => $n('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'active_day' => $n('SELECT COUNT(*) FROM users WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'active_week' => $n('SELECT COUNT(*) FROM users WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'active_month' => $n('SELECT COUNT(*) FROM users WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'google' => $n("SELECT COUNT(*) FROM users WHERE google_sub IS NOT NULL"),
            'private' => $n('SELECT COUNT(*) FROM users WHERE is_private=1'),
            'photo' => $n("SELECT COUNT(*) FROM users WHERE avatar_url IS NOT NULL OR avatar_preset IS NOT NULL"),
            'follows' => $n('SELECT COUNT(*) FROM follows'),
            'signups' => $days('users', 'created_at'),
            'devices' => $group("SELECT COALESCE(platform, 'Not seen yet') k, COUNT(*) c FROM users GROUP BY COALESCE(platform, 'Not seen yet') ORDER BY c DESC"),
            'versions' => $group("SELECT COALESCE(app_version, 'Older than 1.9') k, COUNT(*) c FROM users WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR last_seen_at IS NULL GROUP BY COALESCE(app_version, 'Older than 1.9') ORDER BY c DESC LIMIT 8"),
        ],
        'matches' => [
            'total' => $n('SELECT COUNT(*) FROM matches'),
            'live' => $n("SELECT COUNT(*) FROM matches WHERE status='live'"),
            'completed' => $n("SELECT COUNT(*) FROM matches WHERE status='completed'"),
            'scheduled' => $n("SELECT COUNT(*) FROM matches WHERE status='scheduled'"),
            'private' => $n("SELECT COUNT(*) FROM matches WHERE visibility='private'"),
            'week' => $n('SELECT COUNT(*) FROM matches WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'balls' => $n('SELECT COUNT(*) FROM balls'),
            'dls' => $n("SELECT COUNT(*) FROM matches WHERE result_method='dls'"),
            'tournaments' => $n('SELECT COUNT(*) FROM tournaments'),
            'teams' => $n("SELECT COUNT(*) FROM teams WHERE category='custom'"),
            'scorers' => $n('SELECT COUNT(DISTINCT owner_id) FROM matches'),
            'per_day' => $days('matches', 'created_at'),
            'formats' => $group("SELECT CASE WHEN overs<=5 THEN '1-5 overs' WHEN overs<=10 THEN '6-10 overs' WHEN overs<=20 THEN 'T20 (11-20)' ELSE '21+ overs' END k, COUNT(*) c FROM matches GROUP BY k ORDER BY c DESC"),
        ],
        'academy' => [
            'learners' => $n('SELECT COUNT(DISTINCT user_id) FROM academy_progress'),
            'passed' => $n('SELECT COUNT(*) FROM academy_progress WHERE stars>0'),
            'attempts' => $n('SELECT COALESCE(SUM(attempts),0) FROM academy_progress'),
            'three_star' => $n('SELECT COUNT(*) FROM academy_progress WHERE stars=3'),
            'certified' => $n("SELECT COUNT(*) FROM academy_progress WHERE lesson_id='final' AND stars>0"),
        ],
        'games' => [
            'total' => $n("SELECT COUNT(*) FROM games WHERE status IN ('done','abandoned') OR (status='live' AND mode='online')"),
            'online' => $n("SELECT COUNT(*) FROM games WHERE mode='online' AND p1 IS NOT NULL"),
            'local' => $n("SELECT COUNT(*) FROM games WHERE mode='local'"),
            'cpu' => $n("SELECT COUNT(*) FROM games WHERE mode='cpu'"),
            'live_now' => $n("SELECT COUNT(*) FROM games WHERE mode='online' AND status='live' AND updated_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"),
            'players' => $n("SELECT COUNT(DISTINCT p) FROM (SELECT p0 p FROM games UNION SELECT p1 FROM games WHERE p1 IS NOT NULL) x"),
            'balls' => $n('SELECT COALESCE(SUM(balls),0) FROM games'),
            'per_day' => $days('games', 'created_at', "status <> 'waiting'"),
        ],
        'support' => [
            'open' => $n("SELECT COUNT(*) FROM tickets WHERE status='open'"),
            'errors_week' => $n('SELECT COUNT(*) FROM error_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
        ],
        'generated' => date('j M Y, g:i a'),
    ];
}

/* ---------- Features: who has early access (only the usernames the admin typed are shown back) ---------- */
function admin_features(): array {
    $out = [];
    foreach (FEATURES as $k => $f) {
        $out[] = $f + ['key' => $k, 'mode' => feature_mode($k),
            'people' => rows('SELECT u.id, u.username FROM feature_users fu JOIN users u ON u.id=fu.user_id WHERE fu.feature=? ORDER BY fu.created_at', [$k]),
            'total_users' => (int)val('SELECT COUNT(*) FROM users')];
    }
    return $out;
}
function admin_feature_action(array $in): array {
    $k = (string)($in['feature'] ?? '');
    if (!isset(FEATURES[$k])) fail('Unknown feature');
    if (isset($in['mode'])) {
        if (!in_array($in['mode'], ['off', 'some', 'all'], true)) fail('Pick off, selected players or everyone');
        setting_set('feature_' . $k, $in['mode']);
    }
    if (!empty($in['add'])) {
        $un = strtolower(trim((string)$in['add']));
        $id = val('SELECT id FROM users WHERE username=?', [$un]);
        if (!$id) fail('No player with that exact username');
        q('INSERT IGNORE INTO feature_users (feature, user_id) VALUES (?, ?)', [$k, $id]);
    }
    if (!empty($in['remove'])) q('DELETE FROM feature_users WHERE feature=? AND user_id=?', [$k, (int)$in['remove']]);
    return ['features' => admin_features()];
}

/* ---------- Help desk ---------- */
const TICKET_KINDS = ['help' => 'Need help', 'bug' => 'Something is broken', 'idea' => 'Idea / feature request', 'other' => 'Other'];
function ticket_view(array $t, bool $forAdmin): array {
    $msgs = rows('SELECT from_admin, body, created_at FROM ticket_msgs WHERE ticket_id=? ORDER BY id', [$t['id']]);
    return ['id' => (int)$t['id'], 'kind' => $t['kind'], 'kind_label' => TICKET_KINDS[$t['kind']] ?? 'Other', 'status' => $t['status'],
        'created_at' => $t['created_at'], 'updated_at' => $t['updated_at'],
        'unread' => (bool)($forAdmin ? $t['admin_unread'] : $t['user_unread']),
        'app_version' => $t['app_version'], 'device' => $t['device'],
        'msgs' => array_map(fn($m) => ['from' => $m['from_admin'] ? 'support' : ($forAdmin ? 'player' : 'you'), 'body' => $m['body'], 'at' => $m['created_at']], $msgs)];
}
function help_action(string $action, array $u, array $in) {
    switch ($action) {
    case 'help_send': {
        $body = trim(mb_substr((string)($in['message'] ?? ''), 0, 2000));
        if (mb_strlen($body) < 3) fail('Type your message first');
        $tid = (int)($in['ticket_id'] ?? 0);
        if ($tid) {
            $t = row('SELECT * FROM tickets WHERE id=? AND user_id=?', [$tid, $u['id']]);
            if (!$t) fail('Message not found', 404);
            q("UPDATE tickets SET status='open', admin_unread=1, user_unread=0 WHERE id=?", [$tid]);
        } else {
            if ((int)val('SELECT COUNT(*) FROM tickets WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)', [$u['id']]) >= 10) fail('You have sent a lot of messages today. Please wait for a reply.');
            $kind = isset(TICKET_KINDS[$in['kind'] ?? '']) ? $in['kind'] : 'help';
            $v = substr(preg_replace('/[^0-9A-Za-z.\-]/', '', $_SERVER['HTTP_X_APP_VERSION'] ?? ''), 0, 20) ?: APP_VERSION;
            q("INSERT INTO tickets (user_id, kind, status, admin_unread, app_version, device) VALUES (?, ?, 'open', 1, ?, ?)", [$u['id'], $kind, $v, device_kind()]);
            $tid = lastId();
        }
        q('INSERT INTO ticket_msgs (ticket_id, from_admin, body) VALUES (?, 0, ?)', [$tid, $body]);
        return ['ticket' => ticket_view(row('SELECT * FROM tickets WHERE id=?', [$tid]), false)];
    }
    case 'help_list': {
        $list = array_map(fn($t) => ticket_view($t, false), rows('SELECT * FROM tickets WHERE user_id=? ORDER BY updated_at DESC LIMIT 30', [$u['id']]));
        q('UPDATE tickets SET user_unread=0 WHERE user_id=?', [$u['id']]);
        return ['tickets' => $list, 'kinds' => TICKET_KINDS];
    }
    }
    fail('Unknown help action');
}

/* ---------- Everything under admin_* (the API checks is_admin first) ---------- */
function admin_action(string $action, array $u, array $in) {
    switch ($action) {
    case 'admin_stats': return admin_stats();
    case 'admin_features': return ['features' => admin_features()];
    case 'admin_feature_set': return admin_feature_action($in);

    case 'admin_users': {                              // the player list: name, username and id, with search (no emails or numbers)
        $q = trim(mb_substr((string)($in['q'] ?? ''), 0, 60));
        $page = max(0, (int)($in['page'] ?? 0)); $per = 50;
        $where = '1=1'; $p = [];
        if ($q !== '') {
            if (ctype_digit($q) && strlen($q) <= 9) { $where = 'u.id=?'; $p = [(int)$q]; }
            else { $where = '(LOCATE(?, u.display_name) > 0 OR LOCATE(?, u.username) > 0)'; $p = [$q, $q]; }
        }
        $total = (int)val("SELECT COUNT(*) FROM users u WHERE $where", $p);
        $list = rows("SELECT u.id, u.username, u.display_name, u.created_at, u.last_seen_at, u.is_admin, u.is_private, u.mobile_verified_at IS NOT NULL verified, u.google_sub IS NOT NULL google,
                        (SELECT COUNT(*) FROM matches m WHERE m.owner_id=u.id) matches
                      FROM users u WHERE $where ORDER BY u.id DESC LIMIT $per OFFSET " . ($page * $per), $p);
        foreach ($list as &$r) { $r['id'] = (int)$r['id']; $r['matches'] = (int)$r['matches']; $r['verified'] = (bool)$r['verified']; $r['google'] = (bool)$r['google']; $r['is_admin'] = (bool)$r['is_admin']; $r['is_private'] = (bool)$r['is_private']; }
        return ['users' => $list, 'total' => $total, 'page' => $page, 'per' => $per];
    }
    case 'admin_tickets': {
        $st = in_array($in['status'] ?? '', ['open', 'answered', 'closed'], true) ? $in['status'] : 'open';
        $list = array_map(fn($t) => ticket_view($t, true), rows('SELECT * FROM tickets WHERE status=? ORDER BY updated_at DESC LIMIT 100', [$st]));
        $counts = []; foreach (['open', 'answered', 'closed'] as $s) $counts[$s] = (int)val('SELECT COUNT(*) FROM tickets WHERE status=?', [$s]);
        return ['tickets' => $list, 'counts' => $counts];
    }
    case 'admin_ticket_reply': {
        $tid = (int)($in['id'] ?? 0);
        if (!row('SELECT id FROM tickets WHERE id=?', [$tid])) fail('Ticket not found', 404);
        $body = trim(mb_substr((string)($in['message'] ?? ''), 0, 2000));
        if ($body !== '') q('INSERT INTO ticket_msgs (ticket_id, from_admin, body) VALUES (?, 1, ?)', [$tid, $body]);
        $status = in_array($in['status'] ?? '', ['open', 'answered', 'closed'], true) ? $in['status'] : ($body !== '' ? 'answered' : 'closed');
        q('UPDATE tickets SET status=?, admin_unread=0, user_unread=IF(?, 1, user_unread) WHERE id=?', [$status, $body !== '' ? 1 : 0, $tid]);
        return ['ticket' => ticket_view(row('SELECT * FROM tickets WHERE id=?', [$tid]), true)];
    }

    case 'admin_announcements':
        return ['announcements' => rows('SELECT id, title, body, kind, active, created_at FROM announcements ORDER BY id DESC LIMIT 50')];
    case 'admin_announcement_save': {
        $title = trim(mb_substr((string)($in['title'] ?? ''), 0, 120));
        $body = trim(mb_substr((string)($in['body'] ?? ''), 0, 600));
        if ($title === '') fail('Give the announcement a title');
        $kind = in_array($in['kind'] ?? '', ['info', 'new', 'warn'], true) ? $in['kind'] : 'info';
        q('INSERT INTO announcements (title, body, kind, active) VALUES (?, ?, ?, 1)', [$title, $body, $kind]);
        return ['announcements' => rows('SELECT id, title, body, kind, active, created_at FROM announcements ORDER BY id DESC LIMIT 50')];
    }
    case 'admin_announcement_toggle': {
        $id = (int)($in['id'] ?? 0);
        if (!empty($in['delete'])) q('DELETE FROM announcements WHERE id=?', [$id]);
        else q('UPDATE announcements SET active=? WHERE id=?', [!empty($in['active']) ? 1 : 0, $id]);
        return ['announcements' => rows('SELECT id, title, body, kind, active, created_at FROM announcements ORDER BY id DESC LIMIT 50')];
    }

    case 'admin_errors': {
        q('DELETE FROM error_reports WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)');   // logs are kept for one year
        $ref = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($in['ref'] ?? '')));
        $list = $ref !== ''
            ? rows('SELECT ref, source, action, message, app_version, device, created_at FROM error_reports WHERE ref=? ORDER BY id DESC LIMIT 20', [$ref])
            : rows('SELECT ref, source, action, message, app_version, device, created_at FROM error_reports ORDER BY id DESC LIMIT 100');
        $top = rows("SELECT action, COUNT(*) c FROM error_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY action ORDER BY c DESC LIMIT 5");
        return ['errors' => $list, 'top' => $top, 'week' => (int)val('SELECT COUNT(*) FROM error_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')];
    }
    case 'admin_errors_clear':
        q('DELETE FROM error_reports');
        return ['ok' => true];

    case 'admin_import_players': return admin_import_players($u, $in);          // v1.34
    case 'admin_import_list': return admin_import_list();
    case 'admin_import_undo': return admin_import_undo((int)($in['id'] ?? 0));
    case 'admin_player_search': return admin_player_search($in);
    case 'admin_player_save': return admin_player_save($in);
    case 'admin_player_delete': return admin_player_delete((int)($in['id'] ?? 0));
    case 'admin_update_check': return update_check();
    case 'admin_update_install': return update_install();
    case 'admin_update_rollback': return update_rollback((string)($in['file'] ?? ''));
    case 'admin_force_update':
        setting_set('force_version', !empty($in['on']) ? APP_VERSION : null);
        return update_status();
    }
    fail('Unknown admin action');
}

/** Things every signed-in phone gets with "me": active announcements and the help-desk dot */
function client_extras(array $u): array {
    return [
        'announcements' => rows('SELECT id, title, body, kind FROM announcements WHERE active=1 ORDER BY id DESC LIMIT 3'),
        'help_unread' => (int)val('SELECT COUNT(*) FROM tickets WHERE user_id=? AND user_unread=1', [$u['id']]),
        'team_requests' => team_requests_waiting($u),
        'force_version' => setting('force_version'),
        'community' => community_home($u),
    ];
}

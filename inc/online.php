<?php
/**
 * Who is online - v1.39
 * Every open copy of the app sends a small heartbeat (on opening, every 45 seconds while the screen is on, and when the
 * player moves to another screen). "Online now" = a heartbeat in the last 90 seconds. Signed-in players are stored by
 * user id; guests by the same anonymous hash used for live viewers, so they are only counted, never named.
 * Only the admin can see the list (Admin → 🟢 Online, and the "Online now" card on Stats).
 */
declare(strict_types=1);

const ONLINE_WINDOW = 90;      // seconds since the last heartbeat that still counts as "online now"

function schema_v139(): void {
    if (!val("SHOW TABLES LIKE 'site_online'")) db()->exec("CREATE TABLE IF NOT EXISTS site_online (visitor CHAR(16) NOT NULL PRIMARY KEY, user_id INT NULL,
        screen VARCHAR(40) NULL, device VARCHAR(20) NULL, first_at DATETIME NOT NULL, seen_at DATETIME NOT NULL,
        INDEX (seen_at), INDEX (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** "#/match/42?x" -> "match/42"; only the route shape is kept (letters, digits and slashes) */
function online_screen(string $hash): string {
    $s = preg_replace('/[?#].*$/', '', ltrim($hash, '#/'));
    $s = preg_replace('/[^A-Za-z0-9\/_-]/', '', (string)$s);
    return substr($s !== '' ? $s : 'home', 0, 40);
}

function online_ping(?array $u, string $hash): array {
    $key = viewer_key($u);
    q('INSERT INTO site_online (visitor, user_id, screen, device, first_at, seen_at) VALUES (?,?,?,?,NOW(),NOW())
       ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), screen=VALUES(screen), device=VALUES(device),
         first_at=IF(seen_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE), NOW(), first_at), seen_at=NOW()',
      [$key, $u ? (int)$u['id'] : null, online_screen($hash), device_kind()]);
    if ($u) q("DELETE FROM site_online WHERE visitor LIKE 'g%' AND user_id IS NULL AND visitor=?", [viewer_key(null)]);   // just signed in: drop the guest row
    if (random_int(1, 100) === 1) q('DELETE FROM site_online WHERE seen_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    return ['ok' => true];
}

function online_count(): int {
    return (int)val('SELECT COUNT(*) FROM site_online WHERE seen_at >= DATE_SUB(NOW(), INTERVAL ' . ONLINE_WINDOW . ' SECOND)');
}

/** Admin: who is online now, and who was here in the last 15 minutes */
function admin_online(): array {
    $w = ONLINE_WINDOW;
    $people = rows("SELECT u.id, u.display_name, u.username, u.avatar_url avatar, u.avatar_preset, u.is_admin, o.screen, o.device,
            TIMESTAMPDIFF(SECOND, o.seen_at, NOW()) ago, TIMESTAMPDIFF(SECOND, o.first_at, NOW()) online_for
        FROM (SELECT user_id, MAX(seen_at) seen_at FROM site_online WHERE user_id IS NOT NULL AND seen_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) GROUP BY user_id) last
        JOIN site_online o ON o.user_id=last.user_id AND o.seen_at=last.seen_at
        JOIN users u ON u.id=o.user_id
        ORDER BY o.seen_at DESC");
    $now = []; $recent = []; $seen = [];
    foreach ($people as $p) {
        $id = (int)$p['id'];
        if (isset($seen[$id])) continue;            // two phones at the same second - show once
        $seen[$id] = true;
        $p['id'] = $id; $p['ago'] = (int)$p['ago']; $p['online_for'] = (int)$p['online_for']; $p['is_admin'] = (bool)$p['is_admin'];
        if ($p['ago'] <= $w) $now[] = $p; else $recent[] = $p;
    }
    usort($now, fn($a, $b) => strcasecmp((string)$a['display_name'], (string)$b['display_name']));
    $guests = (int)val("SELECT COUNT(*) FROM site_online WHERE user_id IS NULL AND seen_at >= DATE_SUB(NOW(), INTERVAL $w SECOND)");
    $screens = [];
    foreach (rows("SELECT screen FROM site_online WHERE seen_at >= DATE_SUB(NOW(), INTERVAL $w SECOND)") as $r) {
        $k = explode('/', (string)$r['screen'])[0] ?: 'home';
        $screens[$k] = ($screens[$k] ?? 0) + 1;
    }
    arsort($screens);
    // names of matches people are watching, so the list can say "watching Phoenix v Titans"
    $matchIds = [];
    foreach (array_merge($now, $recent) as $p) if (preg_match('#^match/(\d+)#', (string)$p['screen'], $m)) $matchIds[(int)$m[1]] = true;
    $matches = [];
    if ($matchIds) {
        $ids = implode(',', array_map('intval', array_keys($matchIds)));
        foreach (rows("SELECT m.id, m.status, ta.name a, tb.name b FROM matches m LEFT JOIN teams ta ON ta.id=m.team1_id LEFT JOIN teams tb ON tb.id=m.team2_id WHERE m.id IN ($ids)") as $r)
            $matches[(int)$r['id']] = ['name' => trim(($r['a'] ?? '?') . ' v ' . ($r['b'] ?? '?')), 'live' => $r['status'] === 'live'];
    }
    return ['now' => $now, 'recent' => $recent, 'members' => count($now), 'guests' => $guests, 'total' => count($now) + $guests,
        'screens' => $screens, 'matches' => (object)$matches, 'window' => $w, 'generated' => date('g:i:s a')];
}

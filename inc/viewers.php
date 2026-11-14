<?php
/**
 * Live viewers - v1.37
 * Every phone watching a match pings it (the match screen polls every few seconds; the scorer's screen sends a
 * heartbeat). "Watching now" = distinct viewers seen in the last 45 seconds. Viewers are a signed-in user's id or,
 * for guests, an anonymous hash of the connection - nothing is shown about who they are.
 */
declare(strict_types=1);

function schema_v137(): void {
    if (!val("SHOW TABLES LIKE 'match_viewers'")) db()->exec("CREATE TABLE IF NOT EXISTS match_viewers (match_id INT NOT NULL, viewer CHAR(16) NOT NULL, seen_at DATETIME NOT NULL,
        PRIMARY KEY (match_id, viewer), INDEX (seen_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function viewer_key(?array $u): string {
    if ($u) return 'u' . str_pad((string)(int)$u['id'], 15, '0', STR_PAD_LEFT);
    return 'g' . substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . (defined('DB_PASS') ? DB_PASS : '')), 0, 15);
}

/** Record this viewer (only while the match is live) and return how many are watching */
function match_viewers_ping(int $matchId, ?array $u, string $status): int {
    if ($status === 'live') {
        q('INSERT INTO match_viewers (match_id, viewer, seen_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE seen_at=NOW()', [$matchId, viewer_key($u)]);
        if (random_int(1, 200) === 1) q('DELETE FROM match_viewers WHERE seen_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    }
    return match_viewers_count($matchId);
}

function match_viewers_count(int $matchId): int {
    return (int)val('SELECT COUNT(*) FROM match_viewers WHERE match_id=? AND seen_at >= DATE_SUB(NOW(), INTERVAL 45 SECOND)', [$matchId]);
}

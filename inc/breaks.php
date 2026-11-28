<?php
/**
 * Lunch / tea / drinks breaks - v1.48
 * The scorer takes a break from the match menu (⋯) during an innings or at the innings break. Scoring stops and
 * everyone watching sees "Lunch break". Play starts again as soon as the scorer picks the bowler for the next ball.
 */
declare(strict_types=1);

const BREAK_TYPES = ['lunch' => ['🍽️', 'Lunch'], 'tea' => ['☕', 'Tea'], 'drinks' => ['🥤', 'Drinks']];

function schema_v148(): void {
    if (!val("SHOW COLUMNS FROM matches LIKE 'break_type'"))
        db()->exec("ALTER TABLE matches ADD COLUMN break_type VARCHAR(10) NULL AFTER status, ADD COLUMN break_at DATETIME NULL AFTER break_type");
}

function break_label(string $type): string { return BREAK_TYPES[$type][1] ?? ucfirst($type); }

/** Break info for match_state (null when play is on) */
function match_break_info(array $m): ?array {
    if (empty($m['break_type']) || $m['status'] !== 'live') return null;
    $mins = (int)val('SELECT GREATEST(0, TIMESTAMPDIFF(MINUTE, break_at, NOW())) FROM matches WHERE id=?', [$m['id']]);
    $inn = latest_innings((int)$m['id']); $mid = null;   // mid-over break: who was bowling that over (should finish it)
    if ($inn && $inn['status'] === 'live' && (int)$inn['legal_balls'] % (int)$m['balls_per_over'] !== 0) {
        $v = val('SELECT bowler_id FROM balls WHERE innings_id=? ORDER BY seq DESC LIMIT 1', [$inn['id']]);
        $mid = $v ? (int)$v : null;
    }
    return ['type' => $m['break_type'], 'label' => break_label($m['break_type']), 'icon' => BREAK_TYPES[$m['break_type']][0] ?? '⏸️', 'mins' => $mins, 'over_bowler_id' => $mid];
}

/** Start a break: scoring stops until the next bowler is picked */
function match_break_start(array $m, array $in): void {
    $type = (string)($in['type'] ?? 'lunch');
    if (!isset(BREAK_TYPES[$type])) fail('Unknown break');
    if ($m['status'] !== 'live') fail('Breaks can only be taken once the match has started');
    if (!empty($m['break_type'])) fail(break_label($m['break_type']) . ' break is already on');
    q('UPDATE matches SET break_type=?, break_at=NOW() WHERE id=?', [$type, $m['id']]);
    $inn = latest_innings((int)$m['id']);
    if ($inn && $inn['status'] === 'live') q('UPDATE innings SET bowler_id=NULL WHERE id=?', [$inn['id']]);   // the bowler is picked again to resume
    match_log($m, break_label($type) . ' break');
}

/** End the break (called when the bowler is picked, or "Cancel break") */
function match_break_end(array $m, bool $cancelled = false): void {
    if (empty($m['break_type'])) return;
    $mins = (int)val('SELECT GREATEST(0, TIMESTAMPDIFF(MINUTE, break_at, NOW())) FROM matches WHERE id=?', [$m['id']]);
    q('UPDATE matches SET break_type=NULL, break_at=NULL WHERE id=?', [$m['id']]);
    match_log($m, $cancelled ? break_label($m['break_type']) . ' break cancelled' : 'Play resumed after ' . strtolower(break_label($m['break_type'])) . ($mins ? " ($mins min)" : ''));
}

/** Cancel a break taken by mistake: the bowler of an unfinished over is put back */
function match_break_cancel(array $m): void {
    if (empty($m['break_type'])) fail('No break is on');
    $inn = latest_innings((int)$m['id']);
    if ($inn && $inn['status'] === 'live' && !$inn['bowler_id'] && (int)$inn['legal_balls'] % (int)$m['balls_per_over'] !== 0) {
        $b = val('SELECT bowler_id FROM balls WHERE innings_id=? ORDER BY seq DESC LIMIT 1', [$inn['id']]);
        if ($b) q('UPDATE innings SET bowler_id=? WHERE id=?', [$b, $inn['id']]);
    }
    match_break_end($m, true);
}

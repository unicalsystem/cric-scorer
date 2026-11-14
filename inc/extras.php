<?php
/**
 * Extra players / subs - v1.41
 * - Club squads: a club can let teams carry more players than the squad size; the extras are subs.
 * - Matches: the tournament owner or the admin can tick "Allow extra players" (per tournament, and per match on the
 *   Pick-playing-teams screen). A side can then pick more than the playing number (e.g. 11 or 12 for an 8-a-side game);
 *   everyone picked can field and bowl, but only the playing number bat - once that many batters have come in, nobody
 *   new can bat, and "all out" / "won by N wickets" are counted from the playing number.
 */
declare(strict_types=1);

const XI_MAX_WITH_EXTRAS = 20;

function schema_v141(): void {
    if (!val("SHOW COLUMNS FROM matches LIKE 'extra_players'")) db()->exec("ALTER TABLE matches ADD COLUMN extra_players TINYINT(1) NOT NULL DEFAULT 0");
    if (!val("SHOW COLUMNS FROM tournaments LIKE 'extra_players'")) db()->exec("ALTER TABLE tournaments ADD COLUMN extra_players TINYINT(1) NOT NULL DEFAULT 0");
}

function extras_on(array $m): bool { return !empty($m['extra_players']); }

/** How many players a side may pick on the playing-teams screen */
function xi_max(array $m): int { return extras_on($m) ? max((int)$m['players_per_side'], XI_MAX_WITH_EXTRAS) : (int)$m['players_per_side']; }

/** How many of a side's picked players can bat (used for all out and "won by N wickets") */
function bat_limit(array $m, int $teamId): int {
    $n = count(xi_ids((int)$m['id'], $teamId));
    return extras_on($m) ? min($n, (int)$m['players_per_side']) : $n;
}

/** Only batters already in may bat once the playing number has batted (a retired-hurt batter can still come back) */
function batting_cap(array $inn, array $avail): array {
    static $cache = [];
    $mid = (int)$inn['match_id'];
    $cache[$mid] = $cache[$mid] ?? row('SELECT players_per_side, extra_players FROM matches WHERE id=?', [$mid]);
    $m = $cache[$mid];
    if (!$m || empty($m['extra_players'])) return $avail;
    $batted = array_map('intval', array_column(rows('SELECT player_id FROM innings_batters WHERE innings_id=?', [$inn['id']]), 'player_id'));
    foreach ([(int)$inn['striker_id'], (int)$inn['non_striker_id']] as $c) if ($c && !in_array($c, $batted, true)) $batted[] = $c;
    if (count($batted) < (int)$m['players_per_side']) return $avail;
    return array_values(array_intersect($avail, $batted));
}

/** Tournament owner, club owner (club tournaments) or admin; for a match outside a tournament, its creator or admin */
function can_set_extras(array $m, ?array $u): bool {
    $role = match_role($m, $u);
    return in_array($role, !empty($m['tournament_id']) ? ['admin', 'tournament owner', 'club owner'] : ['admin', 'creator'], true);
}

function set_match_extras(array $m, ?array $u, bool $on): void {
    if (!can_set_extras($m, $u)) fail('Only the tournament owner, club owner or admin can change this', 403);
    if ($m['status'] !== 'scheduled' || latest_innings((int)$m['id'])) fail('This can only be changed before the match starts');
    q('UPDATE matches SET extra_players=? WHERE id=?', [$on ? 1 : 0, $m['id']]);
    if (!$on) {   // switched off: trim any side that picked too many back to the playing number
        foreach ([(int)$m['team1_id'], (int)$m['team2_id']] as $tid)
            if (count(xi_ids((int)$m['id'], $tid)) > (int)$m['players_per_side']) {
                q('DELETE FROM match_players WHERE match_id=? AND team_id=? AND bat_order > ?', [$m['id'], $tid, (int)$m['players_per_side']]);
                q('UPDATE matches SET toss_winner_id=NULL, toss_decision=NULL WHERE id=?', [$m['id']]);
            }
    }
}

/** New tournament matches take the tournament's setting */
function inherit_extras(int $matchId): void {
    q('UPDATE matches m JOIN tournaments t ON t.id=m.tournament_id SET m.extra_players=t.extra_players WHERE m.id=?', [$matchId]);
}

/** Tournament setting changed: apply to its matches that have not started */
function tournament_extras_apply(int $tid): void {
    q("UPDATE matches m JOIN tournaments t ON t.id=m.tournament_id SET m.extra_players=t.extra_players
       WHERE m.tournament_id=? AND m.status='scheduled' AND NOT EXISTS (SELECT 1 FROM innings i WHERE i.match_id=m.id)", [$tid]);
}

<?php
/**
 * Club Leagues - v1.22
 * A sports club (registered as an "academy") can switch on its own league rule sheet: player categories
 * (age / gender), groups that field teams, squad sizes, a points scheme (win + runs + wickets) and
 * "no repeating players" rules. Everything here only activates for tournaments linked to that club;
 * players outside the club never see the settings.
 */
declare(strict_types=1);

function schema_v122(): void {
    if (!val("SHOW COLUMNS FROM academies LIKE 'league_json'")) db()->exec("ALTER TABLE academies ADD COLUMN league_json TEXT NULL AFTER about, ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'academy' AFTER league_json");
    if (!val("SHOW COLUMNS FROM players LIKE 'gender'")) db()->exec("ALTER TABLE players ADD COLUMN gender CHAR(1) NULL AFTER is_overseas, ADD COLUMN birth_year SMALLINT NULL AFTER gender");
    if (!val("SHOW COLUMNS FROM teams LIKE 'academy_id'")) db()->exec("ALTER TABLE teams ADD COLUMN academy_id INT NULL AFTER home_ground, ADD COLUMN league_group VARCHAR(60) NULL AFTER academy_id, ADD COLUMN league_cat VARCHAR(40) NULL AFTER league_group, ADD INDEX idx_team_academy (academy_id)");
    if (!val("SHOW COLUMNS FROM tournaments LIKE 'academy_id'")) db()->exec("ALTER TABLE tournaments ADD COLUMN academy_id INT NULL AFTER venue, ADD COLUMN visibility VARCHAR(10) NOT NULL DEFAULT 'public' AFTER academy_id, ADD INDEX idx_tour_academy (academy_id)");
}

/** Default rule sheet - what a club sees the first time it opens "League rules" */
function league_defaults(): array {
    return [
        'enabled' => false,
        'categories' => [
            ['key' => 'u20', 'name' => 'Under 20', 'min_age' => null, 'max_age' => 19, 'min_women' => 0],
            ['key' => 'open', 'name' => '20 - 50', 'min_age' => 20, 'max_age' => 50, 'min_women' => 0],
            ['key' => 'mixed', 'name' => 'Mixed (men & women)', 'min_age' => null, 'max_age' => null, 'min_women' => 3],
        ],
        'squad_size' => 10, 'playing' => 8,
        'points' => ['win' => 50, 'tie' => 25, 'no_result' => 25, 'per_runs' => 10, 'run_pts' => 2, 'wicket_pts' => 5],
        'wicket_mode' => 'all',             // all = every dismissal while bowling (run outs too); bowler = bowled/caught/lbw/stumped/hit wicket only
        'no_repeat' => [['mixed', 'open']], // category pairs that may not share a player within one group
        'groups' => [],                     // e.g. tower / block / department names; each group fields up to one team per category
        'max_teams_per_group' => 3,
    ];
}

/** Read and sanitise a rule sheet from the request */
function league_clean(array $in): array {
    $d = league_defaults();
    $cats = [];
    foreach ((array)($in['categories'] ?? []) as $i => $c) {
        if (!is_array($c)) continue;
        $name = trim(mb_substr((string)($c['name'] ?? ''), 0, 40)); if ($name === '') continue;
        $key = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string)($c['key'] ?? '')))) ?: preg_replace('/[^a-z0-9]+/', '_', strtolower($name));
        $key = trim($key, '_') ?: 'cat' . ($i + 1);
        $min = ($c['min_age'] ?? '') === '' || $c['min_age'] === null ? null : max(0, min(120, (int)$c['min_age']));
        $max = ($c['max_age'] ?? '') === '' || $c['max_age'] === null ? null : max(0, min(120, (int)$c['max_age']));
        if ($min !== null && $max !== null && $min > $max) [$min, $max] = [$max, $min];
        $cats[$key] = ['key' => $key, 'name' => $name, 'min_age' => $min, 'max_age' => $max, 'min_women' => max(0, min(15, (int)($c['min_women'] ?? 0)))];
    }
    if (!$cats) fail('Add at least one category (e.g. Under 20, 20 - 50, Mixed)');
    if (count($cats) > 8) fail('Up to 8 categories');
    $playing = max(1, min(15, (int)($in['playing'] ?? $d['playing'])));
    $squad = max($playing, min(25, (int)($in['squad_size'] ?? $d['squad_size'])));
    $p = (array)($in['points'] ?? []);
    $pts = ['win' => max(0, min(999, (int)($p['win'] ?? 50))), 'tie' => max(0, min(999, (int)($p['tie'] ?? 25))), 'no_result' => max(0, min(999, (int)($p['no_result'] ?? 25))),
            'per_runs' => max(1, min(100, (int)($p['per_runs'] ?? 10))), 'run_pts' => max(0, min(99, (int)($p['run_pts'] ?? 2))), 'wicket_pts' => max(0, min(99, (int)($p['wicket_pts'] ?? 5)))];
    $groups = [];
    foreach ((array)($in['groups'] ?? []) as $g) { $g = trim(mb_substr((string)$g, 0, 60)); if ($g !== '' && !in_array($g, $groups, true)) $groups[] = $g; }
    if (count($groups) > 40) fail('Up to 40 groups');
    $nr = [];
    foreach ((array)($in['no_repeat'] ?? []) as $pair) { if (!is_array($pair) || count($pair) !== 2) continue; [$a, $b] = array_values($pair); if (isset($cats[$a], $cats[$b]) && $a !== $b) $nr[] = [$a, $b]; }
    return ['enabled' => !empty($in['enabled']), 'categories' => array_values($cats), 'squad_size' => $squad, 'playing' => $playing, 'points' => $pts,
            'wicket_mode' => ($in['wicket_mode'] ?? 'all') === 'bowler' ? 'bowler' : 'all', 'no_repeat' => $nr, 'groups' => $groups,
            'max_teams_per_group' => max(1, min(20, (int)($in['max_teams_per_group'] ?? count($cats))))];
}

/** The club's rule sheet (null when the club has none or it is switched off) */
function league_of_academy(?int $academyId): ?array {
    if (!$academyId) return null;
    $a = row('SELECT id, name, league_json FROM academies WHERE id=?', [$academyId]);
    if (!$a || !$a['league_json']) return null;
    $cfg = json_decode($a['league_json'], true);
    if (!$cfg || empty($cfg['enabled'])) return null;
    $cfg['club_id'] = (int)$a['id']; $cfg['club_name'] = $a['name'];
    return $cfg;
}
function league_cat(array $cfg, ?string $key): ?array { foreach ($cfg['categories'] as $c) if ($c['key'] === $key) return $c; return null; }

/** Rule sheet of the tournament a match belongs to (null for ordinary matches) */
function league_of_match(array $m): ?array {
    if (empty($m['tournament_id'])) return null;
    return league_of_academy(nint(val('SELECT academy_id FROM tournaments WHERE id=?', [$m['tournament_id']])));
}

/** Clubs this user runs or belongs to that have a league switched on (drives which screens show club fields) */
function user_league_clubs(?array $u): array {
    if (!$u) return [];
    $list = rows('SELECT DISTINCT a.id, a.name, a.league_json, a.owner_id FROM academies a LEFT JOIN academy_students s ON s.academy_id=a.id AND s.user_id=? WHERE a.league_json IS NOT NULL AND (a.owner_id=? OR s.id IS NOT NULL OR ?=1) ORDER BY a.name', [$u['id'], $u['id'], !empty($u['is_admin']) ? 1 : 0]);
    $out = [];
    foreach ($list as $a) { $cfg = json_decode($a['league_json'], true); if (!$cfg || empty($cfg['enabled'])) continue; $out[] = ['id' => (int)$a['id'], 'name' => $a['name'], 'owner' => (int)$a['owner_id'] === (int)$u['id'] || !empty($u['is_admin']), 'league' => $cfg]; }
    return $out;
}
function is_club_member(int $academyId, ?array $u): bool {
    if (!$u) return false;
    if (!empty($u['is_admin'])) return true;
    return (bool)val('SELECT 1 FROM academies a LEFT JOIN academy_students s ON s.academy_id=a.id AND s.user_id=? WHERE a.id=? AND (a.owner_id=? OR s.id IS NOT NULL)', [$u['id'], $academyId, $u['id']]);
}
/** Club-only tournaments are hidden from everyone outside the club */
function can_view_tournament(array $t, ?array $u): bool {
    if (($t['visibility'] ?? 'public') !== 'club' || empty($t['academy_id'])) return true;
    return is_club_member((int)$t['academy_id'], $u);
}

/* ---------- validation ---------- */

function league_age(array $p, int $year): ?int { return $p['birth_year'] ? $year - (int)$p['birth_year'] : null; }

/** Why this player may not play in this category (null = fine) */
function league_player_problem(array $cfg, array $cat, array $p, int $year): ?string {
    $age = league_age($p, $year);
    if (($cat['min_age'] !== null || $cat['max_age'] !== null)) {
        if ($age === null) return $p['name'] . ': birth year not set (needed for ' . $cat['name'] . ')';
        if ($cat['min_age'] !== null && $age < $cat['min_age']) return $p['name'] . " is $age - under " . $cat['min_age'] . ' for ' . $cat['name'];
        if ($cat['max_age'] !== null && $age > $cat['max_age']) return $p['name'] . " is $age - over " . $cat['max_age'] . ' for ' . $cat['name'];
    }
    if ((int)$cat['min_women'] > 0 && empty($p['gender'])) return $p['name'] . ': gender not set (needed for ' . $cat['name'] . ')';
    return null;
}

/** Same person in two teams: matched by WhatsApp number, else by name */
function league_person_key(array $p): string { return $p['mobile'] ? 'm:' . $p['mobile'] : 'n:' . mb_strtolower(trim(preg_replace('/\s+/', ' ', $p['name']))); }

/** Checks a team's squad or playing list against the club rules; returns a list of problems */
function league_check_players(array $cfg, array $team, array $players, bool $playing, ?int $tournamentId = null): array {
    $probs = [];
    $cat = league_cat($cfg, $team['league_cat'] ?? null);
    if (!$cat) return [($team['name'] ?? 'Team') . ' has no league category - set it on the team page'];
    $year = (int)date('Y');
    $limit = $playing ? (int)$cfg['playing'] : (int)$cfg['squad_size'];
    if (count($players) > $limit) $probs[] = ($team['name'] ?? 'Team') . ': ' . count($players) . ' players, the limit is ' . $limit . ($playing ? ' playing' : ' in a squad');
    $women = 0;
    foreach ($players as $p) { if ($x = league_player_problem($cfg, $cat, $p, $year)) $probs[] = $x; if (($p['gender'] ?? '') === 'F') $women++; }
    if ($playing && (int)$cat['min_women'] > 0 && $women < (int)$cat['min_women']) $probs[] = ($team['name'] ?? 'Team') . ': needs at least ' . $cat['min_women'] . ' women in the playing ' . $cfg['playing'] . " (has $women)";
    // no repeating players across the categories that may not share, within the same group
    if (!empty($cfg['no_repeat']) && !empty($team['league_group'])) {
        $others = [];
        foreach ($cfg['no_repeat'] as [$a, $b]) { if ($a === $cat['key']) $others[] = $b; if ($b === $cat['key']) $others[] = $a; }
        if ($others) {
            $ph = implode(',', array_fill(0, count($others), '?'));
            $sql = "SELECT p.name, p.mobile, t.name team_name FROM players p JOIN teams t ON t.id=p.team_id WHERE t.academy_id=? AND t.league_group=? AND t.league_cat IN ($ph) AND t.id<>?";
            $args = array_merge([$cfg['club_id'], $team['league_group']], $others, [(int)$team['id']]);
            if ($tournamentId) { $sql .= ' AND t.id IN (SELECT team_id FROM tournament_teams WHERE tournament_id=?)'; $args[] = $tournamentId; }
            $seen = [];
            foreach (rows($sql, $args) as $o) $seen[league_person_key($o)] = $o['team_name'];
            foreach ($players as $p) { $k = league_person_key($p); if (isset($seen[$k])) $probs[] = $p['name'] . ' is already in ' . $seen[$k] . ' - the same player cannot be in both'; }
        }
    }
    return array_values(array_unique($probs));
}

/** Called from set_playing_xi: the playing list of each side must obey the club rules */
function league_check_xi(array $m, int $teamId, array $playerIds): void {
    $cfg = league_of_match($m); if (!$cfg) return;
    $team = row('SELECT * FROM teams WHERE id=?', [$teamId]);
    if (!$team || empty($team['league_cat'])) return;   // teams without a category play as ordinary teams
    $ph = implode(',', array_fill(0, max(1, count($playerIds)), '?'));
    $players = $playerIds ? rows("SELECT * FROM players WHERE id IN ($ph)", $playerIds) : [];
    $probs = league_check_players($cfg, $team, $players, true, (int)$m['tournament_id']);
    if ($probs) fail('Club rules: ' . implode(' · ', $probs));
}

/** Called from player_save: a club team's squad may not exceed the squad size */
function league_check_squad_add(array $team): void {
    if (empty($team['academy_id']) || empty($team['league_cat'])) return;
    $cfg = league_of_academy((int)$team['academy_id']); if (!$cfg) return;
    $n = (int)val('SELECT COUNT(*) FROM players WHERE team_id=?', [$team['id']]);
    if ($n >= (int)$cfg['squad_size']) fail('Club rules: a squad has at most ' . $cfg['squad_size'] . ' players (' . $cfg['playing'] . ' playing + ' . ($cfg['squad_size'] - $cfg['playing']) . ' extra)');
}

/* ---------- points ---------- */

/** League points for every team of a club tournament, plus per-group totals */
function league_points(int $tid, array $cfg): array {
    $teams = rows('SELECT t.id, t.name, t.short_name, t.color, t.league_group, t.league_cat FROM tournament_teams tt JOIN teams t ON t.id=tt.team_id WHERE tt.tournament_id=? ORDER BY t.name', [$tid]);
    $tbl = [];
    foreach ($teams as $t) $tbl[(int)$t['id']] = ['id' => (int)$t['id'], 'name' => $t['name'], 'short_name' => $t['short_name'], 'color' => $t['color'], 'group' => $t['league_group'], 'cat' => $t['league_cat'],
        'p' => 0, 'w' => 0, 'l' => 0, 't' => 0, 'nr' => 0, 'match_pts' => 0, 'runs' => 0, 'run_pts' => 0, 'wkts' => 0, 'wkt_pts' => 0, 'pts' => 0];
    $P = $cfg['points'];
    foreach (rows("SELECT * FROM matches WHERE tournament_id=? AND status IN ('completed','abandoned')", [$tid]) as $m) {
        $a = (int)$m['team1_id']; $b = (int)$m['team2_id'];
        if (!isset($tbl[$a]) || !isset($tbl[$b])) continue;
        $tbl[$a]['p']++; $tbl[$b]['p']++;
        if ($m['status'] === 'abandoned') { foreach ([$a, $b] as $x) { $tbl[$x]['nr']++; $tbl[$x]['match_pts'] += $P['no_result']; } continue; }
        if (!$m['winner_team_id']) { foreach ([$a, $b] as $x) { $tbl[$x]['t']++; $tbl[$x]['match_pts'] += $P['tie']; } }
        else { $w = (int)$m['winner_team_id']; $l = $w === $a ? $b : $a; $tbl[$w]['w']++; $tbl[$w]['match_pts'] += $P['win']; $tbl[$l]['l']++; }
        if (!empty($m['bonus_json'])) foreach ((json_decode($m['bonus_json'], true) ?: []) as $bt => $bp) if (isset($tbl[(int)$bt])) { $tbl[(int)$bt]['match_pts'] += (int)$bp; $tbl[(int)$bt]['bonus'] = ($tbl[(int)$bt]['bonus'] ?? 0) + (int)$bp; }
        // runs per batter (every N runs = X pts, per player, rounded down) and wickets while bowling
        foreach (rows('SELECT id, batting_team_id, bowling_team_id FROM innings WHERE match_id=? AND is_super=0', [$m['id']]) as $i) {
            $bt = (int)$i['batting_team_id']; $bw = (int)$i['bowling_team_id'];
            foreach (rows('SELECT batsman_id, SUM(runs_bat) r FROM balls WHERE innings_id=? GROUP BY batsman_id', [$i['id']]) as $r) {
                $tbl[$bt]['runs'] += (int)$r['r']; $tbl[$bt]['run_pts'] += intdiv((int)$r['r'], $P['per_runs']) * $P['run_pts'];
            }
            $wk = ($cfg['wicket_mode'] ?? 'all') === 'bowler'
                ? (int)val('SELECT COUNT(*) FROM balls WHERE innings_id=? AND is_wicket=1 AND wicket_type IN (' . implode(',', array_fill(0, count(BOWLER_WICKETS), '?')) . ')', array_merge([$i['id']], BOWLER_WICKETS))
                : (int)val("SELECT COUNT(*) FROM balls WHERE innings_id=? AND is_wicket=1 AND wicket_type NOT IN ('retired hurt','retired out','retired')", [$i['id']]);
            $tbl[$bw]['wkts'] += $wk; $tbl[$bw]['wkt_pts'] += $wk * $P['wicket_pts'];
        }
    }
    foreach ($tbl as &$r) $r['pts'] = $r['match_pts'] + $r['run_pts'] + $r['wkt_pts'];
    unset($r);
    $list = array_values($tbl);
    usort($list, fn($x, $y) => $y['pts'] <=> $x['pts'] ?: $y['w'] <=> $x['w'] ?: strcmp($x['name'], $y['name']));
    $groups = [];
    foreach ($list as $r) {
        $g = $r['group'] ?: 'No group';
        $groups[$g] = $groups[$g] ?? ['group' => $g, 'teams' => 0, 'p' => 0, 'w' => 0, 'match_pts' => 0, 'run_pts' => 0, 'wkt_pts' => 0, 'pts' => 0];
        $groups[$g]['teams']++; foreach (['p', 'w', 'match_pts', 'run_pts', 'wkt_pts', 'pts'] as $k) $groups[$g][$k] += $r[$k];
    }
    $gl = array_values($groups);
    usort($gl, fn($x, $y) => $y['pts'] <=> $x['pts'] ?: strcmp($x['group'], $y['group']));
    return ['teams' => $list, 'groups' => $gl];
}

/* ---------- fixtures ---------- */

/** Same category, different group - every such pair once */
function league_fixture_pairs(array $cfg, array $teamIds): array {
    $ph = implode(',', array_fill(0, max(1, count($teamIds)), '?'));
    $teams = $teamIds ? rows("SELECT id, name, league_group, league_cat FROM teams WHERE id IN ($ph) ORDER BY league_cat, league_group, name", $teamIds) : [];
    $pairs = [];
    foreach ($cfg['categories'] as $cat) {
        $in = array_values(array_filter($teams, fn($t) => $t['league_cat'] === $cat['key']));
        $n = 0;
        for ($i = 0; $i < count($in); $i++) for ($j = $i + 1; $j < count($in); $j++) {
            if ($in[$i]['league_group'] && $in[$i]['league_group'] === $in[$j]['league_group']) continue;   // same group never play each other
            $pairs[] = [(int)$in[$i]['id'], (int)$in[$j]['id'], $cat['name'] . ' - Match ' . (++$n)];
        }
    }
    return $pairs;
}

/** Everything the app needs to show a club tournament: rules + points + group standings */
function league_tournament_extra(array $t): array {
    $cfg = league_of_academy(nint($t['academy_id'] ?? null));
    if (!$cfg) return ['league' => null];
    return ['league' => $cfg, 'league_points' => league_points((int)$t['id'], $cfg)];
}

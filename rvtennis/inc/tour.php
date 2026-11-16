<?php
/* RV Tennis - players, matches and tournaments (round robin groups and knockout draws) */

/** Find a club player by name, or add them */
function tn_player(string $name, ?int $by = null): int {
    $name = mb_substr(trim(preg_replace('/\s+/', ' ', $name)), 0, 80);
    if ($name === '') fail('Every player needs a name');
    $id = val('SELECT id FROM tn_players WHERE name=?', [$name]);
    if ($id) return (int)$id;
    q('INSERT INTO tn_players (name, created_by) VALUES (?,?)', [$name, $by]);
    return lastId();
}
/** player id → team id, for everyone who is in a team (one small query per request) */
function tn_team_map(bool $fresh = false): array {
    static $map = null;
    if ($map === null || $fresh) { $map = []; foreach (rows('SELECT id, team_id FROM tn_players WHERE team_id IS NOT NULL') as $r) $map[(int)$r['id']] = (int)$r['team_id']; }
    return $map;
}
/** The team a side plays for: every player on the side is in the same team */
function tn_side_team($p1, $p2): ?int {
    $map = tn_team_map();
    $a = $map[(int)$p1] ?? null;
    if (!$p2) return $a;
    return $a !== null && $a === ($map[(int)$p2] ?? null) ? $a : null;
}
function tn_teams(): array {
    return array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name'], 'logo' => $t['logo'], 'color' => $t['color']], rows('SELECT * FROM tn_teams ORDER BY name'));
}
/** Put players into a team; with $onlyEmpty, players who already have a team keep it */
function tn_assign_team(array $playerIds, ?int $teamId, bool $onlyEmpty = false): void {
    if ($teamId !== null && !val('SELECT 1 FROM tn_teams WHERE id=?', [$teamId])) fail('That team was not found');
    foreach (array_filter($playerIds) as $pid) q('UPDATE tn_players SET team_id=? WHERE id=?' . ($onlyEmpty ? ' AND team_id IS NULL' : ''), [$teamId, $pid]);
    tn_team_map(true);
}

/** "Asha / Meera" → [id, id]; "Asha" → [id, null] */
function tn_side(string $text, bool $doubles, ?int $by): array {
    $parts = array_values(array_filter(array_map('trim', preg_split('#\s*(?:/|&|\+|,| and )\s*#i', $text)), 'strlen'));
    if ($doubles) {
        if (count($parts) !== 2) fail("Doubles needs two names for each pair, written like \"Asha / Meera\" - check \"$text\"");
        $a = tn_player($parts[0], $by); $b = tn_player($parts[1], $by);
        if ($a === $b) fail("A pair needs two different players - check \"$text\"");
        return [$a, $b];
    }
    if (count($parts) !== 1) fail("Singles takes one name per side - check \"$text\"");
    return [tn_player($parts[0], $by), null];
}
function tn_side_name(int $p1, ?int $p2): string {
    $a = (string)val('SELECT name FROM tn_players WHERE id=?', [$p1]);
    return $p2 ? $a . ' / ' . val('SELECT name FROM tn_players WHERE id=?', [$p2]) : $a;
}

function tn_can_score(array $m, ?array $u): bool {
    if (!$u) return false;
    if (!empty($u['tn_admin']) || (int)$m['owner_id'] === (int)$u['id'] || (int)($m['scorer_id'] ?? 0) === (int)$u['id']) return true;
    return $m['tour_id'] && (int)val('SELECT owner_id FROM tn_tours WHERE id=?', [$m['tour_id']]) === (int)$u['id'];
}
function tn_round_name(int $round, int $rounds): string {
    $left = $rounds - $round;
    return $left === 0 ? 'Final' : ($left === 1 ? 'Semi-final' : ($left === 2 ? 'Quarter-final' : 'Round of ' . (2 ** ($left + 1))));
}
/** A match as the phone sees it */
function tn_match_out(array $m, ?array $u, bool $full = true): array {
    $o = ['id' => (int)$m['id'], 'a' => $m['a_name'], 'b' => $m['b_name'], 'doubles' => (bool)$m['doubles'], 'status' => $m['status'],
        'winner' => $m['winner'] === null ? null : (int)$m['winner'], 'summary' => $m['summary'], 'court' => $m['court'],
        'tour_id' => $m['tour_id'] ? (int)$m['tour_id'] : null, 'round' => $m['round'] === null ? null : (int)$m['round'], 'grp' => $m['grp'] === null ? null : (int)$m['grp'],
        'sched_at' => $m['sched_at'], 'started_at' => $m['started_at'], 'finished_at' => $m['finished_at'], 'rev' => $m['updated_at'],
        'players' => [[$m['a1'] ? (int)$m['a1'] : null, $m['a2'] ? (int)$m['a2'] : null], [$m['b1'] ? (int)$m['b1'] : null, $m['b2'] ? (int)$m['b2'] : null]],
        'teams' => [$m['a1'] ? tn_side_team($m['a1'], $m['a2']) : null, $m['b1'] ? tn_side_team($m['b1'], $m['b2']) : null]];
    $st = $m['state'] ? json_decode($m['state'], true) : null;
    if (!$full) {   // list rows carry just the score line
        if ($st) $o['mini'] = ['sets' => $st['sets'], 'games' => $st['games'], 'label' => $st['label'], 'server' => $st['server'][0] ?? null, 'tb' => $st['tb']];
        if (isset($m['tour_name'])) $o['tour'] = $m['tour_name'];
        return $o;
    }
    $f = tn_format($m['format']);
    $o['state'] = $st ?: tn_replay($f, (bool)$m['doubles'], (int)$m['first_srv'], [])->state();
    $o['format_label'] = tn_format_label($f);
    $o['first_srv'] = (int)$m['first_srv'];
    $o['can_score'] = tn_can_score($m, $u);
    $o['scorer'] = $m['scorer_id'] ? val('SELECT display_name FROM users WHERE id=?', [$m['scorer_id']]) : null;
    $o['owner'] = val('SELECT display_name FROM users WHERE id=?', [$m['owner_id']]);
    if ($m['tour_id']) {
        $t = row('SELECT name, kind FROM tn_tours WHERE id=?', [$m['tour_id']]);
        $o['tour'] = $t['name'] ?? null;
        if ($t && $t['kind'] === 'ko') $o['round_name'] = tn_round_name((int)$m['round'], (int)val('SELECT MAX(round) FROM tn_matches WHERE tour_id=?', [$m['tour_id']]));
        elseif ($t) $o['round_name'] = (int)val('SELECT grp_count FROM tn_tours WHERE id=?', [$m['tour_id']]) > 1 ? 'Group ' . chr(65 + (int)$m['grp']) : 'Round robin';
    }
    $names = [];
    foreach (rows('SELECT id, name FROM tn_players WHERE id IN (?,?,?,?)', [(int)$m['a1'], (int)$m['a2'], (int)$m['b1'], (int)$m['b2']]) as $p) $names[(int)$p['id']] = $p['name'];
    $o['names'] = [[$names[(int)$m['a1']] ?? $m['a_name'], $names[(int)$m['a2']] ?? null], [$names[(int)$m['b1']] ?? $m['b_name'], $names[(int)$m['b2']] ?? null]];
    return $o;
}

/** Save the events of a match: recompute the score, status and (in a knockout) who moves on */
function tn_store(array $m, array $events, ?array $manual = null): array {
    $f = tn_format($m['format']);
    $st = $manual ?: tn_replay($f, (bool)$m['doubles'], (int)$m['first_srv'], $events)->state();
    $wasDone = $m['status'] === 'done';
    $status = $st['over'] ? 'done' : (($events || $m['status'] === 'live') ? 'live' : 'scheduled');
    if ($wasDone && ($status !== 'done' || (int)$st['winner'] !== (int)$m['winner'])) tn_ko_retract($m);
    // Times are worked out here and sent as plain values, so the statement is the same on every MySQL / MariaDB version
    $now = date('Y-m-d H:i:s');
    $started = $status === 'scheduled' ? null : ($m['started_at'] ?: $now);
    $finished = $status === 'done' ? ($m['finished_at'] ?: $now) : null;
    q('UPDATE tn_matches SET events=?, state=?, status=?, winner=?, summary=?, started_at=?, finished_at=? WHERE id=?',
      [json_encode($events), json_encode($st), $status, $st['over'] ? (int)$st['winner'] : null, tn_summary($st['sets'], $st['outcome']) ?: null, $started, $finished, $m['id']]);
    $m = row('SELECT * FROM tn_matches WHERE id=?', [$m['id']]);
    if ($status === 'done') tn_ko_advance($m);
    return $m;
}
function tn_ko_next(array $m): ?array {
    if (!$m['tour_id'] || $m['round'] === null || val('SELECT kind FROM tn_tours WHERE id=?', [$m['tour_id']]) !== 'ko') return null;
    return row('SELECT * FROM tn_matches WHERE tour_id=? AND round=? AND slot=?', [$m['tour_id'], (int)$m['round'] + 1, intdiv((int)$m['slot'], 2)]);
}
/** The winner takes their place in the next round */
function tn_ko_advance(array $m): void {
    $next = tn_ko_next($m);
    if (!$next || $m['winner'] === null) return;
    $w = (int)$m['winner'] === 0 ? 'a' : 'b'; $side = (int)$m['slot'] % 2 === 0 ? 'a' : 'b';
    q("UPDATE tn_matches SET entry_$side=?, {$side}1=?, {$side}2=?, {$side}_name=? WHERE id=?", [$m["entry_$w"], $m["{$w}1"], $m["{$w}2"], $m["{$w}_name"], $next['id']]);
}
/** A finished knockout match is reopened: take its winner back out of the next round (unless that has started) */
function tn_ko_retract(array $m): void {
    $next = tn_ko_next($m);
    if (!$next) return;
    if ($next['status'] !== 'scheduled') fail('The next round of this draw has already started, so this result can no longer be changed here.');
    $side = (int)$m['slot'] % 2 === 0 ? 'a' : 'b';
    q("UPDATE tn_matches SET entry_$side=NULL, {$side}1=NULL, {$side}2=NULL, {$side}_name='' WHERE id=?", [$next['id']]);
}

/** Where each seed sits in a draw of $size: [1, 8, 4, 5, 2, 7, 3, 6] for 8 */
function tn_seed_order(int $size): array {
    $o = [1];
    while (count($o) < $size) { $n = count($o) * 2; $x = []; foreach ($o as $s) { $x[] = $s; $x[] = $n + 1 - $s; } $o = $x; }
    return $o;
}
function tn_tour_create(array $in, array $u): int {
    $name = str_in($in, 'name', 120);
    if ($name === '') fail('Give the tournament a name');
    $kind = ($in['kind'] ?? '') === 'ko' ? 'ko' : 'rr';
    $doubles = !empty($in['doubles']);
    $f = tn_format($in['format'] ?? 'bo3');
    $lines = is_array($in['entries'] ?? null) ? $in['entries'] : preg_split('/\R/', (string)($in['entries'] ?? ''));
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
    if (count($lines) < 2) fail('Add at least two ' . ($doubles ? 'pairs' : 'players') . ', one on each line');
    if (count($lines) > 64) fail('A tournament can have up to 64 entries');
    $groups = $kind === 'rr' ? max(1, min(8, int_in($in, 'groups', 1))) : 1;
    if ($kind === 'rr' && count($lines) < $groups * 2) fail("With $groups groups you need at least " . ($groups * 2) . ' entries');
    db()->beginTransaction();
    try {
        q('INSERT INTO tn_tours (name, kind, doubles, format, grp_count, owner_id) VALUES (?,?,?,?,?,?)', [$name, $kind, $doubles ? 1 : 0, json_encode($f), $groups, $u['id']]);
        $tid = lastId();
        $entries = []; $seen = [];
        foreach ($lines as $i => $line) {
            [$p1, $p2] = tn_side($line, $doubles, (int)$u['id']);
            foreach (array_filter([$p1, $p2]) as $p) { if (isset($seen[$p])) fail('"' . val('SELECT name FROM tn_players WHERE id=?', [$p]) . '" is entered twice'); $seen[$p] = 1; }
            $g = $kind === 'rr' ? ($groups > 1 ? (intdiv($i, $groups) % 2 === 0 ? $i % $groups : $groups - 1 - $i % $groups) : 0) : 0;   // snake seeding across groups
            $nm = tn_side_name($p1, $p2);
            q('INSERT INTO tn_entries (tour_id, pos, grp, p1, p2, name) VALUES (?,?,?,?,?,?)', [$tid, $i + 1, $g, $p1, $p2, $nm]);
            $entries[] = ['id' => lastId(), 'grp' => $g, 'p1' => $p1, 'p2' => $p2, 'name' => $nm];
        }
        $ins = function (?array $a, ?array $b, int $round, int $slot, ?int $grp, string $status = 'scheduled', ?int $winner = null) use ($tid, $u, $doubles, $f) {
            q('INSERT INTO tn_matches (owner_id, tour_id, round, slot, grp, entry_a, entry_b, doubles, a1, a2, b1, b2, a_name, b_name, format, status, winner) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$u['id'], $tid, $round, $slot, $grp, $a['id'] ?? null, $b['id'] ?? null, $doubles ? 1 : 0, $a['p1'] ?? null, $a['p2'] ?? null, $b['p1'] ?? null, $b['p2'] ?? null,
               $a['name'] ?? '', $b['name'] ?? '', json_encode($f), $status, $winner]);
            return lastId();
        };
        if ($kind === 'rr') {
            for ($g = 0; $g < $groups; $g++) {
                $list = array_values(array_filter($entries, fn($e) => $e['grp'] === $g));
                if (count($list) % 2) $list[] = null;                         // circle method: everyone plays once per round
                $n = count($list); $slot = 0;
                for ($r = 0; $r < $n - 1; $r++) {
                    for ($i = 0; $i < $n / 2; $i++) {
                        $a = $list[$i]; $b = $list[$n - 1 - $i];
                        if ($a && $b) $ins($a, $b, $r + 1, $slot++, $g);
                    }
                    array_splice($list, 1, 0, [array_pop($list)]);
                }
            }
        } else {
            $size = 2; while ($size < count($entries)) $size *= 2;
            $order = tn_seed_order($size); $rounds = (int)log($size, 2);
            for ($r = 1; $r <= $rounds; $r++) for ($s = 0; $s < $size / (2 ** $r); $s++) {
                if ($r > 1) { $ins(null, null, $r, $s, null); continue; }
                $a = $entries[$order[2 * $s] - 1] ?? null; $b = $entries[$order[2 * $s + 1] - 1] ?? null;
                $bye = !$a || !$b;
                $ins($a, $b, 1, $s, null, $bye ? 'bye' : 'scheduled', $bye ? ($a ? 0 : 1) : null);
            }
            foreach (rows("SELECT * FROM tn_matches WHERE tour_id=? AND status='bye'", [$tid]) as $m) tn_ko_advance($m);
        }
        db()->commit();
        return $tid;
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}
/** Group tables for a round robin: played, won, lost, sets and games, sorted the usual way */
function tn_standings(int $tid): array {
    $tab = [];
    foreach (rows('SELECT * FROM tn_entries WHERE tour_id=? ORDER BY pos', [$tid]) as $e)
        $tab[(int)$e['id']] = ['id' => (int)$e['id'], 'name' => $e['name'], 'grp' => (int)$e['grp'], 'p' => 0, 'w' => 0, 'l' => 0, 'sf' => 0, 'sa' => 0, 'gf' => 0, 'ga' => 0];
    foreach (rows("SELECT entry_a, entry_b, winner, state FROM tn_matches WHERE tour_id=? AND status='done'", [$tid]) as $m) {
        $a = (int)$m['entry_a']; $b = (int)$m['entry_b'];
        if (!isset($tab[$a], $tab[$b])) continue;
        $w = (int)$m['winner'];
        $tab[$a]['p']++; $tab[$b]['p']++;
        $tab[$w === 0 ? $a : $b]['w']++; $tab[$w === 0 ? $b : $a]['l']++;
        foreach ((json_decode((string)$m['state'], true) ?: [])['sets'] ?? [] as $s) {
            $x = $s['a'] > $s['b'] ? $a : $b; $y = $x === $a ? $b : $a;
            $tab[$x]['sf']++; $tab[$y]['sa']++;
            $ga = !empty($s['mtb']) ? ($s['a'] > $s['b'] ? 1 : 0) : $s['a']; $gb = !empty($s['mtb']) ? ($s['b'] > $s['a'] ? 1 : 0) : $s['b'];
            $tab[$a]['gf'] += $ga; $tab[$a]['ga'] += $gb; $tab[$b]['gf'] += $gb; $tab[$b]['ga'] += $ga;
        }
    }
    $tab = array_values($tab);
    usort($tab, fn($x, $y) => [$x['grp'], $y['w'], $y['sf'] - $y['sa'], $y['gf'] - $y['ga'], $x['l']] <=> [$y['grp'], $x['w'], $x['sf'] - $x['sa'], $x['gf'] - $x['ga'], $y['l']]);
    return $tab;
}

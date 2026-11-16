<?php
/* RV Tennis - JSON API. Reads: GET api.php?a=...   Changes: POST api.php?a=... with a JSON body. */
require_once __DIR__ . '/inc/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$a = preg_replace('/[^a-z_]/', '', (string)($_GET['a'] ?? ''));
$in = [];
try {
    $post = $_SERVER['REQUEST_METHOD'] === 'POST';
    if ($post) {
        // Changes must come from our own pages: a JSON body cannot be sent by a plain cross-site form
        if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === false) fail('Bad request');
        $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    }
    $reads = ['boot', 'home', 'match', 'tours', 'tour', 'players', 'player', 'admins', 'teams', 'team'];
    if (!$post && !in_array($a, $reads, true)) fail('Bad request', 405);
    tn_schema();
    $u = tn_user();
    $mget = function (int $id) { $m = row('SELECT * FROM tn_matches WHERE id=?', [$id]); if (!$m) fail('That match was not found', 404); return $m; };
    $mscore = function (int $id) use ($mget, $u) {
        tn_require();
        $m = $mget($id);
        if (!tn_can_score($m, $u)) fail('Only the scorer of this match can change it', 403);
        return $m;
    };
    $list = 'SELECT m.*, t.name tour_name FROM tn_matches m LEFT JOIN tn_tours t ON t.id=m.tour_id';
    $mini = fn(array $ms) => array_map(fn($m) => tn_match_out($m, $u, false), $ms);

    switch ($a) {
    case 'boot':
        $out = ['user' => tn_public_user($u), 'version' => TN_VERSION, 'club' => TN_CLUB, 'name' => TN_NAME, 'main_site' => TN_MAIN_SITE,
            'google_client_id' => tn_google_client() ?: null, 'whatsapp' => tn_wa_enabled() || DEBUG,
            'presets' => array_map(fn($p) => $p['label'], TN_PRESETS), 'teams' => tn_teams(), 'logo' => is_file(__DIR__ . '/assets/logo.png') ? 'assets/logo.png?v=' . filemtime(__DIR__ . '/assets/logo.png') : null];
        break;

    /* ----- sign-in ----- */
    case 'login':      $out = tn_password_login($in); break;
    case 'google':     $out = tn_google($in); break;
    case 'wa_request': $out = tn_wa_request($in); break;
    case 'wa_verify':  $out = tn_wa_verify($in); break;
    case 'logout':     tn_logout(); $out = ['ok' => true]; break;

    /* ----- matches ----- */
    case 'home':
        $out = [
            'live' => $mini(rows("$list WHERE m.status='live' ORDER BY m.updated_at DESC LIMIT 20")),
            'next' => $mini(rows("$list WHERE m.status='scheduled' AND m.a_name<>'' AND m.b_name<>'' ORDER BY (m.sched_at IS NULL), m.sched_at, m.tour_id, m.round, m.slot LIMIT 30")),
            'done' => $mini(rows("$list WHERE m.status='done' ORDER BY m.finished_at DESC LIMIT 30")),
        ];
        break;
    case 'match':
        $m = $mget((int)($_GET['id'] ?? 0));
        if (isset($_GET['rev']) && $_GET['rev'] === $m['updated_at']) { $out = ['same' => true]; break; }
        $out = ['match' => tn_match_out($m, $u)];
        break;
    case 'match_new':
        tn_require();
        $doubles = !empty($in['doubles']);
        [$a1, $a2] = tn_side(str_in($in, 'a', 170), $doubles, (int)$u['id']);
        [$b1, $b2] = tn_side(str_in($in, 'b', 170), $doubles, (int)$u['id']);
        if (count(array_unique(array_filter([$a1, $a2, $b1, $b2]))) !== ($doubles ? 4 : 2)) fail('The same player is on both sides');
        $when = str_in($in, 'sched_at', 20);
        $when = $when !== '' && strtotime($when) ? date('Y-m-d H:i:s', strtotime($when)) : null;
        q('INSERT INTO tn_matches (owner_id, doubles, a1, a2, b1, b2, a_name, b_name, format, first_srv, court, sched_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
          [$u['id'], $doubles ? 1 : 0, $a1, $a2, $b1, $b2, tn_side_name($a1, $a2), tn_side_name($b1, $b2), json_encode(tn_format($in['format'] ?? 'bo3')),
           !empty($in['first_srv']) ? 1 : 0, str_in($in, 'court', 40) ?: null, $when]);
        $out = ['id' => lastId()];
        // a team picked on the form goes to the players of that side who are not in a team yet
        if (int_in($in, 'team_a')) tn_assign_team([$a1, $a2], int_in($in, 'team_a'), true);
        if (int_in($in, 'team_b')) tn_assign_team([$b1, $b2], int_in($in, 'team_b'), true);
        break;
    case 'point':
        $m = $mscore(int_in($in, 'id'));
        if ($m['a_name'] === '' || $m['b_name'] === '') fail('This match is still waiting for a player from the previous round');
        $ev = json_decode((string)$m['events'], true) ?: [];
        // "n" is how many events the phone had seen: a double tap or a second scorer cannot add a point twice
        if (int_in($in, 'n', -1) === count($ev) && $m['status'] !== 'done') {
            $k = in_array($in['k'] ?? '', ['ace', 'df', 'win', 'fe', 'ue'], true) ? $in['k'] : '';
            $ev[] = ['w' => !empty($in['w']) ? 1 : 0] + ($k ? ['k' => $k] : []);
            $m = tn_store($m, $ev);
        }
        $out = ['match' => tn_match_out($m, $u)];
        break;
    case 'ev':   // everything a scorer records that is not a plain point: fault, let, doubles order, no-ad court choice
        $m = $mscore(int_in($in, 'id'));
        if ($m['a_name'] === '' || $m['b_name'] === '') fail('This match is still waiting for a player from the previous round');
        $ev = json_decode((string)$m['events'], true) ?: [];
        $t = (string)($in['t'] ?? '');
        if (!in_array($t, ['f', 'let', 'ord', 'dc'], true)) fail('Bad request');
        if (int_in($in, 'n', -1) === count($ev) && $m['status'] !== 'done') {
            $e = ['t' => $t];
            if ($t === 'dc') $e['c'] = !empty($in['c']) ? 1 : 0;
            if ($t === 'ord') {
                if (!$m['doubles']) fail('Serving order is for doubles');
                if (empty(tn_replay(tn_format($m['format']), true, (int)$m['first_srv'], $ev)->atSetStart())) fail('The serving order can only change at the start of a set');
                foreach (['s', 'r'] as $key) if (is_array($in[$key] ?? null)) $e[$key] = [!empty($in[$key][0]) ? 1 : 0, !empty($in[$key][1]) ? 1 : 0];
                // one order per set start: a second choice replaces the first
                while ($ev && (end($ev)['t'] ?? '') === 'ord') { $prev = array_pop($ev); $e += array_intersect_key($prev, ['s' => 1, 'r' => 1]); }
            }
            $ev[] = $e;
            $m = tn_store($m, $ev);
        }
        $out = ['match' => tn_match_out($m, $u)];
        break;
    case 'tag':   // optional: say how the last point ended (winner, forced error, unforced error)
        $m = $mscore(int_in($in, 'id'));
        $ev = json_decode((string)$m['events'], true) ?: [];
        $i = count($ev) - 1;
        if (int_in($in, 'n', -1) === count($ev) && $i >= 0 && isset($ev[$i]['w']) && !isset($ev[$i]['t']) && !in_array($ev[$i]['k'] ?? '', ['ace', 'df'], true)) {
            $k = in_array($in['k'] ?? '', ['win', 'fe', 'ue'], true) ? $in['k'] : '';
            if ($k === '' || ($ev[$i]['k'] ?? '') === $k) unset($ev[$i]['k']); else $ev[$i]['k'] = $k;   // tapping the same tag again clears it
            $m = tn_store($m, $ev);
        }
        $out = ['match' => tn_match_out($m, $u)];
        break;
    case 'undo':
        $m = $mscore(int_in($in, 'id'));
        $ev = json_decode((string)$m['events'], true) ?: [];
        $manual = !empty(json_decode((string)$m['state'], true)['manual']);
        if (!$manual && (int_in($in, 'n', -1) !== count($ev) || !$ev)) { $out = ['match' => tn_match_out($m, $u)]; break; }
        if ($manual) $ev = []; else array_pop($ev);
        if (!array_filter($ev, fn($e) => ($e['t'] ?? '') !== 'ord')) {   // nothing played is left: back to "not started"
            if ($m['status'] === 'done') tn_ko_retract($m);
            q("UPDATE tn_matches SET status='scheduled', started_at=NULL, finished_at=NULL, winner=NULL WHERE id=?", [$m['id']]);
            $m = $mget((int)$m['id']);
        }
        $m = tn_store($m, $ev);
        $out = ['match' => tn_match_out($m, $u)];
        break;
    case 'match_end':   // retirement or walkover: "w" is the side that wins
        $m = $mscore(int_in($in, 'id'));
        if ($m['status'] === 'done') fail('This match is already finished');
        if ($m['a_name'] === '' || $m['b_name'] === '') fail('This match is still waiting for a player from the previous round');
        $ev = json_decode((string)$m['events'], true) ?: [];
        $ev[] = ['t' => in_array($in['why'] ?? '', ['wo', 'def'], true) ? $in['why'] : 'ret', 'w' => !empty($in['w']) ? 1 : 0];
        $out = ['match' => tn_match_out(tn_store($m, $ev), $u)];
        break;
    case 'match_result':   // type in a finished score instead of scoring point by point
        $m = $mscore(int_in($in, 'id'));
        if ($m['a_name'] === '' || $m['b_name'] === '') fail('This match is still waiting for a player from the previous round');
        if (json_decode((string)$m['events'], true) && empty($in['replace'])) fail('This match has points scored already. Undo them first, or confirm replacing them.');
        try { $st = tn_manual_state(tn_format($m['format']), (bool)$m['doubles'], is_array($in['sets'] ?? null) ? $in['sets'] : []); }
        catch (InvalidArgumentException $e) { fail($e->getMessage()); }
        $out = ['match' => tn_match_out(tn_store($m, [], $st), $u)];
        break;
    case 'match_edit':
        $m = $mscore(int_in($in, 'id'));
        $set = []; $p = [];
        if (array_key_exists('court', $in)) { $set[] = 'court=?'; $p[] = str_in($in, 'court', 40) ?: null; }
        if (array_key_exists('sched_at', $in)) { $w = str_in($in, 'sched_at', 20); $set[] = 'sched_at=?'; $p[] = $w !== '' && strtotime($w) ? date('Y-m-d H:i:s', strtotime($w)) : null; }
        if (array_key_exists('scorer', $in)) {   // hand the scoring to another member, found by username or mobile number
            $s = str_in($in, 'scorer', 60); $sid = null;
            if ($s !== '') {
                $sid = val('SELECT id FROM users WHERE username=?', [ltrim($s, '@')]) ?: (($mb = tn_mobile($s)) ? val('SELECT id FROM users WHERE mobile=?', [$mb]) : null);
                if (!$sid) fail('No member found with that username or mobile number. They need to sign in to RV Tennis once first.');
            }
            $set[] = 'scorer_id=?'; $p[] = $sid;
        }
        if ($m['status'] === 'scheduled') {
            if (array_key_exists('first_srv', $in)) { $set[] = 'first_srv=?'; $p[] = !empty($in['first_srv']) ? 1 : 0; }
            if (array_key_exists('format', $in)) { $set[] = 'format=?'; $p[] = json_encode(tn_format($in['format'])); }
            if (!empty($in['swap_a']) && $m['a2']) { $set[] = 'a1=?, a2=?'; array_push($p, $m['a2'], $m['a1']); }
            if (!empty($in['swap_b']) && $m['b2']) { $set[] = 'b1=?, b2=?'; array_push($p, $m['b2'], $m['b1']); }
            $set[] = 'state=NULL';
        }
        if ($set) { $p[] = $m['id']; q('UPDATE tn_matches SET ' . implode(', ', $set) . ' WHERE id=?', $p); }
        $out = ['match' => tn_match_out($mget((int)$m['id']), $u)];
        break;
    case 'match_delete':
        $m = $mscore(int_in($in, 'id'));
        if ($m['tour_id']) fail('This match belongs to a tournament. Delete the tournament, or undo the score instead.');
        q('DELETE FROM tn_matches WHERE id=?', [$m['id']]);
        $out = ['ok' => true];
        break;

    /* ----- tournaments ----- */
    case 'tours':
        $out = ['tours' => rows("SELECT t.id, t.name, t.kind, t.doubles, t.created_at, (SELECT COUNT(*) FROM tn_entries e WHERE e.tour_id=t.id) entries,
            (SELECT COUNT(*) FROM tn_matches m WHERE m.tour_id=t.id AND m.status='done') done,
            (SELECT COUNT(*) FROM tn_matches m WHERE m.tour_id=t.id AND m.status<>'bye') total FROM tn_tours t ORDER BY t.id DESC")];
        break;
    case 'tour':
        $t = row('SELECT * FROM tn_tours WHERE id=?', [(int)($_GET['id'] ?? 0)]);
        if (!$t) fail('That tournament was not found', 404);
        $ms = rows('SELECT * FROM tn_matches WHERE tour_id=? ORDER BY round, slot', [$t['id']]);
        $rounds = $ms ? max(array_map(fn($m) => (int)$m['round'], $ms)) : 0;
        $out = ['tour' => ['id' => (int)$t['id'], 'name' => $t['name'], 'kind' => $t['kind'], 'doubles' => (bool)$t['doubles'], 'groups' => (int)$t['grp_count'],
                'format_label' => tn_format_label(tn_format($t['format'])), 'rounds' => $rounds,
                'can_edit' => $u && ($u['tn_admin'] || (int)$t['owner_id'] === (int)$u['id'])],
            'matches' => array_map(function ($m) use ($u, $t, $rounds) {
                $o = tn_match_out($m, $u, false) + ['slot' => (int)$m['slot']];
                if ($t['kind'] === 'ko') $o['round_name'] = tn_round_name((int)$m['round'], $rounds);
                return $o;
            }, $ms),
            'table' => $t['kind'] === 'rr' ? tn_standings((int)$t['id']) : null];
        break;
    case 'tour_new':
        tn_require_admin();
        $out = ['id' => tn_tour_create($in, $u)];
        break;
    case 'tour_delete':
        tn_require();
        $t = row('SELECT * FROM tn_tours WHERE id=?', [int_in($in, 'id')]);
        if (!$t) fail('That tournament was not found', 404);
        if (!$u['tn_admin'] && (int)$t['owner_id'] !== (int)$u['id']) fail('Only a club admin can delete a tournament', 403);
        q('DELETE FROM tn_tours WHERE id=?', [$t['id']]);
        $out = ['ok' => true];
        break;

    /* ----- players ----- */
    case 'players':
        $out = ['players' => rows("SELECT p.id, p.name, p.gender, p.team_id,
            (SELECT COUNT(*) FROM tn_matches m WHERE m.status='done' AND p.id IN (m.a1, m.a2, m.b1, m.b2)) played,
            (SELECT COUNT(*) FROM tn_matches m WHERE m.status='done' AND ((m.winner=0 AND p.id IN (m.a1, m.a2)) OR (m.winner=1 AND p.id IN (m.b1, m.b2)))) won
            FROM tn_players p ORDER BY won DESC, played, p.name")];
        break;
    case 'player':
        $p = row('SELECT id, name, gender, team_id FROM tn_players WHERE id=?', [(int)($_GET['id'] ?? 0)]);
        if (!$p) fail('That player was not found', 404);
        $ms = rows("$list WHERE ? IN (m.a1, m.a2, m.b1, m.b2) AND m.status IN ('done','live') ORDER BY COALESCE(m.finished_at, m.updated_at) DESC LIMIT 60", [$p['id']]);
        $rec = ['played' => 0, 'won' => 0, 'singles' => [0, 0], 'doubles' => [0, 0], 'aces' => 0, 'df' => 0];
        foreach ($ms as $m) {
            if ($m['status'] !== 'done') continue;
            $side = in_array((int)$p['id'], [(int)$m['a1'], (int)$m['a2']], true) ? 0 : 1;
            $w = (int)$m['winner'] === $side;
            $rec['played']++; $rec['won'] += $w ? 1 : 0;
            $rec[$m['doubles'] ? 'doubles' : 'singles'][$w ? 0 : 1]++;
            if (!$m['doubles']) { $st = json_decode((string)$m['state'], true)['stats'][$side] ?? []; $rec['aces'] += (int)($st['aces'] ?? 0); $rec['df'] += (int)($st['df'] ?? 0); }
        }
        $out = ['player' => $p + ['can_edit' => (bool)($u['tn_admin'] ?? false)], 'record' => $rec, 'matches' => $mini($ms)];
        break;
    case 'player_save':
        tn_require();
        $id = int_in($in, 'id'); $name = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? ''))), 0, 80);
        $g = in_array($in['gender'] ?? '', ['M', 'F'], true) ? $in['gender'] : null;
        if ($name === '') fail('Enter the player\'s name');
        if (val('SELECT 1 FROM tn_players WHERE name=? AND id<>?', [$name, $id])) fail('There is already a player with that name');
        if ($id) {
            tn_require_admin();
            q('UPDATE tn_players SET name=?, gender=? WHERE id=?', [$name, $g, $id]);
            foreach (['a', 'b'] as $s) foreach (rows("SELECT id, {$s}1 p1, {$s}2 p2 FROM tn_matches WHERE ? IN ({$s}1, {$s}2)", [$id]) as $m) q("UPDATE tn_matches SET {$s}_name=? WHERE id=?", [tn_side_name((int)$m['p1'], $m['p2'] ? (int)$m['p2'] : null), $m['id']]);
            foreach (rows('SELECT id, p1, p2 FROM tn_entries WHERE ? IN (p1, p2)', [$id]) as $e) q('UPDATE tn_entries SET name=? WHERE id=?', [tn_side_name((int)$e['p1'], $e['p2'] ? (int)$e['p2'] : null), $e['id']]);
        } else { q('INSERT INTO tn_players (name, gender, created_by) VALUES (?,?,?)', [$name, $g, $u['id']]); $id = lastId(); }
        if (array_key_exists('team_id', $in)) tn_assign_team([$id], int_in($in, 'team_id') ?: null);
        $out = ['id' => $id];
        break;
    case 'player_delete':
        tn_require_admin();
        $id = int_in($in, 'id');
        if (val('SELECT 1 FROM tn_matches WHERE ? IN (a1, a2, b1, b2) LIMIT 1', [$id]) || val('SELECT 1 FROM tn_entries WHERE ? IN (p1, p2) LIMIT 1', [$id])) fail('This player has matches, so they cannot be deleted. Rename them instead.');
        q('DELETE FROM tn_players WHERE id=?', [$id]);
        $out = ['ok' => true];
        break;

    /* ----- teams ----- */
    case 'teams':
    case 'team':
        $teams = [];
        foreach (tn_teams() as $t) $teams[$t['id']] = $t + ['players' => 0, 'played' => 0, 'won' => 0];
        foreach (tn_team_map() as $tid) if (isset($teams[$tid])) $teams[$tid]['players']++;
        $done = rows("$list WHERE m.status='done' ORDER BY m.finished_at DESC");
        foreach ($done as $m) foreach ([0, 1] as $side) {
            $tid = $side ? tn_side_team($m['b1'], $m['b2']) : tn_side_team($m['a1'], $m['a2']);
            if ($tid === null || !isset($teams[$tid])) continue;
            $teams[$tid]['played']++; if ((int)$m['winner'] === $side) $teams[$tid]['won']++;
        }
        if ($a === 'teams') { $t = array_values($teams); usort($t, fn($x, $y) => [$y['won'], $x['played'], $x['name']] <=> [$x['won'], $y['played'], $y['name']]); $out = ['teams' => $t]; break; }
        $tid = (int)($_GET['id'] ?? 0);
        if (!isset($teams[$tid])) fail('That team was not found', 404);
        $mine = fn($m) => in_array($tid, [tn_side_team($m['a1'], $m['a2']), tn_side_team($m['b1'], $m['b2'])], true);
        $ms = array_merge(rows("$list WHERE m.status='live' ORDER BY m.updated_at DESC"), $done);
        $out = ['team' => $teams[$tid] + ['can_edit' => (bool)($u['tn_admin'] ?? false)],
            'players' => rows("SELECT p.id, p.name,
                (SELECT COUNT(*) FROM tn_matches m WHERE m.status='done' AND p.id IN (m.a1, m.a2, m.b1, m.b2)) played,
                (SELECT COUNT(*) FROM tn_matches m WHERE m.status='done' AND ((m.winner=0 AND p.id IN (m.a1, m.a2)) OR (m.winner=1 AND p.id IN (m.b1, m.b2)))) won
                FROM tn_players p WHERE p.team_id=? ORDER BY won DESC, p.name", [$tid]),
            'matches' => $mini(array_slice(array_values(array_filter($ms, $mine)), 0, 40))];
        break;
    case 'team_players':   // admin: add a list of names to a team (new players are created), or take one out
        tn_require_admin();
        $tid = int_in($in, 'id');
        if (int_in($in, 'remove')) { tn_assign_team([int_in($in, 'remove')], null); $out = ['ok' => true]; break; }
        $names = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)($in['names'] ?? ''))), 'strlen'));
        if (!$names) fail('Type at least one name');
        if (count($names) > 100) fail('Add up to 100 players at a time');
        tn_assign_team(array_map(fn($n) => tn_player($n, (int)$u['id']), $names), $tid);
        $out = ['added' => count($names)];
        break;

    /* ----- club admins ----- */
    case 'admins':
        tn_require_admin();
        $out = ['admins' => rows('SELECT u.id, u.display_name name, u.username FROM tn_admins a JOIN users u ON u.id=a.user_id ORDER BY u.display_name')];
        break;
    case 'admin_add':
        tn_require_admin();
        $s = str_in($in, 'who', 190);
        $id = val('SELECT id FROM users WHERE username=?', [ltrim($s, '@')]) ?: val('SELECT id FROM users WHERE email=? AND google_sub IS NOT NULL', [strtolower($s)])
            ?: (($mb = tn_mobile($s)) ? val('SELECT id FROM users WHERE mobile=?', [$mb]) : null);
        if (!$id) fail('No member found with that username, Google e-mail or mobile number. They need to sign in to RV Tennis once first.');
        q('INSERT IGNORE INTO tn_admins (user_id, added_by) VALUES (?,?)', [$id, $u['id']]);
        $out = ['ok' => true];
        break;
    case 'admin_remove':
        tn_require_admin();
        q('DELETE FROM tn_admins WHERE user_id=?', [int_in($in, 'id')]);
        $out = ['ok' => true];
        break;

    default: fail('Unknown request', 404);
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (ApiError $e) {
    http_response_code($e->status);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('RV Tennis: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    $msg = $e instanceof PDOException && !val_safe() ? 'The database is not reachable. Check the settings in config.local.php.' : 'Something went wrong. Please try again.';
    // A club admin sees the technical reason too, so a problem on the server can be reported and fixed quickly
    $detail = $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    try { $adm = !empty(tn_user()['tn_admin']); } catch (Throwable $x) { $adm = false; }
    echo json_encode(['error' => DEBUG || $adm ? "$msg ($detail)" : $msg]);
}
function val_safe(): bool { try { db()->query('SELECT 1'); return true; } catch (Throwable $e) { return false; } }

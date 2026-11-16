<?php
/* RV Badminton - JSON API. Reads: GET api.php?a=...   Changes: POST api.php?a=... with a JSON body. */
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
    bd_schema();
    $u = bd_user();
    $mget = function (int $id) { $m = row('SELECT * FROM bd_matches WHERE id=?', [$id]); if (!$m) fail('That match was not found', 404); return $m; };
    $mscore = function (int $id) use ($mget, $u) {
        bd_require();
        $m = $mget($id);
        if (!bd_can_score($m, $u)) fail('Only the scorer of this match can change it', 403);
        return $m;
    };
    $list = 'SELECT m.*, t.name tour_name FROM bd_matches m LEFT JOIN bd_tours t ON t.id=m.tour_id';
    $mini = fn(array $ms) => array_map(fn($m) => bd_match_out($m, $u, false), $ms);

    switch ($a) {
    case 'boot':
        $out = ['user' => bd_public_user($u), 'version' => BD_VERSION, 'club' => BD_CLUB, 'name' => BD_NAME, 'main_site' => BD_MAIN_SITE,
            'google_client_id' => bd_google_client() ?: null, 'whatsapp' => bd_wa_enabled() || DEBUG,
            'presets' => array_map(fn($p) => $p['label'], BD_PRESETS), 'teams' => bd_teams(), 'logo' => is_file(__DIR__ . '/assets/logo.png') ? 'assets/logo.png?v=' . filemtime(__DIR__ . '/assets/logo.png') : null];
        break;

    /* ----- sign-in ----- */
    case 'login':      $out = bd_password_login($in); break;
    case 'google':     $out = bd_google($in); break;
    case 'wa_request': $out = bd_wa_request($in); break;
    case 'wa_verify':  $out = bd_wa_verify($in); break;
    case 'logout':     bd_logout(); $out = ['ok' => true]; break;

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
        $out = ['match' => bd_match_out($m, $u)];
        break;
    case 'match_new':
        bd_require();
        $doubles = !empty($in['doubles']);
        [$a1, $a2] = bd_side(str_in($in, 'a', 170), $doubles, (int)$u['id']);
        [$b1, $b2] = bd_side(str_in($in, 'b', 170), $doubles, (int)$u['id']);
        if (count(array_unique(array_filter([$a1, $a2, $b1, $b2]))) !== ($doubles ? 4 : 2)) fail('The same player is on both sides');
        $when = str_in($in, 'sched_at', 20);
        $when = $when !== '' && strtotime($when) ? date('Y-m-d H:i:s', strtotime($when)) : null;
        q('INSERT INTO bd_matches (owner_id, doubles, a1, a2, b1, b2, a_name, b_name, format, first_srv, court, sched_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
          [$u['id'], $doubles ? 1 : 0, $a1, $a2, $b1, $b2, bd_side_name($a1, $a2), bd_side_name($b1, $b2), json_encode(bd_format($in['format'] ?? 'bo1')),
           !empty($in['first_srv']) ? 1 : 0, str_in($in, 'court', 40) ?: null, $when]);
        $out = ['id' => lastId()];
        // a team picked on the form goes to the players of that side who are not in a team yet
        if (int_in($in, 'team_a')) bd_assign_team([$a1, $a2], int_in($in, 'team_a'), true);
        if (int_in($in, 'team_b')) bd_assign_team([$b1, $b2], int_in($in, 'team_b'), true);
        break;
    case 'point':
        $m = $mscore(int_in($in, 'id'));
        if ($m['a_name'] === '' || $m['b_name'] === '') fail('This match is still waiting for a player from the previous round');
        $ev = json_decode((string)$m['events'], true) ?: [];
        // "n" is how many events the phone had seen: a double tap or a second scorer cannot add a point twice
        if (int_in($in, 'n', -1) === count($ev) && $m['status'] !== 'done') {
            $k = in_array($in['k'] ?? '', ['win', 'ue', 'fl', 'sf'], true) ? $in['k'] : '';
            $ev[] = ['w' => !empty($in['w']) ? 1 : 0] + ($k ? ['k' => $k] : []);
            $m = bd_store($m, $ev);
        }
        $out = ['match' => bd_match_out($m, $u)];
        break;
    case 'ev':   // everything a scorer records that is not a rally: a let, doubles positions at the start of a game
        $m = $mscore(int_in($in, 'id'));
        if ($m['a_name'] === '' || $m['b_name'] === '') fail('This match is still waiting for a player from the previous round');
        $ev = json_decode((string)$m['events'], true) ?: [];
        $t = (string)($in['t'] ?? '');
        if (!in_array($t, ['let', 'ord'], true)) fail('Bad request');
        if (int_in($in, 'n', -1) === count($ev) && $m['status'] !== 'done') {
            $e = ['t' => $t];
            if ($t === 'ord') {
                if (!$m['doubles']) fail('Court positions are for doubles');
                if (!bd_replay(bd_format($m['format']), true, (int)$m['first_srv'], $ev)->atGameStart()) fail('Positions can only change before the first rally of a game');
                if (!is_array($in['r'] ?? null)) fail('Bad request');
                $e['r'] = [!empty($in['r'][0]) ? 1 : 0, !empty($in['r'][1]) ? 1 : 0];
                while ($ev && (end($ev)['t'] ?? '') === 'ord') array_pop($ev);   // one choice per game start: the new one replaces the old
            }
            $ev[] = $e;
            $m = bd_store($m, $ev);
        }
        $out = ['match' => bd_match_out($m, $u)];
        break;
    case 'tag':   // optional: say how the last rally ended (winner, unforced error, fault)
        $m = $mscore(int_in($in, 'id'));
        $ev = json_decode((string)$m['events'], true) ?: [];
        $i = count($ev) - 1;
        if (int_in($in, 'n', -1) === count($ev) && $i >= 0 && isset($ev[$i]['w']) && !isset($ev[$i]['t']) && ($ev[$i]['k'] ?? '') !== 'sf') {
            $k = in_array($in['k'] ?? '', ['win', 'ue', 'fl'], true) ? $in['k'] : '';
            if ($k === '' || ($ev[$i]['k'] ?? '') === $k) unset($ev[$i]['k']); else $ev[$i]['k'] = $k;   // tapping the same tag again clears it
            $m = bd_store($m, $ev);
        }
        $out = ['match' => bd_match_out($m, $u)];
        break;
    case 'undo':
        $m = $mscore(int_in($in, 'id'));
        $ev = json_decode((string)$m['events'], true) ?: [];
        $manual = !empty(json_decode((string)$m['state'], true)['manual']);
        if (!$manual && (int_in($in, 'n', -1) !== count($ev) || !$ev)) { $out = ['match' => bd_match_out($m, $u)]; break; }
        if ($manual) $ev = []; else array_pop($ev);
        if (!array_filter($ev, fn($e) => ($e['t'] ?? '') !== 'ord')) {   // nothing played is left: back to "not started"
            if ($m['status'] === 'done') bd_ko_retract($m);
            q("UPDATE bd_matches SET status='scheduled', started_at=NULL, finished_at=NULL, winner=NULL WHERE id=?", [$m['id']]);
            $m = $mget((int)$m['id']);
        }
        $m = bd_store($m, $ev);
        $out = ['match' => bd_match_out($m, $u)];
        break;
    case 'match_end':   // retirement, walkover or disqualification: "w" is the side that wins
        $m = $mscore(int_in($in, 'id'));
        if ($m['status'] === 'done') fail('This match is already finished');
        if ($m['a_name'] === '' || $m['b_name'] === '') fail('This match is still waiting for a player from the previous round');
        $ev = json_decode((string)$m['events'], true) ?: [];
        $ev[] = ['t' => in_array($in['why'] ?? '', ['wo', 'def'], true) ? $in['why'] : 'ret', 'w' => !empty($in['w']) ? 1 : 0];
        $out = ['match' => bd_match_out(bd_store($m, $ev), $u)];
        break;
    case 'match_result':   // type in a finished score instead of scoring rally by rally
        $m = $mscore(int_in($in, 'id'));
        if ($m['a_name'] === '' || $m['b_name'] === '') fail('This match is still waiting for a player from the previous round');
        if (json_decode((string)$m['events'], true) && empty($in['replace'])) fail('This match has rallies scored already. Undo them first, or confirm replacing them.');
        try { $st = bd_manual_state(bd_format($m['format']), (bool)$m['doubles'], is_array($in['games'] ?? null) ? $in['games'] : []); }
        catch (InvalidArgumentException $e) { fail($e->getMessage()); }
        $out = ['match' => bd_match_out(bd_store($m, [], $st), $u)];
        break;
    case 'match_edit':
        $m = $mscore(int_in($in, 'id'));
        $set = []; $p = [];
        if (array_key_exists('court', $in)) { $set[] = 'court=?'; $p[] = str_in($in, 'court', 40) ?: null; }
        if (array_key_exists('sched_at', $in)) { $w = str_in($in, 'sched_at', 20); $set[] = 'sched_at=?'; $p[] = $w !== '' && strtotime($w) ? date('Y-m-d H:i:s', strtotime($w)) : null; }
        if (array_key_exists('scorer', $in)) {   // hand the scoring to another member, found by username or mobile number
            $s = str_in($in, 'scorer', 60); $sid = null;
            if ($s !== '') {
                $sid = val('SELECT id FROM users WHERE username=?', [ltrim($s, '@')]) ?: (($mb = bd_mobile($s)) ? val('SELECT id FROM users WHERE mobile=?', [$mb]) : null);
                if (!$sid) fail('No member found with that username or mobile number. They need to sign in to RV Badminton once first.');
            }
            $set[] = 'scorer_id=?'; $p[] = $sid;
        }
        if ($m['status'] === 'scheduled') {
            if (array_key_exists('first_srv', $in)) { $set[] = 'first_srv=?'; $p[] = !empty($in['first_srv']) ? 1 : 0; }
            if (array_key_exists('format', $in)) { $set[] = 'format=?'; $p[] = json_encode(bd_format($in['format'])); }
            if (!empty($in['swap_a']) && $m['a2']) { $set[] = 'a1=?, a2=?'; array_push($p, $m['a2'], $m['a1']); }
            if (!empty($in['swap_b']) && $m['b2']) { $set[] = 'b1=?, b2=?'; array_push($p, $m['b2'], $m['b1']); }
            $set[] = 'state=NULL';
        }
        if ($set) { $p[] = $m['id']; q('UPDATE bd_matches SET ' . implode(', ', $set) . ' WHERE id=?', $p); }
        $out = ['match' => bd_match_out($mget((int)$m['id']), $u)];
        break;
    case 'match_delete':
        $m = $mscore(int_in($in, 'id'));
        if ($m['tour_id']) fail('This match belongs to a tournament. Delete the tournament, or undo the score instead.');
        q('DELETE FROM bd_matches WHERE id=?', [$m['id']]);
        $out = ['ok' => true];
        break;

    /* ----- tournaments ----- */
    case 'tours':
        $out = ['tours' => rows("SELECT t.id, t.name, t.kind, t.doubles, t.created_at, (SELECT COUNT(*) FROM bd_entries e WHERE e.tour_id=t.id) entries,
            (SELECT COUNT(*) FROM bd_matches m WHERE m.tour_id=t.id AND m.status='done') done,
            (SELECT COUNT(*) FROM bd_matches m WHERE m.tour_id=t.id AND m.status<>'bye') total FROM bd_tours t ORDER BY t.id DESC")];
        break;
    case 'tour':
        $t = row('SELECT * FROM bd_tours WHERE id=?', [(int)($_GET['id'] ?? 0)]);
        if (!$t) fail('That tournament was not found', 404);
        $ms = rows('SELECT * FROM bd_matches WHERE tour_id=? ORDER BY stage, round, slot', [$t['id']]);
        $ko = array_filter($ms, fn($m) => (int)$m['stage'] === 1);
        $rounds = $ko ? max(array_map(fn($m) => (int)$m['round'], $ko)) : 0;
        $o = bd_opts($t);
        $out = ['tour' => ['id' => (int)$t['id'], 'name' => $t['name'], 'kind' => $t['kind'], 'doubles' => (bool)$t['doubles'], 'groups' => (int)$t['grp_count'],
                'format_label' => bd_format_label(bd_format($t['format'])), 'ko_label' => $t['ko_format'] ? bd_format_label(bd_format($t['ko_format'])) : null, 'rounds' => $rounds] + $o + [
                'league_left' => count(array_filter($ms, fn($m) => (int)$m['stage'] === 0 && in_array($m['status'], ['scheduled', 'live'], true))),
                'ko_started' => (bool)array_filter($ko, fn($m) => in_array($m['status'], ['live', 'done'], true)),
                'can_edit' => $u && ($u['bd_admin'] || (int)$t['owner_id'] === (int)$u['id'])],
            'matches' => array_map(function ($m) use ($u, $rounds) {
                $o = bd_match_out($m, $u, false) + ['slot' => (int)$m['slot']];
                if ((int)$m['stage'] === 1) $o['round_name'] = bd_round_name((int)$m['round'], $rounds);
                return $o;
            }, $ms),
            'table' => $t['kind'] !== 'ko' ? bd_standings((int)$t['id']) : null];
        break;
    case 'tour_new':
        bd_require_admin();
        $out = ['id' => bd_tour_create($in, $u)];
        break;
    case 'tour_playoffs':   // league + play-offs: draw (or re-draw) the play-offs from the tables as they stand
        bd_require();
        $t = row('SELECT * FROM bd_tours WHERE id=?', [int_in($in, 'id')]);
        if (!$t) fail('That tournament was not found', 404);
        if (!$u['bd_admin'] && (int)$t['owner_id'] !== (int)$u['id']) fail('Only a club admin can draw the play-offs', 403);
        bd_playoffs((int)$t['id'], $u);
        $out = ['ok' => true];
        break;
    case 'tour_delete':
        bd_require();
        $t = row('SELECT * FROM bd_tours WHERE id=?', [int_in($in, 'id')]);
        if (!$t) fail('That tournament was not found', 404);
        if (!$u['bd_admin'] && (int)$t['owner_id'] !== (int)$u['id']) fail('Only a club admin can delete a tournament', 403);
        q('DELETE FROM bd_tours WHERE id=?', [$t['id']]);
        $out = ['ok' => true];
        break;

    /* ----- players ----- */
    case 'players':
        $out = ['players' => rows("SELECT p.id, p.name, p.gender, p.team_id,
            (SELECT COUNT(*) FROM bd_matches m WHERE m.status='done' AND p.id IN (m.a1, m.a2, m.b1, m.b2)) played,
            (SELECT COUNT(*) FROM bd_matches m WHERE m.status='done' AND ((m.winner=0 AND p.id IN (m.a1, m.a2)) OR (m.winner=1 AND p.id IN (m.b1, m.b2)))) won
            FROM bd_players p ORDER BY won DESC, played, p.name")];
        break;
    case 'player':
        $p = row('SELECT id, name, gender, team_id FROM bd_players WHERE id=?', [(int)($_GET['id'] ?? 0)]);
        if (!$p) fail('That player was not found', 404);
        $ms = rows("$list WHERE ? IN (m.a1, m.a2, m.b1, m.b2) AND m.status IN ('done','live') ORDER BY COALESCE(m.finished_at, m.updated_at) DESC LIMIT 60", [$p['id']]);
        $rec = ['played' => 0, 'won' => 0, 'singles' => [0, 0], 'doubles' => [0, 0], 'games' => [0, 0], 'pts' => [0, 0]];
        foreach ($ms as $m) {
            if ($m['status'] !== 'done') continue;
            $side = in_array((int)$p['id'], [(int)$m['a1'], (int)$m['a2']], true) ? 0 : 1;
            $w = (int)$m['winner'] === $side;
            $rec['played']++; $rec['won'] += $w ? 1 : 0;
            $rec[$m['doubles'] ? 'doubles' : 'singles'][$w ? 0 : 1]++;
            foreach (json_decode((string)$m['state'], true)['games'] ?? [] as $g) {
                $mine = $side ? $g['b'] : $g['a']; $theirs = $side ? $g['a'] : $g['b'];
                $rec['games'][$mine > $theirs ? 0 : 1]++; $rec['pts'][0] += $mine; $rec['pts'][1] += $theirs;
            }
        }
        $out = ['player' => $p + ['can_edit' => (bool)($u['bd_admin'] ?? false)], 'record' => $rec, 'matches' => $mini($ms)];
        break;
    case 'player_save':
        bd_require();
        $id = int_in($in, 'id'); $name = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? ''))), 0, 80);
        $g = in_array($in['gender'] ?? '', ['M', 'F'], true) ? $in['gender'] : null;
        if ($name === '') fail('Enter the player\'s name');
        if (val('SELECT 1 FROM bd_players WHERE name=? AND id<>?', [$name, $id])) fail('There is already a player with that name');
        if ($id) {
            bd_require_admin();
            q('UPDATE bd_players SET name=?, gender=? WHERE id=?', [$name, $g, $id]);
            foreach (['a', 'b'] as $s) foreach (rows("SELECT id, {$s}1 p1, {$s}2 p2 FROM bd_matches WHERE ? IN ({$s}1, {$s}2)", [$id]) as $m) q("UPDATE bd_matches SET {$s}_name=? WHERE id=?", [bd_side_name((int)$m['p1'], $m['p2'] ? (int)$m['p2'] : null), $m['id']]);
            foreach (rows('SELECT id, p1, p2 FROM bd_entries WHERE ? IN (p1, p2)', [$id]) as $e) q('UPDATE bd_entries SET name=? WHERE id=?', [bd_side_name((int)$e['p1'], $e['p2'] ? (int)$e['p2'] : null), $e['id']]);
        } else { q('INSERT INTO bd_players (name, gender, created_by) VALUES (?,?,?)', [$name, $g, $u['id']]); $id = lastId(); }
        if (array_key_exists('team_id', $in)) bd_assign_team([$id], int_in($in, 'team_id') ?: null);
        $out = ['id' => $id];
        break;
    case 'player_delete':
        bd_require_admin();
        $id = int_in($in, 'id');
        if (val('SELECT 1 FROM bd_matches WHERE ? IN (a1, a2, b1, b2) LIMIT 1', [$id]) || val('SELECT 1 FROM bd_entries WHERE ? IN (p1, p2) LIMIT 1', [$id])) fail('This player has matches, so they cannot be deleted. Rename them instead.');
        q('DELETE FROM bd_players WHERE id=?', [$id]);
        $out = ['ok' => true];
        break;

    /* ----- teams ----- */
    case 'teams':
    case 'team':
        $teams = [];
        foreach (bd_teams() as $t) $teams[$t['id']] = $t + ['players' => 0, 'played' => 0, 'won' => 0];
        foreach (bd_team_map() as $tid) if (isset($teams[$tid])) $teams[$tid]['players']++;
        $done = rows("$list WHERE m.status='done' ORDER BY m.finished_at DESC");
        foreach ($done as $m) foreach ([0, 1] as $side) {
            $tid = $side ? bd_side_team($m['b1'], $m['b2']) : bd_side_team($m['a1'], $m['a2']);
            if ($tid === null || !isset($teams[$tid])) continue;
            $teams[$tid]['played']++; if ((int)$m['winner'] === $side) $teams[$tid]['won']++;
        }
        if ($a === 'teams') { $t = array_values($teams); usort($t, fn($x, $y) => [$y['won'], $x['played'], $x['name']] <=> [$x['won'], $y['played'], $y['name']]); $out = ['teams' => $t]; break; }
        $tid = (int)($_GET['id'] ?? 0);
        if (!isset($teams[$tid])) fail('That team was not found', 404);
        $mine = fn($m) => in_array($tid, [bd_side_team($m['a1'], $m['a2']), bd_side_team($m['b1'], $m['b2'])], true);
        $ms = array_merge(rows("$list WHERE m.status='live' ORDER BY m.updated_at DESC"), $done);
        $out = ['team' => $teams[$tid] + ['can_edit' => (bool)($u['bd_admin'] ?? false)],
            'players' => rows("SELECT p.id, p.name,
                (SELECT COUNT(*) FROM bd_matches m WHERE m.status='done' AND p.id IN (m.a1, m.a2, m.b1, m.b2)) played,
                (SELECT COUNT(*) FROM bd_matches m WHERE m.status='done' AND ((m.winner=0 AND p.id IN (m.a1, m.a2)) OR (m.winner=1 AND p.id IN (m.b1, m.b2)))) won
                FROM bd_players p WHERE p.team_id=? ORDER BY won DESC, p.name", [$tid]),
            'matches' => $mini(array_slice(array_values(array_filter($ms, $mine)), 0, 40))];
        break;
    case 'team_players':   // admin: add a list of names to a team (new players are created), or take one out
        bd_require_admin();
        $tid = int_in($in, 'id');
        if (int_in($in, 'remove')) { bd_assign_team([int_in($in, 'remove')], null); $out = ['ok' => true]; break; }
        $names = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)($in['names'] ?? ''))), 'strlen'));
        if (!$names) fail('Type at least one name');
        if (count($names) > 100) fail('Add up to 100 players at a time');
        bd_assign_team(array_map(fn($n) => bd_player($n, (int)$u['id']), $names), $tid);
        $out = ['added' => count($names)];
        break;

    /* ----- club admins ----- */
    case 'admins':
        bd_require_admin();
        $out = ['admins' => rows('SELECT u.id, u.display_name name, u.username FROM bd_admins a JOIN users u ON u.id=a.user_id ORDER BY u.display_name')];
        break;
    case 'admin_add':
        bd_require_admin();
        $s = str_in($in, 'who', 190);
        $id = val('SELECT id FROM users WHERE username=?', [ltrim($s, '@')]) ?: val('SELECT id FROM users WHERE email=? AND google_sub IS NOT NULL', [strtolower($s)])
            ?: (($mb = bd_mobile($s)) ? val('SELECT id FROM users WHERE mobile=?', [$mb]) : null);
        if (!$id) fail('No member found with that username, Google e-mail or mobile number. They need to sign in to RV Badminton once first.');
        q('INSERT IGNORE INTO bd_admins (user_id, added_by) VALUES (?,?)', [$id, $u['id']]);
        $out = ['ok' => true];
        break;
    case 'admin_remove':
        bd_require_admin();
        q('DELETE FROM bd_admins WHERE user_id=?', [int_in($in, 'id')]);
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
    error_log('RV Badminton: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    $msg = $e instanceof PDOException && !val_safe() ? 'The database is not reachable. Check the settings in config.local.php.' : 'Something went wrong. Please try again.';
    // A club admin sees the technical reason too, so a problem on the server can be reported and fixed quickly
    $detail = $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    try { $adm = !empty(bd_user()['bd_admin']); } catch (Throwable $x) { $adm = false; }
    echo json_encode(['error' => DEBUG || $adm ? "$msg ($detail)" : $msg]);
}
function val_safe(): bool { try { db()->query('SELECT 1'); return true; } catch (Throwable $e) { return false; } }

<?php
/* Cric-Scorer - cricket training academies: register an academy, add students (existing players by username or
   mobile, or new names), and give the academy its own team so students can play matches together. */

function academy_view(array $a, ?array $u): array {
    $a['id'] = (int)$a['id'];
    $a['can_edit'] = $u && (!empty($u['is_admin']) || (int)$a['owner_id'] === (int)$u['id']);
    $a['students'] = (int)val('SELECT COUNT(*) FROM academy_students WHERE academy_id=?', [$a['id']]);
    $a['team_id'] = $a['team_id'] ? (int)$a['team_id'] : null;
    $a['owner_id'] = (int)$a['owner_id'];
    return $a;
}
function academies_action(string $action, array $u, array $in) {
    switch ($action) {
    case 'academies_list': {
        $qs = trim(mb_substr((string)($in['q'] ?? ''), 0, 60));
        $where = $qs !== '' ? 'WHERE a.name LIKE ? OR a.city LIKE ?' : '';
        $p = $qs !== '' ? ["%$qs%", "%$qs%"] : [];
        $list = rows("SELECT a.*, u.display_name owner_name FROM academies a LEFT JOIN users u ON u.id=a.owner_id $where ORDER BY a.name LIMIT 100", $p);
        $mine = rows('SELECT a.id, a.name FROM academy_students s JOIN academies a ON a.id=s.academy_id WHERE s.user_id=?', [$u['id']]);
        return ['academies' => array_map(fn($a) => academy_view($a, $u), $list), 'mine' => $mine];
    }
    case 'academy_get': {
        $a = row('SELECT a.*, u.display_name owner_name, u.username owner_username FROM academies a LEFT JOIN users u ON u.id=a.owner_id WHERE a.id=?', [(int)($in['id'] ?? 0)]);
        if (!$a) fail('Academy not found', 404);
        $a = academy_view($a, $u);
        $students = rows('SELECT s.id, s.name, s.user_id, s.player_id, s.created_at, us.display_name, us.username, us.avatar_url, us.avatar_preset, us.is_private, us.mobile_verified_at
                          FROM academy_students s LEFT JOIN users us ON us.id=s.user_id WHERE s.academy_id=? ORDER BY COALESCE(us.display_name, s.name)', [$a['id']]);
        foreach ($students as &$s) {
            $s['id'] = (int)$s['id']; $s['user_id'] = $s['user_id'] ? (int)$s['user_id'] : null; $s['player_id'] = $s['player_id'] ? (int)$s['player_id'] : null;
            $s['name'] = $s['display_name'] ?: $s['name']; $s['avatar'] = $s['avatar_url']; $s['verified'] = !empty($s['mobile_verified_at']);
            if (!empty($s['is_private']) && !$a['can_edit'] && (int)$s['user_id'] !== (int)$u['id']) { $s['user_id'] = null; $s['username'] = null; }   // private players: name only
            unset($s['avatar_url'], $s['mobile_verified_at'], $s['is_private']);
        }
        $team = $a['team_id'] ? team_brief($a['team_id']) : null;
        [$vs, $vp] = vis_sql($u);
        $recent = $a['team_id'] ? match_summaries(rows("SELECT * FROM matches WHERE (team1_id=? OR team2_id=?) AND $vs ORDER BY updated_at DESC LIMIT 5", array_merge([$a['team_id'], $a['team_id']], $vp))) : [];
        $cfg = league_of_academy($a['id']);
        $league = $cfg && ($a['can_edit'] || is_club_member($a['id'], $u)) ? ['name' => $cfg['club_name'], 'categories' => $cfg['categories'], 'groups' => $cfg['groups'], 'squad_size' => $cfg['squad_size'], 'playing' => $cfg['playing'], 'points' => $cfg['points'],
            'teams' => rows('SELECT id, name, short_name, color, league_group, league_cat, (SELECT COUNT(*) FROM players p WHERE p.team_id=teams.id) players FROM teams WHERE academy_id=? ORDER BY league_group, league_cat, name', [$a['id']]),
            'tournaments' => rows('SELECT id, name FROM tournaments WHERE academy_id=? ORDER BY created_at DESC', [$a['id']])] : null;
        return ['academy' => $a, 'students' => $students, 'team' => $team, 'recent' => $recent, 'is_student' => (bool)val('SELECT 1 FROM academy_students WHERE academy_id=? AND user_id=?', [$a['id'], $u['id']]), 'league' => $league, 'has_rules' => (bool)$a['league_json']];
    }
    case 'academy_save': {
        $id = (int)($in['id'] ?? 0);
        $name = trim(mb_substr((string)($in['name'] ?? ''), 0, 120));
        if (mb_strlen($name) < 3) fail('Enter the academy name');
        $vals = [$name, trim(mb_substr((string)($in['address'] ?? ''), 0, 250)) ?: null, trim(mb_substr((string)($in['city'] ?? ''), 0, 80)) ?: null,
                 normalize_mobile((string)($in['phone'] ?? '')) ?: null, trim(mb_substr((string)($in['coach'] ?? ''), 0, 100)) ?: null, trim(mb_substr((string)($in['about'] ?? ''), 0, 600)) ?: null];
        if ($id) {
            $a = row('SELECT * FROM academies WHERE id=?', [$id]);
            if (!$a || !academy_view($a, $u)['can_edit']) fail('Only the academy owner can edit it', 403);
            q('UPDATE academies SET name=?, address=?, city=?, phone=?, coach=?, about=? WHERE id=?', array_merge($vals, [$id]));
            if ($a['team_id']) q('UPDATE teams SET name=? WHERE id=? AND name<>?', [$name, $a['team_id'], $name]);
        } else {
            if (!$u['is_admin'] && (int)val('SELECT COUNT(*) FROM academies WHERE owner_id=?', [$u['id']]) >= 3) fail('You can register up to 3 academies');
            // same name in the same city? (compared in PHP: wrapping a bound value in COALESCE() trips MariaDB 11's collation rules)
            foreach (rows('SELECT city FROM academies WHERE name=?', [$name]) as $dup) if (mb_strtolower(trim((string)$dup['city'])) === mb_strtolower(trim((string)($vals[2] ?? '')))) fail('An academy with that name is already registered in this city');
            $short = strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)) ?: 'ACA';
            q("INSERT INTO teams (name, short_name, color, category, owner_id, share_mode) VALUES (?,?,?,'custom',?,'approved')", [$name, $short, '#0ca678', $u['id']]);
            $teamId = lastId();
            q('INSERT INTO academies (name, address, city, phone, coach, about, owner_id, team_id) VALUES (?,?,?,?,?,?,?,?)', array_merge($vals, [$u['id'], $teamId]));
            $id = lastId();
        }
        return ['id' => $id];
    }
    case 'academy_delete': {
        $a = row('SELECT * FROM academies WHERE id=?', [(int)($in['id'] ?? 0)]);
        if (!$a || !academy_view($a, $u)['can_edit']) fail('Only the academy owner can delete it', 403);
        q('DELETE FROM academies WHERE id=?', [$a['id']]);            // students go with it; the team stays (it may have played matches)
        return ['ok' => true];
    }
    case 'academy_student_add': {
        $a = row('SELECT * FROM academies WHERE id=?', [(int)($in['academy_id'] ?? 0)]);
        if (!$a || !academy_view($a, $u)['can_edit']) fail('Only the academy owner can add students', 403);
        if ((int)val('SELECT COUNT(*) FROM academy_students WHERE academy_id=?', [$a['id']]) >= 300) fail('That is a lot of students! The limit is 300 per academy.');
        $who = trim((string)($in['user'] ?? ''));           // a username or mobile number of a player with an account
        $name = trim(mb_substr((string)($in['name'] ?? ''), 0, 100));
        if ($who !== '') {
            $m = normalize_mobile($who);
            $p = row('SELECT * FROM users WHERE username=?' . ($m ? ' OR mobile=?' : ''), $m ? [strtolower($who), $m] : [strtolower($who)]);
            if (!$p) fail('No player with that username or mobile number');
            if (val('SELECT 1 FROM academy_students WHERE academy_id=? AND user_id=?', [$a['id'], $p['id']])) fail($p['display_name'] . ' is already a student here');
            $playerId = $p['player_id'] ?: null;
            if ($a['team_id'] && !val('SELECT 1 FROM players WHERE team_id=? AND name=?', [$a['team_id'], $p['display_name']])) {
                q('INSERT INTO players (team_id, name, role, batting_style, bowling_style, country) VALUES (?,?,?,?,?,?)', [$a['team_id'], $p['display_name'], $p['role'] ?: 'Batter', $p['batting_style'] ?: 'Right-hand bat', $p['bowling_style'] ?: null, 'India']);
                $playerId = lastId();
            }
            q('INSERT INTO academy_students (academy_id, user_id, player_id, name) VALUES (?,?,?,?)', [$a['id'], $p['id'], $playerId, $p['display_name']]);
            if ($a['team_id']) q("INSERT IGNORE INTO team_access (team_id, user_id, status) VALUES (?, ?, 'allowed')", [$a['team_id'], $p['id']]);   // students may use the academy team
        } else {
            if (mb_strlen($name) < 2) fail('Enter the student\'s name, or their username / mobile number');
            $playerId = null;
            if ($a['team_id']) { q('INSERT INTO players (team_id, name, role, batting_style, country) VALUES (?,?,?,?,?)', [$a['team_id'], $name, in_array($in['role'] ?? '', ['Batter', 'Bowler', 'All-rounder', 'Wicket-keeper'], true) ? $in['role'] : 'Batter', 'Right-hand bat', 'India']); $playerId = lastId(); }
            q('INSERT INTO academy_students (academy_id, user_id, player_id, name) VALUES (?,NULL,?,?)', [$a['id'], $playerId, $name]);
        }
        return ['ok' => true];
    }
    case 'academy_student_remove': {
        $s = row('SELECT s.*, a.owner_id, a.team_id FROM academy_students s JOIN academies a ON a.id=s.academy_id WHERE s.id=?', [(int)($in['id'] ?? 0)]);
        if (!$s) fail('Student not found', 404);
        $owner = !empty($u['is_admin']) || (int)$s['owner_id'] === (int)$u['id'];
        if (!$owner && (int)$s['user_id'] !== (int)$u['id']) fail('Only the academy owner (or the student) can do that', 403);
        q('DELETE FROM academy_students WHERE id=?', [$s['id']]);
        if ($s['player_id'] && !val('SELECT 1 FROM match_players WHERE player_id=? LIMIT 1', [$s['player_id']])) q('DELETE FROM players WHERE id=? AND team_id=?', [$s['player_id'], $s['team_id']]);
        if ($s['user_id'] && $s['team_id']) q('DELETE FROM team_access WHERE team_id=? AND user_id=?', [$s['team_id'], $s['user_id']]);
        return ['ok' => true];
    }
    }
    fail('Unknown academy action');
}
/** For a profile: which academies this player belongs to */
function user_academies(int $uid): array {
    return array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'city' => $r['city']], rows('SELECT a.id, a.name, a.city FROM academy_students s JOIN academies a ON a.id=s.academy_id WHERE s.user_id=? ORDER BY a.name', [$uid]));
}

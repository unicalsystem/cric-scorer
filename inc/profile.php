<?php
/* Cric-Scorer - player profile: photo or figure, bio, cricket profile, linked scorer player */

const PROFILE_ROLES = ['Batter', 'Bowler', 'All-rounder', 'Wicket-keeper'];
const PROFILE_BATTING = ['Right-hand bat', 'Left-hand bat'];
const PROFILE_BOWLING = ['Right-arm fast', 'Right-arm fast-medium', 'Right-arm medium', 'Left-arm fast', 'Left-arm fast-medium', 'Left-arm medium',
                         'Right-arm offbreak', 'Right-arm legbreak', 'Slow left-arm orthodox', 'Left-arm wrist-spin'];
const AVATAR_PRESETS = ['bat', 'ball', 'gloves', 'trophy', 'lion', 'tiger', 'eagle', 'rocket', 'fire', 'star', 'bolt', 'target', 'crown', 'rhino', 'shark', 'wolf'];
const AVATAR_DIR = 'uploads/avatars';

/** Cricket-profile fields shown to anyone allowed to see the player */
function cricket_profile(array $r): array {
    $p = null;
    if (!empty($r['player_id'])) {
        $pl = row('SELECT p.id, p.name, t.id team_id, t.name team, t.short_name, t.color FROM players p JOIN teams t ON t.id=p.team_id WHERE p.id=?', [$r['player_id']]);
        if ($pl) $p = ['id' => (int)$pl['id'], 'name' => $pl['name'], 'team_id' => (int)$pl['team_id'], 'team' => $pl['team'], 'short_name' => $pl['short_name'], 'color' => $pl['color']];
    }
    return ['bio' => $r['bio'] ?? null, 'role' => $r['role'] ?? null, 'batting_style' => $r['batting_style'] ?? null,
            'bowling_style' => $r['bowling_style'] ?? null, 'experience_years' => isset($r['experience_years']) ? (int)$r['experience_years'] : null,
            'avatar_preset' => $r['avatar_preset'] ?? null, 'player' => $p];
}

/** v1.32: whole years since a date of birth (today, server time) */
function age_from_dob(?string $dob): ?int {
    if (!$dob) return null;
    try { return (new DateTime($dob))->diff(new DateTime('today'))->y; } catch (Throwable $e) { return null; }
}
function schema_v132(): void {
    if (!val("SHOW COLUMNS FROM users LIKE 'dob'")) db()->exec("ALTER TABLE users ADD COLUMN dob DATE NULL AFTER experience_years, ADD COLUMN dob_exact TINYINT(1) NOT NULL DEFAULT 0 AFTER dob");
    if (!val("SHOW COLUMNS FROM users LIKE 'gender'")) db()->exec("ALTER TABLE users ADD COLUMN gender CHAR(1) NULL AFTER dob_exact, ADD COLUMN address VARCHAR(250) NULL AFTER gender");
    if (!val("SHOW COLUMNS FROM users LIKE 'country'")) db()->exec("ALTER TABLE users ADD COLUMN country CHAR(2) NULL AFTER address, ADD COLUMN state VARCHAR(80) NULL AFTER country, ADD COLUMN city VARCHAR(80) NULL AFTER state");
}

/** v1.33: ISO-2 codes of every country in the bundled list */
function geo_country_codes(): array {
    static $codes = null;
    if ($codes === null) { $l = json_decode((string)@file_get_contents(dirname(__DIR__) . '/assets/geo/countries.json'), true) ?: []; $codes = array_map(fn($c) => $c[0], $l); }
    return $codes;
}

/**
 * v1.33: "Use my location" - turn GPS coordinates into country / state / city / street with OpenStreetMap Nominatim
 * (free, no key; usage policy: max 1 request a second, a real User-Agent). Results are cached per ~100 m in data/cache/geo.
 */
function geo_reverse(array $in): array {
    $lat = (float)($in['lat'] ?? 0); $lon = (float)($in['lon'] ?? 0);
    if (!$lat && !$lon || abs($lat) > 90 || abs($lon) > 180) fail('Location not available');
    $dir = dirname(__DIR__) . '/data/cache/geo';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $key = $dir . '/' . sprintf('%.3f_%.3f', $lat, $lon) . '.json';
    $j = is_file($key) ? json_decode((string)file_get_contents($key), true) : null;
    if (!$j) {
        $lock = fopen($dir . '/.lock', 'c');                    // one request at a time, at least 1.1 s apart
        if ($lock) flock($lock, LOCK_EX);
        $last = (float)@file_get_contents($dir . '/.last');
        $wait = 1.1 - (microtime(true) - $last); if ($wait > 0) usleep((int)($wait * 1e6));
        $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query(['format' => 'jsonv2', 'lat' => sprintf('%.6f', $lat), 'lon' => sprintf('%.6f', $lon), 'addressdetails' => 1, 'zoom' => 18, 'accept-language' => 'en']);
        $r = news_http($url);                                    // plain GET with our User-Agent (inc/news.php)
        @file_put_contents($dir . '/.last', (string)microtime(true));
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
        $j = ($r['code'] ?? 0) === 200 ? json_decode((string)$r['body'], true) : null;
        if (!$j || empty($j['address'])) fail('Could not find an address for this spot - please pick it from the lists');
        @file_put_contents($key, json_encode($j));
    }
    $a = $j['address'];
    $city = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['city_district'] ?? $a['county'] ?? $a['state_district'] ?? null;
    $street = implode(', ', array_filter([$a['house_number'] ?? null, $a['building'] ?? null, $a['road'] ?? null, $a['neighbourhood'] ?? null, $a['suburb'] ?? $a['quarter'] ?? null, $a['postcode'] ?? null]));
    return ['country' => isset($a['country_code']) ? strtoupper($a['country_code']) : null, 'country_name' => $a['country'] ?? null, 'state' => $a['state'] ?? $a['region'] ?? null,
            'city' => $city, 'district' => $a['state_district'] ?? $a['county'] ?? null, 'street' => $street ?: null];
}

/** Save the editable profile fields sent from My Profile */
function profile_fields_save(array $u, array $in): void {
    $set = []; $vals = [];
    if (array_key_exists('bio', $in)) { $b = trim(preg_replace('/\s+/u', ' ', (string)$in['bio'])); $set[] = 'bio=?'; $vals[] = $b === '' ? null : mb_substr($b, 0, 160); }
    if (array_key_exists('role', $in)) { $set[] = 'role=?'; $vals[] = in_array($in['role'], PROFILE_ROLES, true) ? $in['role'] : null; }
    if (array_key_exists('batting_style', $in)) { $set[] = 'batting_style=?'; $vals[] = in_array($in['batting_style'], PROFILE_BATTING, true) ? $in['batting_style'] : null; }
    if (array_key_exists('bowling_style', $in)) { $set[] = 'bowling_style=?'; $vals[] = in_array($in['bowling_style'], PROFILE_BOWLING, true) ? $in['bowling_style'] : null; }
    if (array_key_exists('experience_years', $in)) {
        $e = $in['experience_years'];
        $set[] = 'experience_years=?'; $vals[] = ($e === '' || $e === null) ? null : max(0, min(60, (int)$e));
    }
    // v1.32: age - a date of birth, or an age that is turned into an approximate birth date (so it goes up by 1 every year from the day it was entered)
    if (array_key_exists('age_mode', $in)) {
        $dob = null; $exact = 0;
        if ($in['age_mode'] === 'dob' && !empty($in['dob'])) {
            $d = DateTime::createFromFormat('!Y-m-d', (string)$in['dob']);
            if (!$d || $d->format('Y-m-d') !== (string)$in['dob']) fail('Enter your date of birth as a full date');
            $dob = $d->format('Y-m-d'); $exact = 1;
        } elseif ($in['age_mode'] === 'age' && ($in['age'] ?? '') !== '' && $in['age'] !== null) {
            $a = (int)$in['age'];
            if ($a < 3 || $a > 100) fail('Enter an age between 3 and 100');
            $keep = row('SELECT dob, dob_exact FROM users WHERE id=?', [$u['id']]);
            if ($keep && $keep['dob'] && !$keep['dob_exact'] && age_from_dob($keep['dob']) === $a) $dob = $keep['dob'];   // unchanged age: keep its anniversary
            else $dob = (new DateTime('today'))->modify("-$a years")->format('Y-m-d');
        }
        if ($dob) {
            $age = age_from_dob($dob);
            if ($age < 3 || $age > 100) fail('That date of birth gives an age of ' . $age . ' - please check it');
        }
        $set[] = 'dob=?'; $vals[] = $dob; $set[] = 'dob_exact=?'; $vals[] = $exact;
        // fill the birth year of the player linked to this account when the club didn't have it yet (club league age categories)
        $pid = array_key_exists('player_id', $in) ? nint($in['player_id']) : nint(val('SELECT player_id FROM users WHERE id=?', [$u['id']]));
        if ($dob && $pid) q('UPDATE players SET birth_year=? WHERE id=? AND birth_year IS NULL', [(int)substr($dob, 0, 4), $pid]);
    }
    // v1.32: gender (M / F / O = prefer not to say) and postal address - both private to the account
    if (array_key_exists('gender', $in)) {
        $g = in_array($in['gender'], ['M', 'F', 'O'], true) ? $in['gender'] : null;
        $set[] = 'gender=?'; $vals[] = $g;
        $pid = array_key_exists('player_id', $in) ? nint($in['player_id']) : nint(val('SELECT player_id FROM users WHERE id=?', [$u['id']]));
        if ($pid && in_array($g, ['M', 'F'], true)) q('UPDATE players SET gender=? WHERE id=? AND gender IS NULL', [$g, $pid]);   // club league "min women" checks
    }
    if (array_key_exists('address', $in)) { $a = trim(preg_replace('/[ \t]+/u', ' ', (string)$in['address'])); $set[] = 'address=?'; $vals[] = $a === '' ? null : mb_substr($a, 0, 250); }
    // v1.33: country (ISO code from assets/geo/countries.json), state and city - private like the address
    if (array_key_exists('country', $in)) {
        $cc = strtoupper(trim((string)$in['country']));
        $ok = $cc !== '' && in_array($cc, geo_country_codes(), true);
        $clean = fn($k) => ($v = trim(preg_replace('/\s+/u', ' ', (string)($in[$k] ?? '')))) === '' ? null : mb_substr($v, 0, 80);
        $set[] = 'country=?'; $vals[] = $ok ? $cc : null;
        $set[] = 'state=?'; $vals[] = $ok ? $clean('state') : null;
        $set[] = 'city=?'; $vals[] = $ok ? $clean('city') : null;
    }
    if (array_key_exists('avatar_preset', $in)) { $set[] = 'avatar_preset=?'; $vals[] = in_array($in['avatar_preset'], AVATAR_PRESETS, true) ? $in['avatar_preset'] : null; }
    if (array_key_exists('player_id', $in)) {
        $pid = nint($in['player_id']);
        if ($pid) {
            $pl = row('SELECT p.id, t.category, t.owner_id FROM players p JOIN teams t ON t.id=p.team_id WHERE p.id=?', [$pid]);
            if (!$pl) fail('Player not found');
            if ($pl['category'] !== 'custom') fail('Pick yourself from one of your own teams (not an IPL or international squad) 😄');
            $other = val('SELECT username FROM users WHERE player_id=? AND id<>?', [$pid, $u['id']]);
            if ($other) fail('That player is already linked to @' . $other);
        }
        $set[] = 'player_id=?'; $vals[] = $pid;
    }
    if ($set) q('UPDATE users SET ' . implode(', ', $set) . ' WHERE id=?', array_merge($vals, [$u['id']]));
}

function avatar_is_upload(?string $url): bool { return $url !== null && strpos($url, AVATAR_DIR . '/') === 0; }
function avatar_delete_file(?string $url): void {
    if (!avatar_is_upload($url)) return;
    $f = __DIR__ . '/../' . $url;
    if (is_file($f) && preg_match('#^uploads/avatars/u\d+_[a-f0-9]{12}\.(jpg|png|webp)$#', $url)) @unlink($f);
}
function avatar_dir(): string {
    $dir = __DIR__ . '/../' . AVATAR_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) fail('The server could not create the photos folder (uploads/avatars). Please check folder permissions.');
    // Never let anything in uploads run as code
    $ht = __DIR__ . '/../uploads/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh|html?|svg)$\">\n  Require all denied\n</FilesMatch>\n<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n<IfModule mod_headers.c>\n  <FilesMatch \"\\.(jpe?g|png|webp)$\">\n    Header set Cache-Control \"public, max-age=2592000\"\n  </FilesMatch>\n</IfModule>\n");
    return $dir;
}

/** Save a profile photo sent as a data URL (the phone already cropped it to a square) */
function avatar_upload(array $u, array $in): array {
    $data = (string)($in['image'] ?? '');
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $data, $m)) fail('Please choose a JPG, PNG or WebP photo');
    $bin = base64_decode($m[2], true);
    if ($bin === false || strlen($bin) < 100) fail('That photo could not be read');
    if (strlen($bin) > 2 * 1024 * 1024) fail('That photo is too big (max 2 MB)');
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) fail('That file is not a photo');
    if ($info[0] > 4096 || $info[1] > 4096 || $info[0] < 32 || $info[1] < 32) fail('Please use a normal-sized photo');
    $dir = avatar_dir();
    $name = 'u' . (int)$u['id'] . '_' . bin2hex(random_bytes(6));
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg') && ($src = @imagecreatefromstring($bin))) {
        // Re-draw the image: makes a clean 320×320 JPEG and strips location data and anything hidden in the file
        $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
        $dst = imagecreatetruecolor(320, 320);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), 320, 320, $side, $side);
        $file = "$name.jpg";
        if (!imagejpeg($dst, "$dir/$file", 85)) fail('The server could not save your photo');
        imagedestroy($src); imagedestroy($dst);
    } else {
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
        $file = "$name.$ext";
        if (@file_put_contents("$dir/$file", $bin) === false) fail('The server could not save your photo');
    }
    $old = val('SELECT avatar_url FROM users WHERE id=?', [$u['id']]);
    q('UPDATE users SET avatar_url=? WHERE id=?', [AVATAR_DIR . "/$file", $u['id']]);
    avatar_delete_file($old);
    return ['user' => public_user(row('SELECT * FROM users WHERE id=?', [$u['id']]))];
}

function avatar_remove(array $u): array {
    $old = val('SELECT avatar_url FROM users WHERE id=?', [$u['id']]);
    q('UPDATE users SET avatar_url=NULL WHERE id=?', [$u['id']]);
    avatar_delete_file($old);
    return ['user' => public_user(row('SELECT * FROM users WHERE id=?', [$u['id']]))];
}

/** Permanently delete the signed-in player's account and everything they created (Privacy Policy section 10) */
function account_delete(array $u, array $in): array {
    if (!empty($u['is_admin'])) fail('The administrator account looks after this website, so it can\'t be deleted from the app');
    if ((string)$u['password_hash'] !== '') {
        if (!password_verify((string)($in['password'] ?? ''), $u['password_hash'])) { usleep(600000); fail('That password is wrong'); }
    } elseif (strtolower(trim((string)($in['confirm'] ?? ''))) !== strtolower($u['username'])) {
        fail('Type your username exactly to confirm');
    }
    $uid = (int)$u['id'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Matches they scored (innings, balls and line-ups go with them)
        q('DELETE FROM matches WHERE owner_id=?', [$uid]);
        // Tournaments and match types they made; other people's matches just lose the link
        q('DELETE FROM tournaments WHERE owner_id=?', [$uid]);
        $mt = array_map('intval', array_column(rows('SELECT id FROM match_types WHERE owner_id=? AND is_system=0', [$uid]), 'id'));
        if ($mt) {
            $in_ = implode(',', $mt);
            q("UPDATE matches SET match_type_id=NULL WHERE match_type_id IN ($in_)");
            q("UPDATE tournaments SET match_type_id=NULL WHERE match_type_id IN ($in_)");
            q("DELETE FROM match_types WHERE id IN ($in_)");
        }
        // Their teams: keep any team someone else is still using, but unlink it from this account
        foreach (array_map('intval', array_column(rows("SELECT id FROM teams WHERE owner_id=? AND category='custom'", [$uid]), 'id')) as $tid) {
            $used = val('SELECT 1 FROM matches WHERE team1_id=? OR team2_id=? LIMIT 1', [$tid, $tid])
                 || val('SELECT 1 FROM tournament_teams WHERE team_id=? LIMIT 1', [$tid]);
            if ($used) { q('UPDATE teams SET owner_id=NULL WHERE id=?', [$tid]); continue; }
            q('UPDATE users SET player_id=NULL WHERE player_id IN (SELECT id FROM players WHERE team_id=?)', [$tid]);
            q('DELETE FROM teams WHERE id=?', [$tid]);   // its players go with it
        }
        q('UPDATE teams SET owner_id=NULL WHERE owner_id=?', [$uid]);
        q('UPDATE match_types SET owner_id=NULL WHERE owner_id=?', [$uid]);
        q('DELETE FROM academies WHERE owner_id=?', [$uid]);
        // Gully Duel games keep their scores for the totals but lose the link to this player
        q('UPDATE games SET p0=NULL WHERE p0=?', [$uid]);
        q('UPDATE games SET p1=NULL WHERE p1=?', [$uid]);
        // The account itself; follows, Academy progress, help messages and sign-in tokens are removed with it
        q('DELETE FROM users WHERE id=?', [$uid]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    avatar_delete_file($u['avatar_url'] ?? null);
    error_log("Cric-Scorer: account $uid deleted by its owner");
    clear_remember_token();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    return ['ok' => true];
}

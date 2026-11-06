<?php
/**
 * Match photos - v1.21
 * Players add photos from the ground during or after a match. The phone already shrinks them;
 * the server re-draws every photo (max 1280px, JPEG) so nothing hidden in the file survives,
 * location data is stripped, and big files are saved at a slightly lower quality.
 */
declare(strict_types=1);

const PHOTO_DIR = 'uploads/matches';
const PHOTO_MAX_PER_MATCH = 40;
const PHOTO_MAX_SIDE = 1280;     // long side of the saved photo
const PHOTO_THUMB_SIDE = 420;    // long side of the timeline thumbnail
const PHOTO_TARGET_BYTES = 320 * 1024;   // above this the quality is lowered a step

function schema_v121(): void {
    if (!val("SHOW TABLES LIKE 'match_photos'")) db()->exec("CREATE TABLE IF NOT EXISTS match_photos (
        id INT AUTO_INCREMENT PRIMARY KEY, match_id INT NOT NULL, user_id INT NULL, who VARCHAR(100) NULL,
        file VARCHAR(120) NOT NULL, thumb VARCHAR(120) NOT NULL, width SMALLINT NOT NULL DEFAULT 0, height SMALLINT NOT NULL DEFAULT 0, bytes INT NOT NULL DEFAULT 0,
        caption VARCHAR(200) NULL, score VARCHAR(40) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (match_id), INDEX (user_id), FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function photo_dir(): string {
    $dir = __DIR__ . '/../' . PHOTO_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) fail('The server could not create the photos folder (uploads/matches). Please check folder permissions.');
    avatar_dir();   // makes sure uploads/.htaccess exists so nothing in uploads can run as code
    return $dir;
}

/** Who may add a photo: the scorer/creator/admin, owners of either team, or a signed-in player who is in the match */
function can_add_photo(array $m, ?array $u): bool {
    if (!$u) return false;
    if (can_score_match($m, $u)) return true;
    if (!can_view_match($m, $u)) return false;
    if (val('SELECT 1 FROM teams WHERE id IN (?,?) AND owner_id=?', [$m['team1_id'], $m['team2_id'], $u['id']])) return true;
    if (!empty($u['player_id']) && val('SELECT 1 FROM match_players WHERE match_id=? AND player_id=?', [$m['id'], $u['player_id']])) return true;
    return false;
}

function photo_row(array $r, ?array $u): array {
    return ['id' => (int)$r['id'], 'url' => PHOTO_DIR . '/' . $r['file'], 'thumb' => PHOTO_DIR . '/' . $r['thumb'], 'w' => (int)$r['width'], 'h' => (int)$r['height'],
        'caption' => $r['caption'], 'who' => $r['who'], 'user_id' => $r['user_id'] ? (int)$r['user_id'] : null, 'score' => $r['score'], 'at' => $r['created_at'],
        'can_delete' => $u && (!empty($u['is_admin']) || ((int)$r['user_id'] === (int)$u['id']))];
}

function match_photos(array $m, ?array $u): array {
    $rows = rows('SELECT * FROM match_photos WHERE match_id=? ORDER BY created_at, id', [$m['id']]);
    $out = array_map(fn($r) => photo_row($r, $u), $rows);
    if ($u && can_score_match($m, $u)) foreach ($out as &$p) $p['can_delete'] = true;
    return $out;
}

/** Shrink-and-redraw helper: returns [path-written, bytes] */
function photo_save($src, int $sw, int $sh, int $maxSide, string $path, int $quality): int {
    $scale = min(1, $maxSide / max($sw, $sh));
    $w = max(1, (int)round($sw * $scale)); $h = max(1, (int)round($sh * $scale));
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
    imageinterlace($dst, true);
    if (!imagejpeg($dst, $path, $quality)) { imagedestroy($dst); fail('The server could not save your photo'); }
    imagedestroy($dst);
    return (int)filesize($path);
}

/** Save one photo sent as a data URL; returns the new photo */
function match_photo_add(array $m, array $u, array $in): array {
    if (!can_add_photo($m, $u)) fail('Only players in this match, the team owners or the scorer can add photos', 403);
    if ((int)val('SELECT COUNT(*) FROM match_photos WHERE match_id=?', [$m['id']]) >= PHOTO_MAX_PER_MATCH) fail('This match already has ' . PHOTO_MAX_PER_MATCH . ' photos - that\'s the limit');
    $data = (string)($in['image'] ?? '');
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $data, $mm)) fail('Please choose a JPG, PNG or WebP photo');
    $bin = base64_decode($mm[2], true);
    if ($bin === false || strlen($bin) < 100) fail('That photo could not be read');
    if (strlen($bin) > 6 * 1024 * 1024) fail('That photo is too big (max 6 MB)');
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) fail('That file is not a photo');
    if ($info[0] > 8000 || $info[1] > 8000 || $info[0] < 64 || $info[1] < 64) fail('Please use a normal-sized photo');
    if (!function_exists('imagecreatefromstring')) fail('Photo uploads need the GD image library on the server');
    $src = @imagecreatefromstring($bin);
    if (!$src) fail('That photo could not be opened');
    // Phones store the rotation in EXIF; turn the pixels the right way up before we throw the EXIF away
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bin));
        $o = (int)($exif['Orientation'] ?? 1);
        if (in_array($o, [3, 6, 8], true)) { $rot = imagerotate($src, [3 => 180, 6 => -90, 8 => 90][$o], 0); if ($rot) { imagedestroy($src); $src = $rot; } }
    }
    $sw = imagesx($src); $sh = imagesy($src);
    $dir = photo_dir();
    $name = 'm' . (int)$m['id'] . '_' . bin2hex(random_bytes(6));
    $file = "$name.jpg"; $thumb = "{$name}_t.jpg";
    // Normal quality first; if the file is still big, save it again a little lower
    $bytes = photo_save($src, $sw, $sh, PHOTO_MAX_SIDE, "$dir/$file", 80);
    if ($bytes > PHOTO_TARGET_BYTES) $bytes = photo_save($src, $sw, $sh, PHOTO_MAX_SIDE, "$dir/$file", 66);
    if ($bytes > PHOTO_TARGET_BYTES * 1.6) $bytes = photo_save($src, $sw, $sh, 1024, "$dir/$file", 60);
    photo_save($src, $sw, $sh, PHOTO_THUMB_SIDE, "$dir/$thumb", 72);
    imagedestroy($src);
    [$w, $h] = @getimagesize("$dir/$file") ?: [0, 0];
    $inn = latest_innings((int)$m['id']);
    $score = $inn ? $inn['runs'] . '/' . $inn['wickets'] . ' (' . overs_str((int)$inn['legal_balls'], (int)$m['balls_per_over']) . ')' : null;
    $caption = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['caption'] ?? ''))), 0, 200);
    q('INSERT INTO match_photos (match_id, user_id, who, file, thumb, width, height, bytes, caption, score) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$m['id'], $u['id'], $u['display_name'] ?? null, $file, $thumb, (int)$w, (int)$h, $bytes, $caption !== '' ? $caption : null, $score]);
    $r = row('SELECT * FROM match_photos WHERE id=?', [db()->lastInsertId()]);
    $p = photo_row($r, $u); $p['can_delete'] = true;
    return ['photo' => $p, 'photos' => match_photos($m, $u)];
}

function match_photo_caption(array $m, array $u, array $in): array {
    $r = row('SELECT * FROM match_photos WHERE id=? AND match_id=?', [int_in($in, 'photo_id'), $m['id']]);
    if (!$r) fail('Photo not found', 404);
    if (!(!empty($u['is_admin']) || (int)$r['user_id'] === (int)$u['id'] || can_score_match($m, $u))) fail('Only the person who added this photo or the scorer can change it', 403);
    $caption = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['caption'] ?? ''))), 0, 200);
    q('UPDATE match_photos SET caption=? WHERE id=?', [$caption !== '' ? $caption : null, $r['id']]);
    return ['photos' => match_photos($m, $u)];
}

function photo_unlink(array $r): void {
    foreach ([$r['file'], $r['thumb']] as $f) {
        if (preg_match('#^m\d+_[a-f0-9]{12}(_t)?\.jpg$#', (string)$f)) @unlink(__DIR__ . '/../' . PHOTO_DIR . '/' . $f);
    }
}

function match_photo_delete(array $m, array $u, array $in): array {
    $r = row('SELECT * FROM match_photos WHERE id=? AND match_id=?', [int_in($in, 'photo_id'), $m['id']]);
    if (!$r) fail('Photo not found', 404);
    if (!(!empty($u['is_admin']) || (int)$r['user_id'] === (int)$u['id'] || can_score_match($m, $u))) fail('Only the person who added this photo or the scorer can remove it', 403);
    q('DELETE FROM match_photos WHERE id=?', [$r['id']]);
    photo_unlink($r);
    return ['photos' => match_photos($m, $u)];
}

/** Remove the files when a match is deleted (the rows cascade) */
function match_photos_purge(int $matchId): void {
    foreach (rows('SELECT file, thumb FROM match_photos WHERE match_id=?', [$matchId]) as $r) photo_unlink($r);
}

<?php
/* Cric-Scorer - one-tap updates from GitHub, with a backup and roll back.
   Never touches config.php, config.local.php, install.php, install.lock or uploaded photos. */

defined('UPDATE_REPO')    || define('UPDATE_REPO', 'unicalsystem/cricscorerapp');
defined('UPDATE_BRANCH')  || define('UPDATE_BRANCH', 'main');
defined('GITHUB_TOKEN')   || define('GITHUB_TOKEN', '');        // only needed if the repository is private (read-only token)
defined('UPDATE_RAW_URL') || define('UPDATE_RAW_URL', 'https://raw.githubusercontent.com/' . UPDATE_REPO . '/' . UPDATE_BRANCH . '/');
defined('UPDATE_ZIP_URL') || define('UPDATE_ZIP_URL', 'https://codeload.github.com/' . UPDATE_REPO . '/zip/refs/heads/' . UPDATE_BRANCH);

function app_root(): string { return dirname(__DIR__); }
function backup_dir(): string { return app_root() . '/data/backups'; }

/** Paths an update must never overwrite (relative to the app folder) */
function update_protected(string $rel): bool {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if (in_array($rel, ['config.php', 'config.local.php', 'install.php', 'install.lock', '.gitignore'], true)) return true;
    if (preg_match('#^(\.git|\.github)/#', $rel)) return true;
    if (preg_match('#^data/(backups|update)/#', $rel)) return true;
    if (strpos($rel, 'uploads/') === 0 && !in_array($rel, ['uploads/.htaccess', 'uploads/avatars/.gitkeep'], true)) return true;
    return false;
}

function http_get(string $url, ?string $saveTo = null, int $timeout = 20): array {
    $headers = ['User-Agent: Cric-Scorer-Updater'];
    if (GITHUB_TOKEN !== '') $headers[] = 'Authorization: Bearer ' . GITHUB_TOKEN;
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $headers) . "\r\n", 'follow_location' => 1]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $code = (int)$m[1];
        if ($body !== false && $saveTo) file_put_contents($saveTo, $body);
        return ['code' => $code, 'body' => $saveTo ? null : ($body === false ? null : $body)];
    }
    $ch = curl_init($url);
    $fh = $saveTo ? fopen($saveTo, 'wb') : null;
    curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true]);
    if ($fh) curl_setopt($ch, CURLOPT_FILE, $fh); else curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($fh) fclose($fh);
    return ['code' => $code, 'body' => $fh ? null : ($body === false ? null : $body), 'error' => $err];
}

function parse_version(string $php): ?string {
    return preg_match("/define\(\s*'APP_VERSION'\s*,\s*'([0-9A-Za-z.\-]+)'\s*\)/", $php, $m) ? $m[1] : null;
}

/** "## 1.9.0 - 24 October 2026" sections newer than the running version */
function changelog_since(string $md, string $since): array {
    $out = []; $cur = null;
    foreach (preg_split('/\R/', $md) as $line) {
        if (preg_match('/^##\s+v?([0-9][0-9A-Za-z.\-]*)\s*(?:[-–—]\s*(.*))?$/', $line, $m)) {
            if ($cur) $out[] = $cur;
            $cur = version_compare($m[1], $since, '>') ? ['version' => $m[1], 'date' => trim($m[2] ?? ''), 'items' => []] : null;
        } elseif ($cur && preg_match('/^\s*[-*]\s+(.+)$/', $line, $m)) $cur['items'][] = trim($m[1]);
    }
    if ($cur) $out[] = $cur;
    return array_slice($out, 0, 10);
}

function list_backups(): array {
    $o = [];
    foreach (glob(backup_dir() . '/cricscorer-*.zip') ?: [] as $f) {
        $b = basename($f);
        $o[] = ['file' => $b, 'version' => preg_match('/^cricscorer-([0-9A-Za-z.\-]+?)-\d{8}-\d{6}\.zip$/', $b, $m) ? $m[1] : '?', 'size' => filesize($f), 'at' => date('j M Y, g:i a', filemtime($f)), 't' => filemtime($f)];
    }
    usort($o, fn($a, $b) => [$b['t'], $b['file']] <=> [$a['t'], $a['file']]);
    return $o;
}

function update_status(): array {
    $last = setting('last_update');
    return ['current' => APP_VERSION, 'force_version' => setting('force_version'), 'backups' => list_backups(),
        'last' => $last ? json_decode($last, true) : null, 'zip_ok' => class_exists('ZipArchive'), 'repo' => UPDATE_REPO, 'branch' => UPDATE_BRANCH];
}

function update_check(): array {
    $st = update_status();
    $r = http_get(UPDATE_RAW_URL . 'inc/version.php?t=' . time());
    if ($r['code'] !== 200 || !$r['body'] || !($latest = parse_version($r['body'])))
        return $st + ['error' => 'Could not reach GitHub (' . ($r['code'] ?: ($r['error'] ?? 'no connection')) . '). Check the server can make HTTPS requests.'];
    $cl = http_get(UPDATE_RAW_URL . 'CHANGELOG.md?t=' . time());
    $writable = update_writable_problems();
    return $st + ['latest' => $latest, 'newer' => version_compare($latest, APP_VERSION, '>'),
        'changes' => $cl['code'] === 200 && $cl['body'] ? changelog_since($cl['body'], APP_VERSION) : [],
        'writable' => !$writable, 'not_writable' => array_slice($writable, 0, 8)];
}

function update_writable_problems(): array {
    $bad = [];
    $root = app_root();
    foreach (['', '/inc', '/assets', '/sql', '/data'] as $d) if (is_dir($root . $d) && !is_writable($root . $d)) $bad[] = ($d ?: '/') . ' (folder)';
    foreach (['/index.php', '/api.php', '/sw.js', '/inc/version.php', '/assets/app.js'] as $f) if (is_file($root . $f) && !is_writable($root . $f)) $bad[] = $f;
    return $bad;
}

/** Zip up the running app (code only - no config, photos or old backups) so an update can be undone */
function make_backup(): string {
    if (!class_exists('ZipArchive')) fail('This server is missing PHP ZipArchive, so updates are not possible here. Ask your host to enable the zip extension.');
    $dir = backup_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) fail('Could not create the backup folder data/backups');
    $file = $dir . '/cricscorer-' . APP_VERSION . '-' . date('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) fail('Could not create a backup file');
    $root = app_root();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
        if (update_protected($rel) || strpos($rel, 'uploads/') === 0) continue;
        $zip->addFile($f->getPathname(), $rel);
    }
    $zip->close();
    // keep the newest 3 backups
    foreach (array_slice(list_backups(), 3) as $old) @unlink($dir . '/' . $old['file']);
    return basename($file);
}

/** Keep anything the web host added to the main .htaccess (PHP version handlers, cache rules) */
function merge_htaccess(string $new, string $old): string {
    $keep = [];
    if (preg_match_all('/^# BEGIN [^\n]*\n.*?^# END [^\n]*$/ms', $old, $m)) foreach ($m[0] as $blk) if (strpos($new, $blk) === false) $keep[] = $blk;
    foreach (preg_split('/\R/', $old) as $l) {
        if (preg_match('/^\s*(AddHandler|SetHandler|php_value|php_flag|SetEnv)\b/i', $l) && strpos($new, trim($l)) === false && !preg_grep('/' . preg_quote(trim($l), '/') . '/', $keep)) $keep[] = $l;
    }
    return $keep ? implode("\n", $keep) . "\n\n" . $new : $new;
}

/** Copy the files in a zip over the app. $prefix is the folder inside the zip that holds the app. */
function apply_zip(string $zipFile, string $prefix): int {
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) fail('The download is not a valid zip file');
    $root = app_root(); $n = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', $zip->getNameIndex($i));
        if ($prefix !== '' && strpos($name, $prefix) !== 0) continue;
        $rel = substr($name, strlen($prefix));
        if ($rel === '' || substr($rel, -1) === '/' || strpos($rel, '..') !== false || update_protected($rel)) continue;
        $data = $zip->getFromIndex($i);
        if ($data === false) continue;
        $dest = $root . '/' . $rel;
        if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) fail("Could not create folder for $rel");
        if ($rel === '.htaccess' && is_file($dest)) $data = merge_htaccess($data, (string)file_get_contents($dest));
        $tmp = $dest . '.cs-new';
        if (@file_put_contents($tmp, $data) === false || !@rename($tmp, $dest)) { @unlink($tmp); fail("Could not write $rel - check file permissions"); }
        $n++;
    }
    $zip->close();
    if (function_exists('opcache_reset')) @opcache_reset();
    return $n;
}

function update_install(): array {
    @set_time_limit(180);
    if (!class_exists('ZipArchive')) fail('This server is missing PHP ZipArchive, so updates are not possible here.');
    if ($bad = update_writable_problems()) fail('These files or folders are not writable: ' . implode(', ', $bad));
    $work = app_root() . '/data/update';
    if (!is_dir($work) && !@mkdir($work, 0755, true)) fail('Could not create data/update');
    $zipFile = $work . '/download.zip';
    $r = http_get(UPDATE_ZIP_URL, $zipFile, 120);
    if ($r['code'] !== 200 || !is_file($zipFile) || filesize($zipFile) < 10000) { @unlink($zipFile); fail('Download from GitHub failed (' . ($r['code'] ?: ($r['error'] ?? 'no connection')) . ')'); }
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) { @unlink($zipFile); fail('The download is not a valid zip file'); }
    // GitHub zips put everything in one top folder, e.g. "cricscorerapp-main/"
    $prefix = '';
    $first = str_replace('\\', '/', (string)$zip->getNameIndex(0));
    if (strpos($first, '/') !== false && $zip->locateName(explode('/', $first)[0] . '/inc/version.php') !== false) $prefix = explode('/', $first)[0] . '/';
    $ver = $zip->getFromName($prefix . 'inc/version.php');
    $zip->close();
    $newVer = $ver ? parse_version($ver) : null;
    if (!$newVer || $zip->open($zipFile) !== true || $zip->locateName($prefix . 'index.php') === false || $zip->locateName($prefix . 'api.php') === false) {
        @unlink($zipFile); fail('The download does not look like Cric-Scorer - nothing was changed');
    }
    $zip->close();
    $from = APP_VERSION;
    $backup = make_backup();
    $n = apply_zip($zipFile, $prefix);
    @unlink($zipFile);
    setting_set('last_update', json_encode(['from' => $from, 'to' => $newVer, 'at' => date('j M Y, g:i a'), 'files' => $n, 'backup' => $backup, 'kind' => 'update']));
    error_log("Cric-Scorer: updated $from -> $newVer ($n files, backup $backup)");
    return ['ok' => true, 'from' => $from, 'to' => $newVer, 'files' => $n, 'backup' => $backup];
}

function update_rollback(string $file): array {
    @set_time_limit(180);
    if (!preg_match('/^cricscorer-[0-9A-Za-z.\-]+-\d{8}-\d{6}\.zip$/', $file) || !is_file(backup_dir() . '/' . $file)) fail('Backup not found');
    if ($bad = update_writable_problems()) fail('These files or folders are not writable: ' . implode(', ', $bad));
    $zip = new ZipArchive();
    if ($zip->open(backup_dir() . '/' . $file) !== true) fail('That backup file is damaged');
    $ver = $zip->getFromName('inc/version.php');
    $zip->close();
    $to = $ver ? parse_version($ver) : '?';
    $from = APP_VERSION;
    $n = apply_zip(backup_dir() . '/' . $file, '');
    setting_set('last_update', json_encode(['from' => $from, 'to' => $to, 'at' => date('j M Y, g:i a'), 'files' => $n, 'backup' => $file, 'kind' => 'rollback']));
    error_log("Cric-Scorer: rolled back $from -> $to from $file");
    return ['ok' => true, 'from' => $from, 'to' => $to, 'files' => $n];
}

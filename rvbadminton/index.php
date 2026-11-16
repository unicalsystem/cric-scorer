<?php
/* RV Badminton - app shell. Everything on screen is drawn by assets/app.js from api.php. */
require_once __DIR__ . '/config.php';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
$title = BD_NAME . ' - live badminton scores, ' . BD_CLUB;
$desc = 'Live rally-by-rally badminton scoring for ' . BD_CLUB . ': matches, tournaments and player records.';
$mid = (int)($_GET['m'] ?? 0);
if ($mid) {   // a shared match link (/m/12): show the players in the link preview, then open the match
    try {
        require_once __DIR__ . '/inc/db.php';
        $m = row('SELECT a_name, b_name, status, summary FROM bd_matches WHERE id=?', [$mid]);
        if ($m) { $title = "{$m['a_name']} vs {$m['b_name']}" . ($m['summary'] ? " - {$m['summary']}" : '') . ' | ' . BD_NAME; $desc = ($m['status'] === 'live' ? 'Live now: ' : '') . $title; }
    } catch (Throwable $e) { /* the app still opens */ }
}
$v = BD_VERSION;
$logo = is_file(__DIR__ . '/assets/logo.png') ? 'assets/logo.png?v=' . filemtime(__DIR__ . '/assets/logo.png') : null;
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<base href="<?= $h($base) ?>">
<title><?= $h($title) ?></title>
<meta name="description" content="<?= $h($desc) ?>">
<meta property="og:title" content="<?= $h($title) ?>">
<meta property="og:description" content="<?= $h($desc) ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= $h(BD_URL) ?>/assets/og.jpg">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0f2440">
<link rel="manifest" href="manifest.json">
<link rel="icon" href="assets/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="assets/icon-180.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= $h(BD_NAME) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=<?= $v ?>">
</head>
<body>
<div id="splash"><?php if ($logo): ?><img src="<?= $h($logo) ?>" alt="<?= $h(BD_CLUB) ?>" width="640" height="454"><?php endif; ?><b><?= $h(BD_NAME) ?></b><span>Live badminton scoring</span><i></i></div>
<div id="app">
  <header id="top"></header>
  <main id="view" tabindex="-1"><p class="wait">Loading…</p></main>
  <nav id="tabs" aria-label="Sections"></nav>
</div>
<div id="toast" role="status" aria-live="polite"></div>
<noscript><p style="padding:24px">RV Badminton needs JavaScript switched on.</p></noscript>
<script>window.BD = { v: <?= json_encode($v) ?>, open: <?= $mid ?: 'null' ?>, logo: <?= json_encode($logo) ?>, name: <?= json_encode(BD_NAME) ?>, club: <?= json_encode(BD_CLUB) ?> };</script>
<script src="assets/app.js?v=<?= $v ?>" defer></script>
</body>
</html>

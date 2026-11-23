<?php
/* RV Tennis - app shell. Everything on screen is drawn by assets/app.js from api.php. */
require_once __DIR__ . '/config.php';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
$title = TN_NAME . ' - live tennis scores, ' . TN_CLUB;
$desc = 'Live point-by-point tennis scoring for ' . TN_CLUB . ': matches, tournaments and player records.';
$mid = (int)($_GET['m'] ?? 0);
if ($mid) {   // a shared match link (/m/12): show the players in the link preview, then open the match
    try {
        require_once __DIR__ . '/inc/db.php';
        $m = row('SELECT a_name, b_name, status, summary FROM tn_matches WHERE id=?', [$mid]);
        if ($m) { $title = "{$m['a_name']} vs {$m['b_name']}" . ($m['summary'] ? " - {$m['summary']}" : '') . ' | ' . TN_NAME; $desc = ($m['status'] === 'live' ? 'Live now: ' : '') . $title; }
    } catch (Throwable $e) { /* the app still opens */ }
}
$v = TN_VERSION;
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
<meta property="og:image" content="<?= $h(TN_URL) ?>/assets/og.jpg">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0f2440">
<link rel="manifest" href="manifest.json">
<link rel="icon" href="assets/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="assets/icon-180.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= $h(TN_NAME) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=<?= $v ?>">
</head>
<body>
<div id="splash"><?php if ($logo): ?><img src="<?= $h($logo) ?>" alt="<?= $h(TN_CLUB) ?>" width="640" height="454"><?php endif; ?><b><?= $h(TN_NAME) ?></b><span>Live tennis scoring</span><i></i></div>
<div id="app">
  <header id="top"></header>
  <main id="view" tabindex="-1"><p class="wait">Loading…</p></main>
  <nav id="tabs" aria-label="Sections"></nav>
</div>
<div id="toast" role="status" aria-live="polite"></div>
<noscript><div style="padding:24px"><p>RV Tennis needs JavaScript switched on to score. Matches in progress:</p><?php
try {
    require_once __DIR__ . '/inc/db.php';
    tn_schema();
    $live = rows("SELECT id, a_name, b_name, format, state FROM tn_matches WHERE status='live' ORDER BY id");
    if (!$live) echo '<p>No match is live right now.</p>';
    foreach ($live as $lm) {
        $ls = json_decode((string)$lm['state'], true) ?: [];
        $sc = trim(tn_summary($ls['sets'] ?? []) . ' ' . implode('-', $ls['games'] ?? [0, 0]));
        echo '<p>Match ' . (int)$lm['id'] . ': ' . $h($lm['a_name']) . ' v ' . $h($lm['b_name']) . ', ' . $h($sc) . ' (' . $h(tn_format_label(tn_format($lm['format']))) . ')</p>';
    }
} catch (Throwable $e) { /* the app itself reports problems */ }
?></div></noscript>
<script>window.TN = { v: <?= json_encode($v) ?>, open: <?= $mid ?: 'null' ?>, logo: <?= json_encode($logo) ?>, name: <?= json_encode(TN_NAME) ?>, club: <?= json_encode(TN_CLUB) ?> };</script>
<script src="assets/app.js?v=<?= $v ?>" defer></script>
</body>
</html>

<?php
/* Cric-Scorer - Match correction desk (v1.42). A full-screen page for the administrator, made for a laptop. */
require_once __DIR__ . '/inc/core.php';
require_once __DIR__ . '/inc/engine.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$u = null; $err = '';
try { ensure_schema(); $u = current_user(); } catch (Throwable $e) { $err = 'The page could not be loaded just now. Please try again in a minute.'; error_log('Cric-Scorer fix.php: ' . $e->getMessage()); }
$admin = $u && !empty($u['is_admin']);
$v = APP_VERSION;
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Match correction desk · Cric-Scorer</title>
<link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Nunito:wght@500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/fix.css?v=<?= $v ?>">
</head>
<body>
<?php if (!$admin): ?>
<div class="gate">
  <img src="assets/icons/icon-192.png" width="72" height="72" alt="">
  <h1>Match correction desk</h1>
  <?php if ($err): ?><p><?= $h($err) ?></p>
  <?php elseif (!$u): ?><p>Please log in to Cric-Scorer as the administrator first, then open this page again.</p><a class="btn" href="./#/login">Log in</a>
  <?php else: ?><p>Hi <?= $h($u['display_name']) ?> - only the administrator can open the correction desk.</p><a class="btn" href="./">Back to Cric-Scorer</a>
  <?php endif; ?>
</div>
<?php else: ?>
<header class="top">
  <div class="brand"><img src="assets/icons/icon-192.png" width="34" height="34" alt=""><div><b>Match correction desk</b><span>Cric-Scorer · administrator</span></div></div>
  <div class="top-right"><span class="who"><?= $h($u['display_name']) ?></span><a href="./" class="ghost">← Back to the app</a></div>
</header>
<div class="shell">
  <aside class="side">
    <div class="side-head">
      <div class="step"><i>1</i> Pick the match</div>
      <input type="search" id="q" placeholder="Search team, title or match no." autocomplete="off">
      <select id="tour"><option value="0">All tournaments</option></select>
      <label class="small" style="display:flex;gap:6px;align-items:center;margin-top:8px"><input type="checkbox" id="incl"> Include live &amp; upcoming matches (squad tools)</label>
      <div class="dates"><label>From <input type="date" id="dfrom"></label><label>To <input type="date" id="dto"></label><button type="button" class="ghost" id="dclear">Any date</button></div>
    </div>
    <div id="matches" class="mlist"><div class="hint">Loading matches…</div></div>
  </aside>
  <main id="main"><div class="welcome">
    <h2>Correct a finished match</h2>
    <p>Pick a match on the left. Then choose the innings and the over, and correct any ball: who was on strike, who bowled it, the runs, the extra, the wicket.</p>
    <ul>
      <li>You can also <b>add a ball</b> that was missed, <b>delete</b> one that should not be there, or <b>swap a wrongly picked player</b> for the whole match.</li>
      <li>Totals, the target, the result, Player of the Match, the points table and player stats are worked out again after every save.</li>
      <li>Nothing is lost: every save can be undone, and a match can always be put back as it was originally scored.</li>
    </ul>
  </div></main>
</div>
<div id="modal-root"></div>
<div id="toast" role="status" aria-live="polite"></div>
<script>window.FIX_BOOT = <?= json_encode(['match' => (int)($_GET['m'] ?? 0)]) ?>;</script>
<script src="assets/fix.js?v=<?= $v ?>"></script>
<?php endif; ?>
</body>
</html>

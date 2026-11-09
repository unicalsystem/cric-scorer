<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/version.php';
require_once __DIR__ . '/inc/seo.php';
if (!file_exists(__DIR__ . '/install.lock') && file_exists(__DIR__ . '/install.php')) { header('Location: install.php'); exit; }
$v = APP_VERSION;
$faq = [
  ['Is Cric-Scorer really free?', 'Yes. Cric-Scorer is completely free to use - scoring, tournaments, live sharing and the cricket academy. There are no ads and no in-app purchases.'],
  ['Do I need to download an app?', 'No. Cric-Scorer runs in your phone\'s browser. You can add it to your home screen so it opens like a normal app, and it keeps working on slow connections.'],
  ['Can I score gully, box and backyard cricket?', 'Yes. Choose T20, ODI, T10, Box Cricket, Gully Cricket or Backyard (kids), or set your own overs, players per side, bowler limits and last-man-stands rules.'],
  ['Does it handle rain-affected matches?', 'Yes. Cric-Scorer uses the Duckworth-Lewis-Stern (DLS) Standard Edition method to revise targets, show the par score and decide matches stopped by rain.'],
  ['Is it safe for children?', 'Cric-Scorer was made for kids and families. Profiles and matches can be made private, there are no ads or tracking, and anyone under 18 needs a parent\'s permission to sign up.'],
  ['How can kids learn cricket with it?', 'The Cricket Academy has 29 lessons on the Laws, field positions, batting, bowling and fielding, each followed by a quiz, with stars, ranks and a certificate.'],
];
$lessons = public_lessons();
$jsonld = [ld_app(), ld_organization(),
  ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Cric-Scorer', 'url' => site_url()],
  ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq)]];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1">
<?= seo_head(['title' => SEO_DEFAULT_TITLE, 'description' => SEO_DEFAULT_DESC, 'path' => '', 'jsonld' => $jsonld]) ?>
<meta name="keywords" content="cricket scoring app, free cricket scorer, live cricket score, cricket scorecard, gully cricket, box cricket, cricket tournament app, points table, DLS calculator, cricket for kids, learn cricket, cricket rules">
<meta name="application-name" content="Cric-Scorer">
<meta name="theme-color" content="#0d4a36">
<link rel="manifest" href="manifest.json">
<link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="assets/icons/icon-180.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Cric-Scorer">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Nunito:wght@500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=<?= $v ?>">
</head>
<body>
<div id="splash">
  <img class="logo" src="assets/icons/icon-192.png" alt="">
  <div class="brand" data-testid="splash-title">Cric-Scorer</div>
  <p>by Aaryush</p>
</div>
<?php /* Visitor home page: real text for Google and for people who are not signed in. The app hides it after sign-in. */ ?>
<main id="landing">
  <section class="lp-hero">
    <div class="lp-wrap">
      <div class="lp-brand"><img src="assets/icons/icon-192.png" alt="Cric-Scorer logo" width="56" height="56"><div><b>Cric-Scorer</b><span>by Aaryush</span></div></div>
      <h1>Free cricket scoring app for kids, families &amp; clubs</h1>
      <p class="lp-sub">Score every ball on your phone, share live scores, run tournaments and learn the game - all free, no download needed.</p>
      <div class="lp-cta"><a class="lp-btn lime" href="#/register">🏏 Start scoring - it's free</a><a class="lp-btn ghost" href="#/login">Log in</a><a class="lp-btn ghost" href="#/toss">🪙 Quick Toss</a></div>
      <p class="lp-small">Works on Android, iPhone and computers · Add it to your home screen like an app</p>
    </div>
  </section>
  <section class="lp-wrap">
    <h2>Everything you need to score a cricket match</h2>
    <div class="lp-grid">
      <article><span>⚡</span><h3>Ball-by-ball scoring</h3><p>Runs, fours, sixes, wides, no-balls, byes, leg-byes and all 9 ways of getting out - with unlimited undo.</p></article>
      <article><span>📡</span><h3>Live scores &amp; commentary</h3><p>Share a link and family can watch the score update live, with spoken commentary, crowd sounds and FOUR / SIX / OUT pop-ups.</p></article>
      <article><span>🏆</span><h3>Tournaments</h3><p>League or knockout, automatic fixtures, points table with net run rate, top run scorers and wicket takers.</p></article>
      <article><span>🌧️</span><h3>Rain rules (DLS)</h3><p>Duckworth-Lewis-Stern targets, live par score and results for rain-affected matches.</p></article>
      <article><span>🏟️</span><h3>IPL &amp; international squads</h3><p>All 10 IPL teams and 12 international teams with full squads - or build your own team.</p></article>
      <article><span>🎓</span><h3>Cricket Academy</h3><p>29 lessons and quizzes on the Laws, field positions, batting, bowling and fielding, with stars, ranks and a certificate.</p></article>
      <article><span>👥</span><h3>Players &amp; profiles</h3><p>Profile photo, cricket profile and career stats. Follow friends and see their matches.</p></article>
      <article><span>🔒</span><h3>Private &amp; safe</h3><p>Private profiles and private matches, no ads, no tracking. Made for kids and families.</p></article>
    </div>
    <h2>Start in three steps</h2>
    <ol class="lp-steps"><li><b>Create a free account</b> - with a username or Sign in with Google.</li><li><b>Pick two teams</b> - IPL, international or your own, and a match type like T20 or Gully Cricket.</li><li><b>Toss and play!</b> - tap 0–6, extras or OUT for every ball. The scorecard builds itself.</li></ol>
    <h2>Learn cricket for free</h2>
    <p>Short, fun lessons that teach the Laws of Cricket, field positions, batting shots, bowling and fielding - great for kids and new players.</p>
    <ul class="lp-lessons"><?php foreach (array_slice($lessons, 0, 12) as $pl): ?><li><a href="<?= e(lesson_url($pl)) ?>"><?= e($pl['lesson']['title']) ?></a></li><?php endforeach; ?></ul>
    <p><a href="learn/">See all <?= count($lessons) ?> cricket lessons →</a></p>
    <h2>Questions</h2>
    <?php foreach ($faq as [$q, $a]): ?><details class="lp-faq"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
    <div class="lp-final"><h2>Ready for the first ball?</h2><a class="lp-btn lime" href="#/register">Create your free account</a></div>
  </section>
  <footer class="lp-foot"><a href="learn/">Learn cricket</a><a href="privacy">Privacy Policy</a><a href="terms">Terms of Use</a><a href="mailto:<?= e(CONTACT_EMAIL) ?>">Contact</a>
    <p>© <?= date('Y') ?> <?= e(ORG_NAME) ?> · Cric-Scorer by Aaryush is free to use.</p></footer>
</main>
<div id="app"></div>
<nav id="tabbar" class="hidden" aria-label="Main"></nav>
<div id="sheet-root"></div>
<div id="toast" role="status" aria-live="polite"></div>
<div id="ptr" aria-hidden="true"><span></span></div>
<script>window.CS_VERSION = <?= json_encode(APP_VERSION) ?>; window.CS_SITE_URL = <?= json_encode(rtrim(SITE_URL, "/") . "/") ?>;</script>
<script src="assets/sound.js?v=<?= $v ?>"></script>
<script src="assets/app.js?v=<?= $v ?>"></script>
<script src="assets/pro.js?v=<?= $v ?>"></script>
<script src="assets/league.js?v=<?= $v ?>"></script>
<script src="assets/toss.js?v=<?= $v ?>"></script>
</body>
</html>

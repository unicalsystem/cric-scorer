<?php
/* Privacy Policy - served at /privacy */
require_once __DIR__ . '/inc/seo.php';
$org = e(ORG_NAME); $mail = e(CONTACT_EMAIL); $addr = e(ORG_ADDRESS); $officer = e(GRIEVANCE_OFFICER);
page_start([
    'title' => 'Privacy Policy - Cric-Scorer',
    'description' => 'How Cric-Scorer (by ' . ORG_NAME . ') collects, uses and protects your information, including rules for children and how to delete your account.',
    'path' => 'privacy',
    'jsonld' => [ld_breadcrumbs([['Home', ''], ['Privacy Policy', 'privacy']])],
]);
?>
<p class="crumbs"><a href="<?= e(site_url()) ?>">Home</a> › Privacy Policy</p>
<h1>Privacy Policy</h1>
<p class="updated">Last updated: <?= e(POLICY_UPDATED) ?></p>

<div class="card">
  <h2 style="margin-top:0">The short version</h2>
  <ul>
    <li>Cric-Scorer is <b>free</b>. We do <b>not</b> show ads, sell your data or track you across other websites.</li>
    <li>We only keep what the app needs to work: your account, your profile, the matches and teams you create, and your learning progress.</li>
    <li>You choose what others see. Profiles and matches can be made <b>private</b>.</li>
    <li>Anyone under 18 needs a <b>parent or guardian's permission</b> to use Cric-Scorer.</li>
    <li>You can <b>delete your account</b> and all its data at any time from <i>More → My Profile</i>.</li>
    <li>Questions? Email <a href="mailto:<?= $mail ?>"><?= $mail ?></a>.</li>
  </ul>
</div>

<div class="toc"><b>Contents</b><ol>
  <li><a href="#who">Who we are</a></li><li><a href="#collect">What we collect</a></li><li><a href="#use">How we use it</a></li>
  <li><a href="#visible">What other people can see</a></li><li><a href="#children">Children and parents</a></li><li><a href="#share">Who we share it with</a></li>
  <li><a href="#cookies">Cookies and storage on your device</a></li><li><a href="#keep">How long we keep it and how we protect it</a></li>
  <li><a href="#rights">Your rights</a></li><li><a href="#delete">Deleting your account</a></li><li><a href="#changes">Changes to this policy</a></li><li><a href="#contact">Contact and grievances</a></li>
</ol></div>

<h2 id="who">1. Who we are</h2>
<p>Cric-Scorer ("Cric-Scorer by Aaryush", "the app") is run by <b><?= $org ?></b>, <?= $addr ?> ("we", "us"). We decide how and why your personal data is used, which makes us the <i>Data Fiduciary</i> under India's Digital Personal Data Protection Act, 2023 (DPDP Act).</p>
<p>This policy covers the website and web app at <a href="<?= e(site_url()) ?>"><?= e(preg_replace('#^https?://#', '', SITE_URL)) ?></a> and any other address where we run Cric-Scorer.</p>

<h2 id="collect">2. What we collect</h2>
<table class="data">
  <tr><th>What</th><th>Details</th><th>Why</th></tr>
  <tr><td><b>Account</b></td><td>Username, display name and password. We store passwords only as a secure one-way hash, so nobody can read them, not even us.</td><td>To create your account and let you sign in.</td></tr>
  <tr><td><b>Sign in with Google</b> (optional)</td><td>If you use it: your Google account ID, email address, name and profile picture, which Google shares with us when you agree.</td><td>To sign you in without a password.</td></tr>
  <tr><td><b>Mobile number</b> (optional)</td><td>Your WhatsApp number, if you choose to add it, and the date it was verified. We send the verification message through Meta's WhatsApp Cloud API; Meta receives your number and the message (see <a href="https://www.whatsapp.com/legal/business-data-transfer-addendum" rel="noopener">WhatsApp's business terms</a>). One account per number.</td><td>To verify it's you, sign you in with one tap, and let academies you train at find you. Never for marketing messages.</td></tr>
  <tr><td><b>Academy</b> (optional)</td><td>If an academy registers on Cric-Scorer and adds you as a student: the academy's name and your membership. Academy owners also give us the academy's name, address and phone.</td><td>So academies can run teams and matches with their students.</td></tr>
  <tr><td><b>Profile</b> (optional)</td><td>Profile photo or chosen figure, a short bio, playing role, batting and bowling style, years of experience, and the team player you link to yourself.</td><td>To show your player profile and career stats.</td></tr>
  <tr><td><b>APL Auction</b></td><td>Auction rooms you create or join: your team name, colour and avatar, bids, purchases and virtual coins. Coins are not money and cannot be bought or cashed out.</td><td>To run the auction for everyone in the room and show results. Rooms are private to people with the code.</td></tr>
<tr><td><b>Grounds and "looking for" posts</b></td><td>Ground details you list (name, address, timings, price, contact name and WhatsApp number) and posts you make on the Looking-for board, including any contact you type in, plus replies you send.</td><td>Shown to other players so they can find grounds and matches. Replies are shown only to the person who posted. You can edit or delete them any time.</td></tr>
<tr><td><b>Cricket data you enter</b></td><td>Teams, player names, matches, tournaments and every ball you score, including shot and fielding details, umpire and scorer names you type in, a live-stream link, venues and dates.</td><td>This is the main purpose of the app: scorecards, results and statistics.</td></tr>
  <tr><td><b>Learning progress</b></td><td>Lessons completed, quiz scores, stars, XP and rank in the Cricket Academy.</td><td>To unlock lessons, show your progress and the Academy leaderboard.</td></tr>
  <tr><td><b>Social</b></td><td>Who you follow and who follows you, and your privacy choices.</td><td>For the Players, Followers and Following features.</td></tr>
  <tr><td><b>Team permissions</b></td><td>Requests to use another player's custom team, and which players a team owner has allowed.</td><td>So owners decide who plays with their teams. The owner sees your name and username when you ask.</td></tr>
  <tr><td><b>Gully Duel games</b></td><td>Scores and results of bat-vs-bowl games you play, who played whom in two-phone games, and your in-game coins, squad and unlocked players (coins are only won by playing - there is nothing to buy).</td><td>To run the game and show the result to both players.</td></tr>
  <tr><td><b>Help messages</b></td><td>Messages you send from <i>Help &amp; feedback</i> and our replies, with the app version and type of device (such as "Android").</td><td>To answer your questions and fix problems.</td></tr>
  <tr><td><b>Usage information</b></td><td>When you last used the app, which app version and which type of device (such as "iPhone"). We do not record your exact device, location or browsing.</td><td>Only to count, in totals, how many people use the app and which versions to support.</td></tr>
  <tr><td><b>Consent record</b></td><td>The date and time you accepted these terms (including a parent's permission for under-18s).</td><td>To show we have your consent.</td></tr>
  <tr><td><b>Technical data</b></td><td>Our hosting provider's servers automatically record standard web logs (such as IP address, browser type and the pages requested), and we log errors to fix problems. Error reports have names, emails and typed text blanked out before they are saved.</td><td>To keep the service running, secure and free of abuse.</td></tr>
</table>
<p>We do <b>not</b> ask for your home address, school, payment details or exact location. Please don't type such details into names, bios or match titles.</p>

<h2 id="use">3. How we use it</h2>
<ul>
  <li>To provide the app: accounts, scoring, scorecards, statistics, tournaments, live sharing, the Cricket Academy and player profiles.</li>
  <li>To keep it safe: protecting accounts, preventing spam and bots (for example with Google reCAPTCHA), and fixing errors.</li>
  <li>To reply when you contact us.</li>
</ul>
<p>We use your data on the basis of the <b>consent</b> you (or your parent or guardian) give when you create an account, and where needed to meet a legal obligation. We do <b>not</b> use your data for advertising, profiling or automated decisions about you, and we never sell it.</p>

<h2 id="visible">4. What other people can see</h2>
<ul>
  <li><b>Public profile</b> (the default): other signed-in users can find you by name or username and see your display name, photo or figure, bio, cricket profile, career stats, followers and the public matches you scored.</li>
  <li><b>Private profile</b>: turn it on in <i>My Profile → Privacy</i>. You then disappear from search, follower lists and the Academy leaderboard, and nobody new can follow you.</li>
  <li><b>Public matches</b> can be watched by anyone you share the link with, even without an account. <b>Private matches</b> can only be opened by the person who scored them.</li>
  <li>Shared match links show a summary (teams, score and result) when posted in apps like WhatsApp. These match pages are marked so that <b>search engines do not list them</b>, and our sitemap never includes players, profiles or matches.</li>
  <li>In <b>Gully Duel</b>, the player you play against sees your display name and photo or figure during the game.</li>
  <li>The app administrator (the family member or person who set up this Cric-Scorer website) can see all accounts and matches in order to supervise and look after the service. The admin dashboard shows totals and charts (such as how many players joined), help messages without the sender's name, and a list of which signed-in players are using the app right now (their name and which screen they are on, kept for one day). Visitors who are not signed in are only counted, never named.</li>
</ul>

<h2 id="children">5. Children and parents</h2>
<p>Cric-Scorer was made for young cricket fans and their families. Under the DPDP Act a child is anyone under 18.</p>
<ul>
  <li>Anyone <b>under 18</b> may use Cric-Scorer only with the permission of a <b>parent or legal guardian</b>. At sign-up, the person creating the account confirms either that they are 18 or over, or that their parent or guardian agrees. We encourage parents to create or set up their child's account themselves.</li>
  <li>We do <b>not</b> track children, monitor their behaviour, build profiles of them or show them advertising.</li>
  <li>Parents and guardians can ask us at any time to see, correct or delete their child's data, or to withdraw consent. Email <a href="mailto:<?= $mail ?>"><?= $mail ?></a> from the parent's email address with the child's username.</li>
  <li>If we learn that a child's account was created without a parent's permission, we may suspend or delete it.</li>
  <li>We recommend that children use their first name or a nickname, a figure instead of a real photo, and a private profile.</li>
</ul>

<h2 id="share">6. Who we share it with</h2>
<p>We share data only with service providers that help us run the app, and only as needed:</p>
<ul>
  <li><b>Hostinger</b>, our web hosting provider, which stores the website, the database and uploaded photos.</li>
  <li><b>Meta (WhatsApp)</b>: if you verify a mobile number, we send one WhatsApp message to it through the WhatsApp Business Cloud API, under <a href="https://www.whatsapp.com/legal/privacy-policy" rel="noopener">WhatsApp's Privacy Policy</a>.</li>
  <li><b>Google</b>: for <i>Sign in with Google</i> (if you use it), <i>reCAPTCHA</i> on the sign-in and sign-up pages to stop bots, and <i>Google Fonts</i>, which loads the app's lettering. When these load, Google receives technical data such as your IP address, under <a href="https://policies.google.com/privacy" rel="noopener">Google's Privacy Policy</a>.</li>
</ul>
<p>We may also disclose information if required by law, a court order or a government authority, or to protect the safety of our users. We do not sell, rent or trade your personal data. Some providers may process data outside India; where they do, we rely on their standard protections and on the DPDP Act's rules on transfers.</p>

<h2 id="cookies">7. Cookies and storage on your device</h2>
<table class="data">
  <tr><th>Name</th><th>Purpose</th><th>How long</th></tr>
  <tr><td><code>CRICSESS</code></td><td>Keeps you signed in while you use the app.</td><td>Until you close the browser</td></tr>
  <tr><td><code>cs_rt</code></td><td>"Remember me": keeps you signed in on your own device.</td><td>90 days, or until you log out</td></tr>
  <tr><td><code>g_csrf_token</code></td><td>Set by Google Sign-In for security (only if you use it).</td><td>Session</td></tr>
  <tr><td>Browser storage</td><td>Remembers your sound and commentary settings, and saves app files so Cric-Scorer opens quickly and works on slow connections.</td><td>Until you clear it</td></tr>
</table>
<p>We do <b>not</b> use advertising or analytics cookies.</p>

<h2 id="keep">8. How long we keep it and how we protect it</h2>
<ul>
  <li>We keep your data for as long as your account exists. When you delete your account, your data is erased from the live system straight away, and from our backups within 30 days.</li>
  <li>Standard server and error logs are kept for one year for security purposes, as India's data protection rules require, and then deleted.</li>
  <li>If an account is unused for 3 years, we may notify you (where we can) and then delete it.</li>
  <li>We protect data with encrypted HTTPS connections, hashed passwords, server-side checks on every action, and by cleaning uploaded photos (we re-save them to remove hidden information such as location). No system is perfectly secure, but if a data breach affects you, we will tell you and the Data Protection Board of India as the law requires.</li>
</ul>

<h2 id="rights">9. Your rights</h2>
<p>Under the DPDP Act you (or your parent or guardian) have the right to:</p>
<ul>
  <li><b>Know and access</b> what personal data we hold about you and how we use it;</li>
  <li><b>Correct or update</b> it. Most details can be changed yourself in <i>My Profile</i>;</li>
  <li><b>Erase</b> it, by deleting your account;</li>
  <li><b>Withdraw consent</b> at any time. Withdrawing consent means closing your account, but it doesn't affect anything done before;</li>
  <li><b>Nominate</b> someone to exercise these rights for you if you are unable to;</li>
  <li><b>Complain</b> to us (see below) and, if you are not satisfied, to the <b>Data Protection Board of India</b>.</li>
</ul>
<p>To use these rights, email <a href="mailto:<?= $mail ?>"><?= $mail ?></a>. We will reply within 7 days and aim to resolve your request within 30 days.</p>

<h2 id="delete">10. Deleting your account</h2>
<p>Open the app → <b>More → My Profile</b> → <b>Delete my account</b>. This permanently erases your account, profile, photo, follows, learning progress, help messages, and the matches, tournaments and teams you created. Gully Duel scores stay in the totals but are no longer linked to you. Teams that other people are using in their own matches are kept for them, but no longer linked to you. If you can't sign in, email us from the address linked to your account.</p>

<h2 id="changes">11. Changes to this policy</h2>
<p>If we change this policy, we will update the date at the top. If a change is important, we will tell you in the app before it applies.</p>

<h2 id="contact">12. Contact and grievances</h2>
<div class="card">
  <p style="margin-top:0"><b><?= $org ?></b><br><?= $addr ?></p>
  <p>Privacy and data requests: <a href="mailto:<?= $mail ?>"><?= $mail ?></a></p>
  <p style="margin-bottom:0">Grievance Officer: <b><?= $officer ?></b> · <a href="mailto:<?= $mail ?>"><?= $mail ?></a></p>
</div>
<?php page_end();

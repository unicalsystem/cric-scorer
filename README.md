# Cric-Scorer - by Aaryush 🏏

A mobile cricket scoring app (installable web app / PWA) built with PHP + MySQL.
Made for Aarush & Aayush.

## What's inside
- **Login & registration** (username + password, stays logged in for 90 days)
- **Teams** - 10 IPL franchises and 12 international teams pre-loaded with full squads (504 players),
  plus your own custom teams. "Pick from teams" copies stars like Kohli or Bumrah into your own team.
- **Match types** - T20, ODI, T10, Box Cricket, Gully Cricket, Backyard (Kids), Custom - or create your own
  (overs, balls per over, players per side, bowler overs limit, last-man-stands). **Test matches** too: pick the
  *Test match* format for two innings a side with no over limit - declare from the match menu, enforce the follow-on
  when the lead is big enough (your own lead, 200 by default), the 4th-innings target is worked out for you, results
  read "won by an innings and 40 runs" / "won by 3 wickets", and time running out ends it as a draw (1 point each in a
  tournament table). Scorecards show "CSK 1st / DC 1st / CSK 2nd" and the match list shows "120/4 & 88/2".
- **Club Leagues** - a sports club registers under *Academies*, opens **Club league rules** and sets its own categories
  (age bands, mixed with a minimum number of women), groups (towers / blocks), squad size, points (win, per 10 runs,
  per wicket) and no-repeat rules. Teams join a club with a group and category, players get gender and birth year,
  the app checks squads and playing sides against the rules, auto fixtures pair same-category teams of different
  groups, and the Points tab shows group standings plus per-category tables. Everything is visible only to club
  members; club tournaments can be hidden from everyone else.
- **Match photos & Timeline** - the scorer, team owners and players in the XI add photos from the ground (toss,
  huddle, that six) with a caption; they are shrunk on the phone and again on the server (max 1280px JPEG, EXIF
  stripped, ~100-300 KB each, 40 a match) and shown on the match's **📷 Timeline** tab alongside the toss, innings
  breaks, fifties, hundreds, partnerships, wicket hauls, scorer notes and the result - each photo stamped with the
  score at that moment. Full-screen viewer, caption edit and delete. Files live in `uploads/matches/`.
- **Live ball-by-ball scoring** - runs, 4s/6s, wides, no-balls, byes, leg-byes, every dismissal (bowled, caught, lbw,
  run out, stumped, hit wicket, obstructing the field, hit the ball twice, retired out, timed out, retired hurt),
  strike rotation, over changes, bowler limits, unlimited undo, **edit or delete any earlier ball** (later balls are
  re-counted), swap a wrongly chosen batter, change overs in the first innings, replace a bowler mid-over, penalty /
  bonus runs, powerplay overs, super over for ties, shot type + wagon-wheel direction, dropped catches and runs
  saved/missed per ball (tap 🎯 *Shot & fielding*, or switch on "ask after every scoring shot"). **Wheel scoring**: a
  tap-to-score wagon wheel (rings • 1 2 3 4 5 6, eight zones) records runs and direction in one tap; 🧤 *Set field* places
  the fielders so commentary and the voice say "cut for 2 runs to point, fielded by Rohit".
- **Scorecards & analysis** - batting/bowling cards with minutes, extras, fall of wickets, ball-by-ball commentary,
  Analysis tab (Manhattan, worm, run rate, wagon wheel per batter, wickets pie, types of runs, all partnerships,
  powerplay, fielding), automatic result with Player of the Match, best batter and best bowler, match officials and a
  live-stream video on the match page, scorecard as an image for WhatsApp.
- **Score overlay for streamers** - `overlay.php?m=ID` (add `&theme=light` for a light bar) is a transparent 1280×140
  browser source for OBS / phone streaming apps; it polls the public match API every 5 s. Free.
- **Player pages** - tabs for Matches (full history with the player's own figures, filters by year, result, tournament,
  team, opponent, ball type and overs), Stats (batting, bowling, fielding and captain cards), Awards, Badges, Teams played
  for and Insights; player level and profile views. Captain and keeper are picked in the playing XI (C / WK).
- **Match page extras** - Summary (best performances, automatic match notes and timings, scorer notes log, match points
  with bonus), MVP (batting + bowling + fielding points - formula in the app under "how?"), Teams (both XIs).
- **Grounds directory** - ground owners list their ground (map, pitch, lights, timings, price, facilities, WhatsApp
  booking number); players browse by city, book on WhatsApp, and pick the ground as the venue of a match (`grounds` table).
- **Looking for board** - posts for an opponent team, players, umpire, scorer, ground or a team to join, filtered by kind
  and city; replies are private to the poster (`lookings`, `looking_replies` tables).
- **Player insights** (free, on every player page) - batting strike rate and bowling economy by phase (powerplay /
  middle / death), how the player gets out, favourite shots, career wagon wheel, fielding, strengths and things to work on.
- **Tournaments** - league, knockout, league + final; auto fixtures, points table with NRR, top scorers.
- **Leaderboards & player stats** - runs, average, strike rate, wickets, economy, best figures; tournament boundary tracker.
- **Rain rules (DLS)** - Duckworth-Lewis-Stern, ICC Standard Edition: reduce overs before or during either innings,
  revised targets, live par score and par-by-over table, "rain stopped play" results, G50 = 200 (junior/club) or 245
  (international). Scorer menu → *Rain / DLS*. The resource table is in `inc/dls.php`.
- **Cricket Academy** (Learn tab) - a full syllabus for kids: 6 modules, 29 lessons, 179 teaching cards with field
  diagrams, 232 quiz questions and a 25-question Final Exam. Lessons first, then a quiz (marked on the server);
  60% unlocks the next lesson, 3 ★ for 90%+. XP and ranks from Gully Rookie to Cricket Legend, module badges,
  family leaderboard and a printable "Certified Cricket Expert" certificate. Content: `assets/academy.json`.
- **Players & privacy** - search and follow other players, see your followers and who you follow, a "Following" match
  filter and Home feed. *Private profile* (My Profile) hides a player from search, profiles and follower lists and stops new
  follows. *Private match* (choose when creating, or Match options) hides it from everyone else's lists, Home, leaderboards
  and stats, and blocks its link; only the scorer - and the admin (parent) account - can open it.
- **Player profiles** - profile photo (taken or picked on the phone, cropped to 320×320 and re-saved on the server) or one
  of 16 cricket figures, a short bio, role, batting & bowling style, years of experience, and an optional link to *your*
  player in one of your teams so your real batting and bowling career stats show on your profile. Photos are stored in
  `uploads/avatars/` (scripts can never run there) - back this folder up along with the database.
- **Live sharing** - share a match link (`/m/123`); WhatsApp and Facebook show the real score in the preview, and anyone
  can watch the score update live (no login needed). Match pages are `noindex`, so players' names never appear in Google.
- **Public website & SEO** - a home page for visitors, free *Learn cricket* pages for all 29 lessons (`/learn/...`),
  titles, descriptions, Open Graph/Twitter share cards, structured data (app, organisation, FAQ, course, lessons),
  `sitemap.xml` and `robots.txt`.
- **Privacy Policy & Terms** (`/privacy`, `/terms`) written for India's DPDP Act, a required consent tick box at sign-up
  (also for new Google sign-ups), and *Delete my account* in My Profile.

- **Scoring permissions** - only the player who created a match (and the admin) can score it; everyone else
  watches. *Match options → Scorer* lets the creator hand scoring to one other player by username and take it back.
- **Team permissions** - a custom team can only be picked for matches and tournaments (or have its players copied)
  by its owner, the admin and players the owner allows. Others tap *Ask to use this team*; the owner allows or declines
  on the team page, can add players by username, or switch the team to *Everyone*. IPL and international teams are open.
- **Gully Duel** - quick 1v1 cricket in the style of Cricket League, played in landscape from behind the batter in six
  stadiums. Batter: touch and hold to lift the bat, let go and flick to hit (short = ground, long = air, tap = block),
  graded by a timing bar. Bowler: pick a bowler each over, a named delivery (outswing, yorker, doosra…), over or round
  the wicket, drag the landing circle and stop the power bar in the blue (red = no-ball + free hit). Three wides or
  three no-balls lose; a 15 s bowling timer with a warning then a loss. A squad of 12 players with stats, unlocked with
  coins won in games (`game_profiles` table). Nets, vs the computer, one phone or two phones online (4-digit code). Rules: `assets/game-engine.js` (phones) and `inc/game.php` (server) -
  check they still match with `node tools/test_game_parity.js | php tools/test_game_parity.php`.
- **APL Auction** (More → APL Auction, Home tile) - Aaryush Premier League, a multiplayer IPL-style player auction with
  virtual coins (no money, ever). The host creates a private room with the rules (purse, squad, XI, overseas limit, timer,
  bid steps, base prices, franchises in the pool, custom players) and shares the 8-character code / invite link; friends
  join on their own phones. Everything is decided on the server: bids are inserted inside a locked transaction (a unique
  key on lot + amount means two simultaneous equal bids can never both win), each lot has a deadline stored in the
  database, and whichever phone polls first after the deadline finalises the sale (purse deducted once, player marked
  sold, next player presented) - so a server restart or a dropped phone cannot lose or duplicate a sale. Browsers poll
  `auc_state` every 2 s (`auction_state()` in `inc/auction.php`) and run a local countdown corrected by the server's
  `secs_left`. Host console: pause/resume, skip, next, withdraw/reopen, +10 s, timer/accelerated mode, end, close, bonus
  points (audited). Results: leaderboard = players × 1 + roles covered × 5 + coins left × 0.2 + bonus (weights set per
  room), squads, every sale, bid history, audit log, playing XI, CSV. Tables: `auc_rooms`, `auc_participants`,
  `auc_players` (per-room copy of the pool, seeded from the app's IPL teams), `auc_bids`, `auc_events`.
- **Admin portal** (More → Admin portal, admin account only) - totals and charts only, never names or emails:
  players joined/active, devices, app versions, matches, games, Academy; **Roll-out** (switch features off, on for
  selected usernames, or on for everyone); **Updates** (one-tap update from GitHub with backup and roll back, and "ask
  everyone to update now"); **Help desk** (player messages by ticket number, reply in the app); **News** (Home-screen
  announcements); **Errors** (error reports by reference code, personal details blanked out, kept one year).

## Releasing a new version
1. Change the code, bump `APP_VERSION` in `inc/version.php` and the cache name in `sw.js`.
2. Add a `## <version> - <date>` section to `CHANGELOG.md` (the admin portal shows it as "what's new").
3. Push to GitHub (`main`). Then in the app: **More → Admin portal → Updates → Check for updates → Install**.
   The server downloads the zip from GitHub, saves a backup to `data/backups/` (newest 3 kept), and copies the new
   files - never `config.php`, `config.local.php`, `install.php` or uploaded photos. Extra lines your host added to
   `.htaccess` are kept. If anything looks wrong, tap **Roll back**.
4. Optional: switch on **Ask everyone to update now** so phones reload to the new version straight away.
The folders must be writable by PHP (normal on Hostinger). If not, the portal says so - upload the zip by hand instead.

## Requirements
- PHP 7.4 or newer (8.x recommended) with `pdo_mysql`
- MySQL 5.7+ or MariaDB 10.3+
- HTTPS recommended (needed for "Install app" on phones)

## Install (cPanel / any shared hosting)
1. **Create a database**: cPanel → *MySQL Databases* → create a database (e.g. `youruser_cric`),
   a user with a strong password, and add the user to the database with **ALL PRIVILEGES**.
2. **Upload** the contents of this folder to your web server, e.g. `public_html/cricket/`.
3. **Copy `config.local.sample.php` to `config.local.php`** and set `DB_NAME`, `DB_USER`, `DB_PASS`
   (on Hostinger these include the `u…_` prefix). Leave `config.php` unchanged - it holds placeholders only.
4. **Open** `https://your-domain/cricket/install.php` in a browser, create the admin account, and click *Install*.
5. **Delete `install.php`** from the server.
6. Open `https://your-domain/cricket/` on the phone → browser menu → **Add to Home screen**.

Alternative to step 4: import `sql/cric_scorer.sql` in phpMyAdmin, create an empty file `install.lock`
in the app folder, then open the app and register - the **first account registered becomes admin**.

## Accounts
- The admin can edit the IPL and international squads (add/remove players when transfers happen).
- Everyone else can create their own teams, tournaments and matches, and view everyone's matches.
- Only the person who created a match can score it.

## Nginx
Apache uses the included `.htaccess` files. On Nginx, block direct access to the `inc/`, `sql/`, `data/`
and `tools/` folders and `config.php`, e.g. `location ~ ^/(inc|sql|data|tools)/ { deny all; }`, and add the
friendly addresses:
```
rewrite ^/privacy/?$ /privacy.php last;         rewrite ^/terms/?$ /terms.php last;
rewrite ^/learn/?$ /learn.php last;             rewrite ^/learn/([a-z0-9-]+)/?$ /learn.php?l=$1 last;
rewrite ^/m/([0-9]+)/?$ /match.php?id=$1 last;  rewrite ^/sitemap\.xml$ /sitemap.php last;
rewrite ^/robots\.txt$ /robots.php last;
```

## Getting found on Google (SEO) & legal pages
1. In `config.local.php` set `SITE_URL` to your real address (default `https://cricscorerapp.com`, no trailing slash)
   and `CONTACT_EMAIL` to a mailbox you read. Company name, address and Grievance Officer are in `config.php`
   (`ORG_NAME`, `ORG_ADDRESS`, `GRIEVANCE_OFFICER`, `POLICY_UPDATED`) and can be overridden in `config.local.php`.
2. Open https://search.google.com/search-console → add your domain → *Sitemaps* → submit `sitemap.xml`.
   Do the same in Bing Webmaster Tools (it can import from Google).
3. Check your share card with https://developers.facebook.com/tools/debug/ and the rich results with
   https://search.google.com/test/rich-results.
4. Friendly addresses (`/privacy`, `/learn/...`, `/m/123`) need Apache `mod_rewrite` (on by default at Hostinger).
5. The Privacy Policy and Terms are a solid starting point, not legal advice. Have a lawyer review them, and update
   `POLICY_UPDATED` whenever you change them.

## Updating squads
Squads are as of October 2026. Edit them in the app (log in as admin), or edit `data/*.json`
and run `python3 tools/build_sql.py` to regenerate `sql/cric_scorer.sql` for a fresh install.

## Files
```
index.php        app shell        api.php       JSON API        install.php   one-time installer
config.php       database config  inc/          scoring engine  assets/       app JS, CSS, icons
privacy.php      /privacy         terms.php     /terms          learn.php     /learn pages
match.php        /m/ID share page sitemap.php   /sitemap.xml    robots.php    /robots.txt
manifest.json    PWA manifest     sw.js         offline cache   sql/          schema + seed data
```

## Sign in with Google & reCAPTCHA (optional)
All keys go in `config.local.php` (see `config.local.sample.php`). Leave a key empty to keep that feature off.
- **Google:** Google Cloud Console → APIs & Services → Credentials → *OAuth client ID* → *Web application* →
  Authorised JavaScript origins `https://cricscorerapp.com` (plus any other domain you use) → put the Client ID in
  `GOOGLE_CLIENT_ID`. No client secret is needed. Players can also link Google later in *More → My Profile*.
- **reCAPTCHA:** https://www.google.com/recaptcha/admin/create → v2 "I'm not a robot" Checkbox (or v3) → add your
  domains → put the keys in `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` and set `RECAPTCHA_VERSION` to match.
- The server checks every Google sign-in and every captcha with Google, so it needs outbound HTTPS (normal on Hostinger).
- **Automated testers** (TestPilot, Playwright runners…) that cannot reach Google: put the runner's IP (or prefix) in
  `RECAPTCHA_BYPASS_IPS` and that IP skips reCAPTCHA; the login page then shows no captcha at all. If the captcha cannot
  load for a real player, the form shows a clear error with a Retry link after 10 s instead of waiting forever.

## WhatsApp verification (Meta Cloud API) - optional
Players can add a mobile number (one account per number) and verify it on WhatsApp; the same message signs them in
with one tap, and *Sign in with WhatsApp* on the login page works without a password. Players who don't verify can
still use everything. Set-up, all in `config.local.php`:
1. **Meta for Developers** → your app → **WhatsApp → API Setup**: copy the **Phone number ID** into `WHATSAPP_PHONE_ID`.
2. **Meta Business Suite → Business settings → System users** → create a system user, *Add assets* (your WhatsApp
   account), **Generate token** with `whatsapp_business_messaging` and `whatsapp_business_management`, never expires.
   Put it in `WHATSAPP_TOKEN`.
3. **WhatsApp Manager → Message templates → Create**: name `cricscorer_verify`, category **Utility**, language
   English. Body: `Your Cric-Scorer verification code is {{1}}. Tap the button to verify and sign in - or type the
   code in the app. The link works for 15 minutes.` Add a **Call to action → Visit website** button with a
   **dynamic** URL: `https://cricscorerapp.com/v/{{1}}` (button text "Verify"). Submit it for approval (usually
   minutes to a few hours). The name goes in `WHATSAPP_TEMPLATE`; use `WHATSAPP_TEMPLATE_LANG` if not `en`.
   **Already have an Authentication template** (Meta's OTP type with a *Copy code* or *Autofill* button)? Set
   `WHATSAPP_TEMPLATE` to its name and `WHATSAPP_TEMPLATE_KIND` to `'code'`. Players then copy the code from WhatsApp
   and type it in the app - the one-tap link needs the Utility template above, because Authentication templates don't
   allow a website button.
4. While the app is in development mode on Meta, only numbers added under *API Setup → To* receive messages; go
   live (business verification) to message anyone. Numbers without a country code are treated as Indian
   (`DEFAULT_COUNTRY_CODE`).
Until the keys are set, the Verify button tells players WhatsApp is not switched on; the admin still gets the test
link on screen. Links are single-use, expire after 15 minutes, and a number gets at most 3 messages in 10 minutes.
`/v/TOKEN` is served by `verify.php` (mod_rewrite rule included).

## Cricket academies
*More → Cricket academies*: anyone can register an academy (name, address, city, phone, coach, about; up to 3 per
account). Registering creates a team with the academy's name. The owner adds students by **username or mobile
number** (players with accounts, who are then allowed to use the academy team) or by **name** (added to the squad
only). Students can leave; profiles show "Trains at".

## Keeping your password out of Git
Real database settings live in `config.local.php`, which is listed in `.gitignore` and blocked from the web
by `.htaccess`. `config.php` (in Git) only has placeholders and loads `config.local.php` when it exists, so a
`git pull` or zip upload never overwrites your server's password.

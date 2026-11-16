# RV Tennis

Point-by-point tennis scoring for RV Sports Club, served at https://rvtennis.cricscorerapp.com
(the `rvtennis/` folder of the Cric-Scorer site).

- **Scoring** - tap the winner's half of the court. Singles and doubles; best of 1/3/5, short sets, pro set,
  7-point tiebreaks, 10-point match tiebreak, no-ad. Server and service court shown, ace / double fault, undo,
  retirement and walkover, or type in a finished score.
- **Rules that vary are settings** (per match, per tournament, and per knockout round - e.g. short no-ad sets in early
  rounds, best of 3 from the semi-finals): sets in the match (1/3/5), games per set (6/4/8), advantage or no-ad, set
  tiebreak (7 or 10 points at games-all, or none), deciding set (full set or a 10/7-point match tiebreak). Defaults are
  standard tennis. On the scoring screen: 1st/2nd serve with Fault and Let, optional point tags (winner, forced /
  unforced error), doubles serving order and receiving courts chosen at the start of each set, the receiver's court
  choice on a no-ad deciding point, and Retired / Walkover / Default.
- **Following** - anyone can open a match link (`/m/12`) and watch the score update; stats and game-by-game view.
- **Tournaments** (club admins) - round robin (1-8 groups, with tables) and seeded knockout draws with byes;
  winners move on automatically.
- **Players** - added as their names are typed; win/loss records.

## How it is set up
- No install step. The app reads the database and sign-in settings from Cric-Scorer's `config.local.php` one folder
  up, and creates its own tables (all named `tn_...`) the first time it is opened. To give tennis different
  settings, add `rvtennis/config.local.php` - anything defined there wins.
- **Accounts are shared with Cric-Scorer** (`users`, `user_tokens`, `otp_tokens`): Sign in with Google, a WhatsApp
  code, or a Cric-Scorer username and password.
  - Google: tennis uses its own OAuth client (`TN_GOOGLE_CLIENT_ID` in `config.php`), whose *Authorised JavaScript
    origin* is `https://rvtennis.cricscorerapp.com`. Cric-Scorer's `GOOGLE_CLIENT_ID` is not used here.
  - WhatsApp: uses the same Meta template as Cric-Scorer; members type the 6-digit code.
- **Club admins** are the accounts whose verified Google e-mail or verified WhatsApp number matches
  `TN_ADMIN_HASHES` in `config.php` (SHA-256, so the values are not readable here), or `TN_ADMIN_EMAILS` /
  `TN_ADMIN_MOBILES` in `config.local.php`. Admins can add more admins in the app (Me → Club admins).
- **Club logo**: `assets/logo.png` (header, home banner, sign-in, opening screen); the app icons `assets/icon-*.png` and
  the share image `assets/og.jpg` are made from it. `sw.js` + `manifest.json` make the site installable as an app.

## Code
`inc/engine.php` scoring rules (a match is a format + a list of points, replayed on every change) ·
`inc/tour.php` matches, draws, tables · `inc/db.php` database and sign-in · `api.php` · `assets/app.js` the phone app.
Checks: `php tools/test_engine.php`.

# RV Badminton

Rally-by-rally badminton scoring for RV Sports Club, served at https://rvbadminton.cricscorerapp.com
(the `rvbadminton/` folder of the Cric-Scorer site). Built the same way as `rvtennis/`.

- **Scoring** (BWF rally-point system, as in the club's *Badminton Tournament Rules & Scoring Specification*):
  tap the side of the court that won the rally. A point on every rally; 21 to win with a 2-point lead from 20-all;
  sudden death at 29-all (30-29); the winner of a game serves first in the next. Server and service court shown for
  every rally (right on an even score, left on an odd). Doubles: each pair picks who starts in the right court at the
  start of every game; the server switches courts when their side scores, nobody moves when the serve changes hands,
  one service turn per side. Service fault, Let, Undo; optional rally tags (winner, unforced error, out / net).
  Interval at 11 (60 seconds); change of ends after each game and at 11 in the deciding game (the only game of a
  one-game league match); 2-minute break between games. Retired / Walkover / Disqualified, or type in a finished score
  (impossible scores such as 23-20 are refused). Score read aloud on request.
- **Rules that vary are settings** - per match, per tournament, and for the play-off / final rounds: games in the match
  (1/3/5), points to win (21/15/11/25/30), setting (win by 2 with a cap, win by 2 without a cap, or first to the
  points), the cap (default 30), and the score that brings the interval (default 11). Defaults are the rule sheet:
  `pointsToWin = 21, winByTwo, maxPointsCap = 30, leagueSets = 1 (ends at 11), knockoutBestOf = 3`.
- **Tournaments** (club admins):
  - *League + play-offs* (default): league groups play single games, then the top 2/4/8 play off best of 3 (semi-finals
    and final for the top 4). The play-offs are drawn automatically when the last league match ends (an admin can
    also draw them early, or redraw before any play-off match starts). With two groups the semi-finals cross over (A1 v B2, B1 v A2).
  - *League* only, or *Knockout* (seeded, byes to the top seeds; the last 1-3 rounds may use their own format).
  - League tables: match points (2 for a win, 0 for a loss - both settable), then net points difference (points scored
    minus conceded in all league games), then head-to-head between the sides still level.
- **Teams** Phoenix, Rangers, Titans, Warriors with their logos; **players** with win/loss, games and points records.

## How it is set up
- No install step. The app reads the database and sign-in settings from Cric-Scorer's `config.local.php` one folder
  up and creates its own tables (all named `bd_...`) the first time it is opened. `rvbadminton/config.local.php`, if
  present, wins over those settings.
- **Accounts are shared with Cric-Scorer and RV Tennis** (`users`, `user_tokens`, `otp_tokens`): Google, WhatsApp code,
  or a Cric-Scorer username and password.
  - Google: uses the RV Tennis OAuth client (`BD_GOOGLE_CLIENT_ID` in `config.php`). Add
    `https://rvbadminton.cricscorerapp.com` to that client's *Authorised JavaScript origins* in Google Cloud Console.
- **Club admins**: shiva2k2@gmail.com (verified Google sign-in) and 77999 99996 (verified WhatsApp sign-in), stored as
  SHA-256 hashes in `BD_ADMIN_HASHES`; more can be added in the app (Me → Club admins) or with `BD_ADMIN_EMAILS` /
  `BD_ADMIN_MOBILES` in `config.local.php`.

## Code
`inc/engine.php` scoring rules (a match is a format + a list of rallies, replayed on every change) ·
`inc/tour.php` matches, draws, play-offs, tables · `inc/db.php` database and sign-in · `api.php` · `assets/app.js` the phone app.
Checks: `php tools/test_engine.php`.

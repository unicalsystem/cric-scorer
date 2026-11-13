# Cric-Scorer changelog

The admin portal (More → Admin portal → Updates) reads this file from GitHub to show "what's new"
before installing. Add a `## <version> - <date>` section with `-` bullet points for every release,
and bump `APP_VERSION` in `inc/version.php`.

## 1.28.1 - 14 November 2026
- Cricket news is kid-safe: headlines about deaths, crime, abuse, drugs or betting are left out

## 1.28.0 - 14 November 2026
- Home: new **🏆 Upcoming tournaments** strip - swipe through tournaments that haven't finished (teams, matches, dates, venue, status); tap one to open it
- Home: new **📰 Cricket news** - the 4 latest real-world cricket headlines (ESPNcricinfo + Google News, no API key) with source and time; tap to read on the publisher's site, **More** opens a full news page (`#/news`). The server refreshes the headlines at most every 20 minutes and keeps the last good copy if a feed is down
- Home: APL Auction and Gully Duel moved down into a **🎮 Games** section above Explore

## 1.27.0 - 13 November 2026
- Playoffs: the tournament form's **League + playoffs** format (now the default) lets you choose the playoff format - **IPL style** (Qualifier 1: 1st v 2nd, Eliminator: 3rd v 4th, Qualifier 2: loser of Q1 v winner of the Eliminator, Final - the default), **Semi-finals** (1v4, 2v3) + Final, or **Final only** - with their own overs and max overs per bowler for the qualifiers / semis and for the final (blank = same as the league)
- After the league, tap **Create playoffs** on the Matches tab (it warns if league games are still unfinished). Winners move on automatically; a playoff that ends level (super over tied too, or no result) sends the higher-placed team through. Club tournaments run separate playoffs per category
- Playoff matches no longer count in points tables
- League table order: points, then net run rate, then (club leagues) wickets taken. Club points tables now show NRR
- Bowling quota: "max N overs a bowler, but up to X bowlers may bowl Y overs" (e.g. a 6-over game: 1 over each, 2 bowlers may bowl 2) on match types and the New Match form; the bowler picker and the server both enforce it
- A tie still gives 1 point each when there's no super over or the super over is tied too; a super-over winner gets the win
- New club rule sheets start with Junior (19 & under), Open (20+), Mixed (at least 1 woman) and win 2 / tie 1 / no result 1

## 1.26.0 - 13 November 2026
- Team owner hand-over (administrator only): on a custom team's page the admin sees "👑 Owner: …" with a **Change owner** button - search a player by name, username or full mobile number and hand the team over. The new owner can edit the team, its squad and who may use it. The old owner keeps using the team by default (untick "Let … keep using this team" to remove them)

## 1.25.0 - 11 November 2026
- Import players: on a team page tap **📥 Import** and paste a list from WhatsApp or Excel (one player per line - numbering, bullets, "Name, bowler, 98765 43210", "Suresh (wk)", "#7" jersey numbers, tabs / CSV columns all understood; M/F and birth year too for club teams) or choose a CSV / text file. A preview shows what was understood; players already in the team are skipped, WhatsApp numbers link the player to their account, club squad limits are respected. Up to 60 at a time
- Team page buttons are now Add · Pick · Import

## 1.24.0 - 9 November 2026
- Bouncers: a ⬆️ **Bouncer** button on the scoring pad marks the next ball as a bouncer (then tap the runs / extra / OUT as usual). The pad shows "↑ 1/1 this over · next = no-ball"; once the per-over limit is used up the next bouncer is recorded as a **no-ball** automatically (+1 run, ball re-bowled, batter cannot be bowled / caught / lbw). Limit is set per match / match type ("Bouncers allowed per over": default 1, 0 = no limit). Bouncers show a ↑ mark on the ball chips and in the commentary
- Hat-tricks: after two wickets in two balls the scoring screen shows "🎩 <bowler> is on a HAT-TRICK!" and the commentary says so; the third wicket in three consecutive balls pops **HAT-TRICK!** with confetti (4 in 4 too). Counted across overs - the bowler's next over continues the sequence; wides / no-balls in between neither count nor break it; run outs break it. Bowlers with a hat-trick get a 🎩 on the scorecard

## 1.23.1 - 9 November 2026
- Fix: the match screen crashed with "Cannot access 'bt' before initialization" at the innings break (before the 2nd innings openers are picked), so the match could not be continued

## 1.23.0 - 9 November 2026
- Quick Toss: a stand-alone coin toss at `#/toss` - type the two team names, pick who calls and what they call, flip a fair coin (crypto random) with a big spinning coin and sound, record bat / bowl, then share the result on WhatsApp. No match, team set-up or even an account needed; team names and the last 10 tosses are remembered on the phone and it works offline
- Reach it from the Home tile, More → Quick Toss, the landing / login pages, and jump straight to "Score this match" afterwards

## 1.22.1 - 6 November 2026
- Fix: registering an academy / club failed on MariaDB 11 hosts with "Illegal mix of collations" (the duplicate-name check wrapped the value in COALESCE)

## 1.22.0 - 6 November 2026
- Club Leagues: a sports club (registered under More → Academies) can switch on its own **League rules** - player categories by age / gender (e.g. Under 20, 20-50, Mixed with at least 3 women playing), groups that field the teams (towers, blocks…), squad size (e.g. 8 playing + 2 extra), a points scheme (win 50, 2 pts per 10 runs by a batter, 5 pts per wicket - all editable) and "no repeating players" between categories within a group
- Only members of that club see any of it: the rule sheet is for the club owner, club fields appear on the team / player / tournament forms only for members, and a club tournament can be "members only" so outsiders cannot even see it in the Cups list
- Teams get a club, group and category; players get gender and birth year (shown only on club teams). The team page checks the squad against the rules (size, age, same player in two teams) and the playing-side picker enforces them (e.g. "needs at least 3 women in the playing 8")
- Club tournaments: auto fixtures pair teams of the same category from different groups only; matches use the club's playing number; the Points tab shows group standings (all of a group's teams added up) and per-category tables with match / run / wicket points
- Players, scoring, ordinary tournaments and all other features are untouched

## 1.21.0 - 6 November 2026
- Match photos: a new **📷 Timeline** tab on every match (like CricHeroes) where players add photos from the ground - the toss, the huddle, that big six - with an optional caption
- Who can add: the scorer/creator, either team's owner, or any signed-in player in the playing XI (up to 40 photos a match); the uploader or the scorer can caption or remove a photo
- Photos are shrunk on the phone before upload (max 1600px) and re-drawn on the server (max 1280px JPEG, lower quality when still large, 420px thumbnails), EXIF rotation honoured and location data stripped - a 5 MB phone photo ends up around 100-300 KB
- The Timeline mixes photos with the match's moments: toss, innings start/break, fifties, hundreds, 50 partnerships, 3/5-wicket hauls, scorer notes and the result, newest first, each photo stamped with the score at that moment
- Full-screen viewer with swipe/arrow keys, caption editing and delete; photos also show on the pre-match page before the first ball
- Photos are deleted with the match; uploads are served with long browser caching and never cached by the service worker

## 1.20.0 - 4 November 2026
- Test matches: a new *Format* picker on the New Match form (Limited overs / Test match) with days and follow-on lead; no over limit and no bowler limit
- Two innings a side: declare from the match menu ("Declare this innings"), follow-on switch on the innings-break screen when the lead is big enough, 4th-innings target worked out automatically, team-innings chips (CSK 1st / DC 1st / CSK 2nd) on the scorecard and "120/4 & 88/2" on match cards
- Results: "won by an innings and N runs", "won by N wickets/runs", tied, or "End match as a draw" from the menu when time is up (undo re-opens it); draws count like ties in points tables
- The scoreboard shows the lead/trail, "following on", declared totals (120/4d) and both teams' earlier innings; no DLS, super over or powerplay options on a Test
- Stats: innings are now flagged `is_super` instead of "innings 3+ = super over", so 3rd/4th Test innings count in careers, MVP, leaderboards and match notes; penalty runs are tied to the innings they were given in
- Faster: no DLS table is computed for Test matches
- Score overlay hides the over limit on a Test
- Schema: `matches.format/days/follow_on`, `innings.is_super/follow_on/declared`, `penalties.innings_id` (auto-upgraded)

## 1.19.2 - 4 November 2026
- Team captain: the team owner (and admin) can pick or change the captain straight from the team page - "Pick captain" / "Change captain" lists the squad; the Edit Team form offers the squad as a drop-down too

## 1.19.1 - 26 October 2026
- APL Auction audio: gavel, bid chime, countdown ticks, crowd on a sale, and an auctioneer voice that introduces every player, calls each bid, says "going once… going twice…" and "Sold!" - with its own 🔊 switches on the room page (independent of the scoring sounds) and a Test button

## 1.19.0 - 26 October 2026
- New game: APL Auction (Aaryush Premier League) - a multiplayer IPL-style player auction with virtual coins. Create a private room, share the 8-character code or invite link, everyone joins on their own phone, and the host starts
- Live bidding screen: player card, countdown ring, big Bid button and "jump to" steps, highest bidder, bids on the player, coming-up list, team purses - all decided on the server (bids, timer, sales) and synced every 2 seconds; reconnecting shows the true state
- Host console: start, pause/resume, skip, bring a player forward, withdraw/reopen, +10 s, timer & accelerated mode, end, close; add your own players (friends, fictional stars); remove a team before the start
- Rules per room: purse, max teams, squad size, playing XI, overseas limit, per-role cap, timer, bid steps with bigger steps at higher prices, base price, unsold players come back once, host bids or only auctions
- My team dashboard (coins, spent, slots, Indian/overseas, role balance, player cards), results (highlights, transparent leaderboard = players + roles covered + coins left + host bonus, squads, every sale, unsold, bid history, audit log), playing XI picker, CSV export, print
- Player gallery of all IPL squads with filters; Auction rules and How to play pages; animations and sounds can be switched off
- Admin portal → Roll-out can switch the auction off, on for some players, or everyone (on for everyone by default)

## 1.18.4 - 25 October 2026
- Exactly one <h1> on every screen (splash title is no longer a heading; the visitor home page is taken out of the page while the app runs) - better for accessibility and automated tests
- data-testid hooks: app-title (login/sign-up), page-title (every app screen), splash-title

## 1.18.3 - 25 October 2026
- Login / sign-up no longer wait forever when Google reCAPTCHA cannot load (blocked network, VPN, ad-blocker): after 10 s a clear message appears with a Retry link
- New `RECAPTCHA_BYPASS_IPS` setting: IPs of automated testers skip the captcha entirely

## 1.18.2 - 25 October 2026
- Login button now says "Log in" (was "Let's play 🏏")

## 1.18.1 - 25 October 2026
- Sign-up: one password field with a show/hide (👁) button instead of "confirm password" - easier on phones and works with automated testers that expect a single password input

## 1.18.0 - 25 October 2026
- Grounds directory: ground owners and academies list their ground (address, map link, pitch type, floodlights, size, timings, price, facilities, contact person and a WhatsApp number) - players find grounds by city, open the map, tap "WhatsApp to book", start a match there or post a "looking for" for it; ground pages show matches scored there and views
- Pick a listed ground as the venue when creating a match; the match page links to the ground
- "Looking for" board: post for an opponent team, players, umpire, scorer, ground or a team to join - with city, area, date, overs, ball type, your team and an optional contact; filter by kind and city, search, share on WhatsApp
- Replies stay inside the app: only the poster sees them (with the replier's profile and contact); mark a post as sorted, reopen, edit or delete; up to 10 open posts and 10 grounds per account
- Home tiles and More-menu entries for Looking for and Grounds

## 1.17.0 - 25 October 2026
- Player page rebuilt with tabs: Matches (full history with your runs/wickets per match, won/lost, load more), Stats, Awards, Badges, Teams, Insights
- Filters on Matches and Stats: year, result, tournament, playing for, against, ball type, overs
- Full stats cards: batting adds ducks and won/lost; bowling adds maidens, 3/5-wicket hauls, strike rate, wides, no-balls, dot balls, 4s/6s conceded; new fielding card (catches, caught behind, run outs, stumpings, runs saved/given, drops) and captain card (matches, won, lost, win %, tosses)
- Awards: every Player of the Match, best batter and best bowler with the figures that earned it; Badges: fifty, century, quick fire, six machine, 50 stand, 3-wicket haul, five-for, hat-trick, maiden, safe hands, duck
- Teams played for (since, played, won, lost); player level (Rookie → Aspirant → Regular → Star → Legend) and profile views
- Captain and wicket-keeper picked in the playing XI (C / WK) - used for captain stats and caught-behind
- Match page: Summary tab (best performances, auto match notes - team 50s, batter 50/100s, 50 partnerships, bowler hauls, innings start/end and break timings - scorer notes log, match points), MVP tab (batting + bowling + fielding points, published formula), Teams tab (both XIs)
- Tournament Stats tab shows an MVP leaderboard; bonus points per match add to the points table
- Ball type (leather / tennis / rubber) and round name on a match (new-match form or Officials sheet)

## 1.16.1 - 25 October 2026
- Wheel scoring: a tap-to-score wagon wheel above the pad - rings for • 1 2 3 4 5 6, eight zones; tap where the ball went and the runs and direction are recorded in one go (🎯 Hide wheel to switch it off)
- Commentary and the voice now say where it went: "cut for 2 runs to point, fielded by Rohit", "FOUR! driven through cover", "SIX! over long on"
- 🧤 Set field: place the fielders on the wheel (zone → player) so commentary names who fielded the ball; fielders show on the wheel while scoring

## 1.16.0 - 25 October 2026
- Edit or delete any earlier ball (Overs / Ball by ball tab) - everything after it is re-counted with the same batters and bowlers; works after the match too
- New dismissals: retired out, timed out, obstructing the field, hit the ball twice (plus retired hurt, which stays not out)
- Wrong batter? Swap a batter who has not faced a ball yet; change the overs during the first innings; replace a bowler mid-over
- Penalty / bonus runs for either side with a reason; powerplay overs (set when creating the match or any time) shown on the scoreboard
- Shot & fielding on every ball: shot type, wagon-wheel direction, dropped catch, runs saved or given away - tap 🎯 or switch on "ask after every scoring shot"
- New Analysis tab: Manhattan, worm, run rate, wagon wheel per batter, wickets pie, types of runs, all partnerships, powerplay and fielding
- Super over for tied matches (one over, two wickets; the side that batted second bats first)
- Result card shows Player of the Match, best batter and best bowler
- Match officials (umpires, third umpire, referee, scorer, commentator) and a live-stream link - YouTube plays right on the match page
- Download the scorecard as an image to share on WhatsApp
- Free score overlay for streamers: overlay.php?m=ID as a browser source in OBS or any streaming app
- Free player insights on every profile: batting and bowling by phase (powerplay / middle / death), how they get out, favourite shots, career wagon wheel, fielding, strengths and things to work on
- Tournament stats show a boundary tracker (fours, sixes, run rate, dot-ball %)
- Minutes batted on the scorecard; super-over runs are kept out of career stats and NRR

## 1.15.1 - 25 October 2026
- More → Invite friends: four ready-made messages (fun, for my team, for parents, short) to share on WhatsApp or anywhere, or copy

## 1.15.0 - 25 October 2026
- Players in a team can have a WhatsApp number; when that person later joins or verifies with the same number, their account is linked automatically and all matches scored under that name become their stats
- Add players: the search now also finds players who use the app (by name, username or number), including brand-new accounts in no team yet, so anyone can add them to a team

## 1.14.5 - 25 October 2026
- Admin portal: Players tab - every player's name, username and ID, joined/last-seen dates, verified tick, with search and paging

## 1.14.4 - 25 October 2026
- Find players by their full WhatsApp number (exact number only; numbers are never shown in results; private profiles stay hidden)
- Clearer message when a number is already linked to another account - one account per number

## 1.14.3 - 25 October 2026
- WhatsApp: support Meta "Authentication" templates (Copy code / Autofill) with WHATSAPP_TEMPLATE_KIND = 'code'; Meta's full error reason is shown to the admin

## 1.14.0 - 24 October 2026
- Mobile number on your profile (optional, one account per number) with WhatsApp verification through the Meta Cloud API
- One-tap WhatsApp link: tapping it verifies the number and signs you in - no code to type (a 6-digit code works as a fallback)
- Sign in or join with WhatsApp instead of a password
- Cricket academies: register with name and address, add students by username, mobile number or name; each academy gets its own team that students can play with
- Verified players show a tick; profiles show the academies they train at

## 1.13.0 - 24 October 2026
- Only the player who created a match (and the admin) can score it - everyone else just watches
- The creator can name one other player as scorer by username (Match options → Scorer) and take it back any time
- The named scorer sees the match under My matches; only the creator can delete it, make it private or change the scorer

## 1.12.0 - 24 October 2026
- Gully Duel plays like Cricket League: quick 2-over 1v1 matches in six stadiums (Mumbai, Dubai, Melbourne, London, Johannesburg, Dhaka)
- Batting: touch and hold to lift the bat as the bowler runs in, let go and flick to hit; timing bar on the left
- Bowling: choose a bowler each over, named deliveries (outswing, inswing, yorker, bouncer, slower ball, off-break, leg-break, doosra, flipper, arm ball), over or round the wicket, power bar
- Three wides or three no-balls lose the game; a 15-second bowling timer - time out twice and you lose
- Squad of 12 players with power, timing, pace, accuracy and swing stats; coins for every game unlock new players
- Fielders chase and dive, crowds, floodlights and camera shake on sixes

## 1.11.0 - 24 October 2026
- Custom teams are now protected: only the owner (and players they allow) can pick a team for matches and tournaments, or copy its players
- Other players tap "Ask to use this team"; the owner sees the request on the team page and taps Allow or Decline
- Owners can add players by username, remove them again, or open the team to everyone
- Locked teams show a 🔒 in the match and tournament pickers; IPL and international teams stay open to all

## 1.10.0 - 24 October 2026
- Gully Duel rebuilt in the style of Cricket League: the camera is behind the batter and the ball comes towards you
- Batting: swipe the way you want to hit as the ball arrives - short swipe along the ground, long swipe in the air, tap to block
- Timing bar shows perfect (blue), good, ok, early and late - wider timing window, so runs are much easier to score
- Bowling: choose Fast, Swing or Spin (In / Straight / Out), drag the circle to where the ball should land, stop the power bar in the blue
- Real fielders with a mini-map of the gaps, catches, boundary catches, no-balls and free hits
- New Practice nets mode to learn batting

## 1.9.0 - 24 October 2026
- New game: Gully Duel - one player bowls, the other bats, on two phones (4-digit code), one phone held sideways, or against the computer
- Admin portal with usage stats: players joined and active, matches, games, Academy - totals only, no personal details
- Roll out new features to selected players or everyone from the admin portal
- One-tap updates from GitHub with automatic backup and roll back, plus "ask everyone to update now"
- Help & feedback: players send messages and see replies in the app
- Announcements on the Home screen
- Error reports with reference codes (personal details blanked out)

## 1.8.0 - 24 October 2026
- Home page for visitors, free Learn cricket pages, sitemap and search-engine data
- Shareable match pages with the score in WhatsApp previews
- Privacy Policy and Terms of Use, consent at sign-up, Delete my account

## 1.7.1 - 23 October 2026
- Find players works on every MySQL version; clearer error references

## 1.7.0 - 23 October 2026
- Player profiles: photo or figure, bio, cricket profile and linked career stats

## 1.6.0 - 22 October 2026
- Follow players, private profiles and private matches

## 1.5.0 - 22 October 2026
- Sign in with Google and Google reCAPTCHA

## 1.4.0 - 21 October 2026
- Cricket Academy: lessons, quizzes, XP, ranks and certificate

## 1.3.0 - 21 October 2026
- DLS method for rain-affected matches

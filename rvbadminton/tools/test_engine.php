<?php
/* RV Badminton - engine checks against the club rule sheet. Run: php tools/test_engine.php */
require __DIR__ . '/../inc/engine.php';
$fails = 0; $n = 0;
function ok($cond, string $what) { global $fails, $n; $n++; if (!$cond) { $fails++; echo "FAIL: $what\n"; } }
/** Play a string of rally winners: 'a' / 'b' */
function play(BdMatch $m, string $seq): BdMatch { foreach (str_split($seq) as $c) $m->apply(['w' => $c === 'a' ? 0 : 1]); return $m; }

// default format = the rule sheet: pointsToWin 21, winByTwo, cap 30, end change / interval at 11
$f = bd_format('bo1');
ok($f['pts'] === 21 && $f['two'] && $f['cap'] === 30 && $f['mid'] === 11 && $f['best'] === 1, 'rule-sheet defaults');
ok(bd_format('bo3')['best'] === 3, 'knockout best of 3');

// singles service courts: even = right, odd = left; server keeps serve on winning, loses it on losing
$m = new BdMatch($f); $s = $m->state();
ok($s['server'] === [0, 0] && $s['court'] === 'right' && $s['call'] === 'love all, play', 'A serves from the right at 0-0');
play($m, 'a'); $s = $m->state(); ok($s['server'][0] === 0 && $s['court'] === 'left' && !$s['srvOver'], 'A wins: serves again from the left');
play($m, 'b'); $s = $m->state(); ok($s['server'][0] === 1 && $s['court'] === 'left' && $s['srvOver'] && $s['call'] === 'Service over, 1 all', 'B wins the rally: B serves from the left (score 1): ' . $s['call']);
play($m, 'b'); $s = $m->state(); ok($s['court'] === 'right' && $s['call'] === '2 1', 'B on 2 serves from the right');

// interval and end change at 11 in a one-game match
$m = new BdMatch($f); play($m, str_repeat('a', 10)); ok(!$m->state()['interval'], 'no interval at 10');
play($m, 'a'); $s = $m->state(); ok($s['interval'] && $s['ends'] && str_ends_with($s['call'], 'interval'), 'interval + change ends when the leader reaches 11 (one-game match)');
play($m, 'b'); ok(!$m->state()['interval'] && !$m->state()['ends'], 'only once');
play($m, 'bbbbbbbbbbb'); ok(!$m->state()['interval'], 'trailing side reaching 11 brings no second interval');

// deuce: 20-all plays on to a 2-point lead
$m = new BdMatch($f); play($m, str_repeat('ab', 20)); $s = $m->state();
ok($s['pts'] === [20, 20] && $s['setting'] && $s['flag'] === null, '20-all, setting');
play($m, 'a'); $s = $m->state(); ok($s['flag']['k'] === 'mp' && $s['flag']['side'] === 0 && $s['call'] === 'Service over, 21 match point 20', 'one-game match: game point is match point: ' . $s['call']);
play($m, 'b'); ok(!$m->over, '21-21 continues');
play($m, 'aa'); $s = $m->state(); ok($s['over'] && $s['winner'] === 0 && bd_summary($s['games']) === '23-21', 'won 23-21');

// cap: 29-all, the 30th point wins
$m = new BdMatch($f); play($m, str_repeat('ab', 29)); $s = $m->state();
ok($s['pts'] === [29, 29] && $s['flag']['k'] === 'both', '29-all: next rally wins for either side');
play($m, 'b'); $s = $m->state(); ok($s['over'] && $s['winner'] === 1 && bd_summary($s['games']) === '29-30', '30-29 sudden death: ' . bd_summary($s['games']));

// best of 3: winner of a game serves first in the next; ends change after games 1 and 2 and at 11 in game 3
$m = new BdMatch(bd_format('bo3'), false, 0);
play($m, str_repeat('b', 21)); $s = $m->state();
ok($s['games'][0] === ['a' => 0, 'b' => 21] && $s['server'] === [1, 0] && $s['brk'] && $s['ends'] && $s['flag'] === null, 'game 1 to B; B serves game 2; break + ends');
ok($s['call'] === 'Second game, love all, play', 'second game call');
play($m, str_repeat('a', 11)); ok($m->state()['interval'] && !$m->state()['ends'], 'game 2: interval at 11, no end change');
play($m, str_repeat('a', 9)); $s = $m->state(); ok($s['flag']['k'] === 'gp' && $s['flag']['side'] === 0, 'game point (not match point) in game 2');
play($m, 'a'); $s = $m->state(); ok($s['won'] === [1, 1] && $s['server'][0] === 0 && $s['ends'] && $s['deciding'] && $s['call'] === 'Final game, love all, play', 'game 2 to A; final game');
play($m, str_repeat('b', 11)); $s = $m->state(); ok($s['interval'] && $s['ends'], 'deciding game: change ends at 11');
play($m, str_repeat('b', 9)); $s = $m->state(); ok($s['flag']['k'] === 'mp' && $s['flag']['side'] === 1, 'match point B 20-0');
play($m, 'b'); $s = $m->state(); ok($s['over'] && $s['winner'] === 1 && bd_summary($s['games']) === '0-21 21-0 0-21', 'B wins in three');
ok($s['stats'][0]['pts'] + $s['stats'][1]['pts'] === $s['n'], 'points add up');

// doubles service rotation (rule sheet section 4)
$m = new BdMatch(bd_format('bo1'), true, 0);
$m->apply(['t' => 'ord', 'r' => [1, 0]]);             // A2 in A's right court, B1 in B's right court
$s = $m->state(); ok($s['server'] === [0, 1] && $s['receiver'] === [1, 0], '0-0: A2 serves from the right to B1');
play($m, 'a'); $s = $m->state(); ok($s['server'] === [0, 1] && $s['court'] === 'left' && $s['receiver'] === [1, 1], 'A wins: same server switches to the left, B2 receives (B did not move)');
play($m, 'a'); $s = $m->state(); ok($s['server'] === [0, 1] && $s['court'] === 'right' && $s['receiver'] === [1, 0], 'A wins again: A2 back to the right');
play($m, 'b'); $s = $m->state(); ok($s['server'] === [1, 1] && $s['court'] === 'left' && $s['receiver'] === [0, 0], 'B wins (B on 1, odd): nobody moves, B2 in the left court serves to A1');
$m->apply(['t' => 'ord', 'r' => [0, 0]]); ok($m->ord === [1, 0], 'positions cannot change mid-game');

// single service hand: B wins back the serve, then loses it - straight back to A
$m = new BdMatch(bd_format('bo1'), true, 0); play($m, 'ab'); $s = $m->state();
ok($s['server'][0] === 1 && $s['pts'] === [1, 1], 'one service turn per possession');

// service fault always goes to the receiver; let changes nothing
$m = new BdMatch($f); $m->apply(['w' => 0, 'k' => 'sf']); $s = $m->state();
ok($s['pts'] === [0, 1] && $s['stats'][0]['sf'] === 1 && $s['server'][0] === 1, 'service fault = rally to the receiver');
$m->apply(['t' => 'let']); ok($m->state()['pts'] === [0, 1] && $m->state()['stats'][1]['lets'] === 1, 'let replays the rally');

// retirement
$m = new BdMatch($f); play($m, 'aab'); $m->apply(['t' => 'ret', 'w' => 1]); $s = $m->state();
ok($s['over'] && $s['winner'] === 1 && $s['outcome'] === 'ret', 'retired');

// configurable: first to 15, no setting
$m = new BdMatch(bd_format(['best' => 1, 'pts' => 15, 'two' => false, 'cap' => 0, 'mid' => 8]));
play($m, str_repeat('ab', 14)); play($m, 'a'); ok($m->over && $m->winner === 0 && bd_summary($m->games) === '15-14', 'no setting: 15-14 wins');

// typed-in results
$ok = fn($rows, $fmt = 'bo3') => (function () use ($rows, $fmt) { try { bd_manual_state(bd_format($fmt), false, $rows); return true; } catch (InvalidArgumentException $e) { return $e->getMessage(); } })();
ok($ok([[21, 17], [18, 21], [21, 19]]) === true, 'three games ok');
ok($ok([[22, 20], [30, 29]]) === true, '22-20 and 30-29 ok');
ok($ok([[30, 28], [21, 0]]) === true, '30-28 ok');
ok($ok([[23, 20], [21, 0]]) !== true, '23-20 impossible');
ok($ok([[21, 20], [21, 0]]) !== true, '21-20 impossible');
ok($ok([[31, 29], [21, 0]]) !== true, '31-29 impossible with cap');
ok($ok([[21, 10]]) !== true, 'not finished in best of 3');
ok($ok([[21, 10]], 'bo1') === true, 'one game ok');
ok($ok([[21, 10], [21, 5], [21, 3]]) !== true, 'too many games');

echo $fails ? "$fails of $n checks failed\n" : "All $n checks passed\n";
exit($fails ? 1 : 0);

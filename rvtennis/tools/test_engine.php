<?php
/* RV Tennis - engine checks. Run: php tools/test_engine.php */
require __DIR__ . '/../inc/engine.php';
$fails = 0; $n = 0;
function ok($cond, string $what) { global $fails, $n; $n++; if (!$cond) { $fails++; echo "FAIL: $what\n"; } }
/** Play a string of point winners: 'a' / 'b', 'A' = ace by server side a, 'D' = double fault */
function play(TnMatch $m, string $seq): TnMatch { foreach (str_split($seq) as $c) $m->apply(['w' => $c === 'a' ? 0 : 1]); return $m; }
function games(TnMatch $m, string $seq): TnMatch { foreach (str_split($seq) as $c) play($m, str_repeat($c, 4)); return $m; }

// plain game and labels
$m = new TnMatch(tn_format('bo3')); play($m, 'ab'); $s = $m->state();
ok($s['label'] === ['15', '15'] && $s['call'] === 'fifteen all', '15 all');
play($m, 'aab'); ok($m->state()['label'] === ['40', '30'], '40-30');
play($m, 'b'); ok($m->state()['deuce'] && $m->state()['call'] === 'Deuce', 'deuce');
play($m, 'b'); $s = $m->state(); ok($s['label'] === ['', 'Ad'] && $s['flag']['k'] === 'bp' && $s['flag']['side'] === 1, 'ad out is a break point');
play($m, 'a'); ok($m->state()['deuce'], 'back to deuce');
play($m, 'aa'); $s = $m->state(); ok($s['games'] === [1, 0] && $s['server'][0] === 1 && $s['ends'] === true, 'game A, B to serve, change ends');

// no-ad
$m = new TnMatch(tn_format(['preset' => 'bo3', 'noad' => true])); play($m, 'aaabbb'); $s = $m->state();
ok($s['flag']['k'] === 'bp', 'no-ad deciding point is a break point'); play($m, 'b'); ok($m->state()['games'] === [0, 1], 'no-ad game to B');

// set 6-4, then tiebreak set, serve rotation
$m = new TnMatch(tn_format('bo3')); games($m, 'ababababaa'); $s = $m->state();
ok($s['sets'][0]['a'] === 6 && $s['sets'][0]['b'] === 4 && $s['setsWon'] === [1, 0], 'set 6-4');
games($m, 'abababababab'); $s = $m->state();
ok($s['tb'] && $s['games'] === [6, 6], 'tiebreak at 6-6');
$first = $s['server'][0]; play($m, 'a'); $s2 = $m->state(); ok($s2['server'][0] !== $first, 'tiebreak: serve changes after 1 point');
play($m, 'a'); ok($m->state()['server'][0] !== $first, 'tiebreak: same server for points 2-3'); play($m, 'a'); ok($m->state()['server'][0] === $first, 'tiebreak: back after 2 points');
play($m, 'bbb'); ok($m->state()['ends'], 'change ends after 6 tiebreak points');
play($m, 'aaa'); $s = $m->state(); ok($s['flag']['k'] === 'mp' && $s['flag']['side'] === 0, 'match point in tiebreak (6-3)');
play($m, 'a'); $s = $m->state();
ok($s['over'] && $s['winner'] === 0 && tn_summary($s['sets']) === '6-4 7-6(3)', 'match 6-4 7-6(3): ' . tn_summary($s['sets']));
ok($s['stats'][0]['pts'] + $s['stats'][1]['pts'] === $s['n'], 'points add up');

// tiebreak must be won by two
$m = new TnMatch(tn_format('set6')); games($m, 'abababababab'); play($m, 'aaaaaabbbbbb'); ok(!$m->over && $m->pts === [6, 6], 'tb 6-6 continues');
play($m, 'ab'); ok(!$m->over, '7-7 continues'); play($m, 'bb'); $s = $m->state(); ok($s['over'] && $s['winner'] === 1 && tn_summary($s['sets']) === '6-7(7)', 'tb 7-9: ' . tn_summary($s['sets']));

// advantage set with no tiebreak
$m = new TnMatch(tn_format(['preset' => 'set6', 'tb_at' => 0])); games($m, 'abababababab'); ok(!$m->inTb && !$m->over, 'no tiebreak at 6-6');
games($m, 'aa'); ok($m->over && tn_summary($m->sets) === '8-6', 'advantage set 8-6');

// match tiebreak decider
$m = new TnMatch(tn_format('bo3mtb')); games($m, 'aaaaaa'); games($m, 'bbbbbb'); $s = $m->state();
ok($s['tb'] && $s['mtb'] && $s['games'] === [0, 0], 'decider is a match tiebreak');
play($m, 'aaaaaaaaab'); ok($m->state()['flag']['k'] === 'mp', 'match point at 9-1'); play($m, 'a'); $s = $m->state();
ok($s['over'] && tn_summary($s['sets']) === '6-0 0-6 [10-1]', 'mtb: ' . tn_summary($s['sets']));

// short sets, pro set, single tiebreak
$m = new TnMatch(tn_format('short4')); games($m, 'aaaa'); ok($m->setsWon === [1, 0], 'short set 4-0'); games($m, 'abababab'); ok($m->inTb, 'short set tiebreak at 4-4');
$m = new TnMatch(tn_format('pro8')); games($m, 'aaaaaaaa'); ok($m->over && tn_summary($m->sets) === '8-0', 'pro set');
$m = new TnMatch(tn_format('tb10')); ok($m->state()['tb'] && $m->state()['mtb'], 'single tiebreak'); play($m, 'aaaaaaaaaa'); ok($m->over && tn_summary($m->sets) === '[10-0]', 'tb10');
$m = new TnMatch(tn_format('bo5')); games($m, str_repeat('a', 12)); ok(!$m->over, 'bo5 needs 3 sets'); games($m, 'aaaaaa'); ok($m->over, 'bo5 done');

// doubles rotation: A1, B1, A2, B2, A1 ...; first server B
$m = new TnMatch(tn_format('bo3'), true, 1); $seen = [];
for ($i = 0; $i < 5; $i++) { $seen[] = implode('', $m->server()); play($m, 'aaaa'); }
ok($seen === ['10', '00', '11', '01', '10'], 'doubles rotation ' . implode(',', $seen));

// aces, double faults, break points, set point flag
$m = new TnMatch(tn_format('bo3')); $m->apply(['w' => 0, 'k' => 'ace']); $m->apply(['w' => 1, 'k' => 'df']); $m->apply(['w' => 1, 'k' => 'ace']);
$s = $m->state(); ok($s['stats'][0]['aces'] === 1 && $s['stats'][0]['df'] === 1 && $s['stats'][1]['aces'] === 0, 'ace/df belong to the server only');
play($m, 'bb'); $s = $m->state(); ok($s['stats'][1]['bpc'] === 1 && $s['stats'][1]['bpw'] === 1 && $s['games'] === [0, 1], 'break converted');
$m = new TnMatch(tn_format('bo3')); games($m, 'ababababa'); play($m, 'aaa'); $s = $m->state();
ok($s['flag'] && $s['flag']['k'] === 'sp' && $s['flag']['side'] === 0, 'set point (and break point) at 5-4, 0-40');
play($m, 'bbbbb'); $s = $m->state(); ok($s['games'] === [5, 5] && $s['flag'] === null, 'held to 5-5, nothing at stake');

// 1st / 2nd serve, lets, double fault by two faults, serve stats
$m = new TnMatch(tn_format('bo3')); $m->apply(['t' => 'let']); $m->apply(['t' => 'f']); $s = $m->state();
ok($s['serve'] === 2 && $s['n'] === 0 && $s['stats'][0]['lets'] === 1, '2nd serve after a fault');
$m->apply(['t' => 'f']); $s = $m->state();
ok($s['serve'] === 1 && $s['label'] === ['0', '15'] && $s['stats'][0]['df'] === 1 && $s['stats'][0]['f1'] === 1, 'two faults = double fault');
$m->apply(['t' => 'f']); $m->apply(['w' => 0]); $m->apply(['w' => 0, 'k' => 'win']); $m->apply(['w' => 1, 'k' => 'ue']); $m->apply(['w' => 1, 'k' => 'fe']); $s = $m->state()['stats'];
ok($s[0]['w2'] === 1 && $s[0]['w1'] === 1 && $s[0]['f1'] === 2 && $s[0]['sp'] === 5, 'won on 1st / 2nd serve');
ok($s[0]['win'] === 1 && $s[0]['ue'] === 1 && $s[0]['fe'] === 1 && $s[1]['ue'] === 0, 'winner to the winner, errors to the loser');

// doubles: order and receiving courts are chosen per set and locked during it
$m = new TnMatch(tn_format('bo3'), true, 0); $m->apply(['t' => 'ord', 's' => [1, 0], 'r' => [0, 1]]);
ok($m->server() === [0, 1] && $m->receiver() === [1, 1] && $m->acts === 0, 'A2 serves first; B2 receives in the deuce court');
play($m, 'a'); ok($m->receiver() === [1, 0], 'B1 receives in the ad court');
$m->apply(['t' => 'ord', 's' => [0, 0]]); ok($m->server() === [0, 1], 'order cannot change during a set');
play($m, 'aaa'); ok($m->server() === [1, 0], 'then B1'); play($m, 'aaaa'); ok($m->server() === [0, 0], 'then A1'); play($m, 'aaaa'); ok($m->server() === [1, 1], 'then B2');
games($m, 'aaa'); ok($m->setsWon === [1, 0] && $m->state()['setStart'] && $m->server() === [0, 1], 'new set: same order unless changed');
$m->apply(['t' => 'ord', 's' => [0, 1]]); ok($m->server() === [0, 0], 'new set: A1 named to serve first'); play($m, 'aaaa'); ok($m->server() === [1, 1], 'and B2 first for pair B');

// no-ad deciding point: the receiver picks the court
$m = new TnMatch(tn_format(['noad' => true])); play($m, 'aaabb'); $m->apply(['t' => 'dc', 'c' => 1]); ok($m->state()['court'] === 'ad', 'no pick before 40-40 (5 points: ad court anyway)');
play($m, 'b'); $s = $m->state(); ok($s['deciding'] && $s['court'] === 'deuce', 'deciding point defaults to the deuce court');
$m->apply(['t' => 'dc', 'c' => 1]); ok($m->state()['court'] === 'ad' && $m->state()['picked'], 'receiver picked the ad court'); play($m, 'a'); ok($m->games === [1, 0] && $m->state()['court'] === 'deuce', 'pick cleared');

// retirement and walkover
$m = new TnMatch(tn_format('bo3')); games($m, 'aaa'); $m->apply(['t' => 'ret', 'w' => 1]); $s = $m->state();
ok($s['over'] && $s['winner'] === 1 && $s['outcome'] === 'ret', 'retired'); $m->apply(['w' => 0]); $m->apply(['t' => 'f']); ok($m->n === 12 && !$m->fault, 'no points after the end');
ok(tn_summary([], 'wo') === 'w/o', 'walkover text');
$m = new TnMatch(tn_format('bo3')); games($m, 'aaaaaa'); $m->apply(['t' => 'def', 'w' => 0]); $s = $m->state(); ok($s['outcome'] === 'def' && tn_summary($s['sets'], 'def') === '6-0 def.', 'default');

// typed-in results
$st = tn_manual_state(tn_format('bo3mtb'), false, [[6, 4], [3, 6], [10, 8]]); ok($st['winner'] === 0 && tn_summary($st['sets']) === '6-4 3-6 [10-8]', 'manual result');
foreach ([[[6, 4]], [[6, 4], [6, 3], [6, 0]], [[6, 6]]] as $bad) { try { tn_manual_state(tn_format('bo3'), false, $bad); ok(false, 'bad manual accepted'); } catch (InvalidArgumentException $e) { ok(true, ''); } }

// a long random match always ends, and replay is deterministic
mt_srand(7);
for ($r = 0; $r < 300; $r++) {
    $f = array_keys(TN_PRESETS)[$r % 7]; $ev = []; $m = new TnMatch(tn_format($f), $r % 2 === 1, $r % 3 === 0 ? 1 : 0);
    while (!$m->over && count($ev) < 3000) { $e = ['w' => mt_rand(0, 99) < 52 ? 0 : 1]; $ev[] = $e; $m->apply($e); }
    $a = $m->state(); $b = tn_replay(tn_format($f), $r % 2 === 1, $r % 3 === 0 ? 1 : 0, $ev)->state();
    ok($m->over && $a === $b && max($a['setsWon']) === $m->need(), "random $f #$r");
    $half = tn_replay(tn_format($f), false, 0, array_slice($ev, 0, intdiv(count($ev), 2)))->state();
    ok(!$half['over'] && in_array($half['server'][0], [0, 1], true), "mid-match state $f");
}
echo $fails ? "$fails of $n checks FAILED\n" : "All $n engine checks passed\n";
exit($fails ? 1 : 0);

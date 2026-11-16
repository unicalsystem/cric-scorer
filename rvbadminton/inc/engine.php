<?php
/* RV Badminton - the scoring engine (BWF rally-point scoring). A match is a format plus a list of events; everything
   on screen is worked out by replaying that list, so "undo" is simply dropping the last event.

   Format:  best  1 | 3 | 5          games in the match (league: 1, semi-finals and final: best of 3)
            pts   21                 points to win a game
            two   bool               a game must be won by 2 clear points (deuce / setting at 20-all)
            cap   30 | 0             sudden-death cap: whoever reaches it wins the game (30-29). 0 = no cap
            mid   11 | 0             leading score that brings the 60-second interval - and, in the deciding game
                                     (the only game of a one-game match), the change of ends. 0 = none
   Event:   ['w' => 0|1, 'k' => ''|'win'|'ue'|'fl'|'sf']   a rally won by side 0 (A) or 1 (B); k = how it ended:
                                     winner, unforced error, fault (out / net / body), service fault
            ['t' => 'let']                          a let: the rally is replayed, nothing changes (counted only)
            ['t' => 'ord', 'r' => [0|1, 0|1]]       doubles, at 0-0 of a game only: which partner of each pair stands in
                                                    the right service court (the serving pair's right-court player serves)
            ['t' => 'ret'|'wo'|'def', 'w' => 0|1]   retirement / walkover / disqualification, 'w' is the winner (always last) */

const BD_PRESETS = [
    'bo1'    => ['label' => 'One game to 21',              'best' => 1, 'pts' => 21, 'two' => true, 'cap' => 30, 'mid' => 11],
    'bo3'    => ['label' => 'Best of 3 games to 21',       'best' => 3, 'pts' => 21, 'two' => true, 'cap' => 30, 'mid' => 11],
    'bo1_15' => ['label' => 'One game to 15',              'best' => 1, 'pts' => 15, 'two' => true, 'cap' => 21, 'mid' => 8],
    'bo3_15' => ['label' => 'Best of 3 games to 15',       'best' => 3, 'pts' => 15, 'two' => true, 'cap' => 21, 'mid' => 8],
    'bo5_11' => ['label' => 'Best of 5 games to 11',       'best' => 5, 'pts' => 11, 'two' => true, 'cap' => 15, 'mid' => 6],
];

/** Clean a format that came from the phone or the database */
function bd_format($f): array {
    if (is_string($f)) $f = isset(BD_PRESETS[$f]) ? ['preset' => $f] : (json_decode($f, true) ?: []);
    $f = is_array($f) ? $f : [];
    $base = BD_PRESETS[$f['preset'] ?? ''] ?? BD_PRESETS['bo1'];
    $o = [];
    $o['best'] = in_array((int)($f['best'] ?? $base['best']), [1, 3, 5], true) ? (int)($f['best'] ?? $base['best']) : 1;
    $o['pts'] = max(5, min(50, (int)($f['pts'] ?? $base['pts'])));
    $o['two'] = (bool)($f['two'] ?? $base['two']);
    $cap = (int)($f['cap'] ?? $base['cap']);
    $o['cap'] = $o['two'] && $cap > 0 ? max($o['pts'] + 1, min(99, $cap)) : 0;   // a cap only means something when a game is won by two
    $mid = (int)($f['mid'] ?? $base['mid']);
    $o['mid'] = $mid <= 0 ? 0 : max(1, min($o['pts'] - 1, $mid));
    return $o;
}
function bd_format_label(array $f): string {
    $f = bd_format($f);
    $s = ($f['best'] === 1 ? 'One game' : "Best of {$f['best']} games") . " to {$f['pts']}";
    if (!$f['two']) $s .= ', first to ' . $f['pts'] . ' wins';
    elseif ($f['cap']) $s .= ", cap {$f['cap']}";
    else $s .= ', win by 2';
    return $s;
}

const BD_STATS_ZERO = ['pts' => 0, 'sp' => 0, 'sw' => 0, 'run' => 0, 'lead' => 0, 'sf' => 0, 'win' => 0, 'ue' => 0, 'fl' => 0, 'lets' => 0, 'gpc' => 0, 'gps' => 0];
// sp/sw rallies served / won on own serve · run most points in a row · lead biggest lead in a game · sf service faults made
// win winners hit · ue unforced errors made · fl other faults made (out, net, body) · gpc game points had · gps game points saved

class BdMatch {
    public array $f;
    public bool $doubles;
    public int $first;                 // side serving first in game 1
    public array $games = [];          // finished games: ['a'=>21,'b'=>17]
    public array $won = [0, 0];        // games won
    public array $pts = [0, 0];
    public int $srv;                   // side serving the next rally
    public array $ord = [0, 0];        // doubles: partner of each pair standing in the right court at 0-0
    public array $pos = [0, 0];        // doubles: partner of each pair in the right court now
    public bool $over = false;
    public ?int $winner = null;
    public ?string $outcome = null;    // null (played out) | 'ret' | 'wo' | 'def'
    public bool $midDone = false;      // the interval of this game has been reached
    public bool $interval = false;     // the last rally brought the interval
    public bool $ends = false;         // change ends now
    public bool $brk = false;          // a game has just finished: break before the next game
    public bool $srvOver = false;      // the last rally changed the serve
    public int $acts = 0;              // events that count as play (everything except 'ord')
    public int $n = 0;                 // rallies played
    public array $stats;
    public array $log = [['p' => '']]; // per game: the winner of every rally, '0101..'
    private array $streak = [0, 0];

    public function __construct(array $format, bool $doubles = false, int $first = 0) {
        $this->f = bd_format($format);
        $this->doubles = $doubles;
        $this->first = $first ? 1 : 0;
        $this->srv = $this->first;
        $z = BD_STATS_ZERO;
        $this->stats = [$z, $z];
    }
    public function need(): int { return intdiv($this->f['best'], 2) + 1; }
    public function deciding(): bool { return $this->won[0] === $this->need() - 1 && $this->won[1] === $this->need() - 1; }
    public function atGameStart(): bool { return !$this->over && $this->pts === [0, 0]; }

    /** Does $p against $q win the game? */
    public function gameWon(int $p, int $q): bool {
        $P = $this->f['pts'];
        if (!$this->f['two']) return $p >= $P;
        if ($this->f['cap'] && $p >= $this->f['cap']) return true;
        return $p >= $P && $p - $q >= 2;
    }
    /** [side, player index within the side] serving the next rally: from the right court on an even score, the left on an odd */
    public function server(): array {
        $s = $this->srv;
        if (!$this->doubles) return [$s, 0];
        return [$s, $this->pts[$s] % 2 === 0 ? $this->pos[$s] : 1 - $this->pos[$s]];
    }
    /** The receiver stands diagonally opposite: in their own right court when the server's score is even */
    public function receiver(): array {
        $r = 1 - $this->srv;
        if (!$this->doubles) return [$r, 0];
        return [$r, $this->pts[$this->srv] % 2 === 0 ? $this->pos[$r] : 1 - $this->pos[$r]];
    }
    public function court(): string { return $this->pts[$this->srv] % 2 === 0 ? 'right' : 'left'; }

    public function apply(array $e): void {
        if ($this->over) return;
        $t = $e['t'] ?? null;
        if ($t === 'ord') {   // only at 0-0 of a game: positions are then locked for the game
            if ($this->doubles && $this->atGameStart() && isset($e['r']) && is_array($e['r'])) {
                foreach ([0, 1] as $i) if (isset($e['r'][$i])) $this->ord[$i] = $e['r'][$i] ? 1 : 0;
                $this->pos = $this->ord;
            }
            return;
        }
        $this->acts++;
        $this->interval = $this->ends = $this->brk = $this->srvOver = false;
        if ($t === 'let') { $this->stats[$this->srv]['lets']++; return; }
        if ($t !== null) {
            $this->over = true; $this->winner = !empty($e['w']) ? 1 : 0; $this->outcome = in_array($t, ['wo', 'def'], true) ? $t : 'ret';
            return;
        }
        $k = (string)($e['k'] ?? '');
        $s = $this->srv;
        $w = $k === 'sf' ? 1 - $s : (!empty($e['w']) ? 1 : 0);   // a service fault is always a rally to the receiver
        $l = 1 - $w;
        // game points before this rally (for "game points saved")
        foreach ([0, 1] as $x) if ($this->gameWon($this->pts[$x] + 1, $this->pts[1 - $x])) { $this->stats[$x]['gpc']++; if ($w !== $x) $this->stats[1 - $x]['gps']++; }
        $this->n++;
        $st = &$this->stats;
        $st[$w]['pts']++;
        $st[$s]['sp']++;
        if ($w === $s) $st[$s]['sw']++;
        if ($k === 'sf') $st[$s]['sf']++;
        if ($k === 'win') $st[$w]['win']++;
        if ($k === 'ue' || $k === 'fl') $st[$l][$k]++;
        $this->streak[$w]++; $this->streak[$l] = 0;
        $st[$w]['run'] = max($st[$w]['run'], $this->streak[$w]);
        unset($st);
        $this->log[count($this->log) - 1]['p'] .= $w;
        $this->pts[$w]++;
        $this->stats[$w]['lead'] = max($this->stats[$w]['lead'], $this->pts[$w] - $this->pts[$l]);

        if ($w === $s) { if ($this->doubles) $this->pos[$s] = 1 - $this->pos[$s]; }   // the server scores and changes service court
        else { $this->srv = $w; $this->srvOver = true; }                               // the receivers win the serve; nobody moves

        if ($this->gameWon($this->pts[$w], $this->pts[$l])) {
            $this->games[] = ['a' => $this->pts[0], 'b' => $this->pts[1]];
            $this->log[count($this->log) - 1]['w'] = $w;
            $this->won[$w]++;
            if ($this->won[$w] >= $this->need()) { $this->over = true; $this->winner = $w; return; }
            // next game: the winner of this one serves first; pairs take up positions again (same as last game unless changed)
            $this->pts = [0, 0]; $this->srv = $w; $this->pos = $this->ord; $this->midDone = false;
            $this->brk = true; $this->ends = true; $this->srvOver = false;
            $this->log[] = ['p' => ''];
            return;
        }
        $mid = $this->f['mid'];
        if ($mid && !$this->midDone && max($this->pts) >= $mid) {
            $this->midDone = true;
            $this->interval = true;
            if ($this->deciding()) $this->ends = true;
        }
    }

    /** What the next rally would decide for a side: 'mp' match, 'gp' game, or null */
    private function stakes(int $side): ?string {
        if (!$this->gameWon($this->pts[$side] + 1, $this->pts[1 - $side])) return null;
        return $this->won[$side] + 1 >= $this->need() ? 'mp' : 'gp';
    }
    /** How an umpire calls the score: the server's score first */
    public function call(): string {
        if ($this->over) return 'Match';
        $s = $this->srv; $x = $this->pts[$s]; $y = $this->pts[1 - $s];
        if ($x === 0 && $y === 0) {
            $g = count($this->games);
            return ($g === 0 ? '' : ($this->deciding() && $this->f['best'] > 1 ? 'Final game, ' : ['', 'Second game, ', 'Third game, ', 'Fourth game, ', 'Fifth game, '][$g] ?? '')) . 'love all, play';
        }
        $k = $this->stakes($s);
        $score = $x === $y ? "$x all" : ($k ? "$x " . ($k === 'mp' ? 'match point' : 'game point') . " $y" : "$x $y");
        return ($this->srvOver ? 'Service over, ' : '') . $score . ($this->interval ? ', interval' : '');
    }

    public function state(): array {
        $flag = null;
        if (!$this->over) {
            $a = $this->stakes(0); $b = $this->stakes(1);
            if ($a && $b) $flag = ['k' => 'both'];                   // e.g. 29-all with a cap of 30: next rally wins
            elseif ($a || $b) $flag = ['k' => $a ?: $b, 'side' => $a ? 0 : 1];
        }
        return [
            'format' => $this->f, 'doubles' => $this->doubles, 'games' => $this->games, 'won' => $this->won, 'pts' => $this->pts,
            'game' => count($this->games) + ($this->over ? 0 : 1), 'deciding' => !$this->over && $this->deciding(),
            'server' => $this->over ? null : $this->server(), 'receiver' => $this->over ? null : $this->receiver(), 'court' => $this->court(),
            'ord' => $this->ord, 'pos' => $this->pos, 'gameStart' => $this->atGameStart(), 'acts' => $this->acts,
            'setting' => !$this->over && $this->f['two'] && min($this->pts) >= $this->f['pts'] - 1 && $this->pts[0] === $this->pts[1],
            'over' => $this->over, 'winner' => $this->winner, 'outcome' => $this->outcome, 'flag' => $flag,
            'interval' => $this->interval, 'ends' => $this->ends, 'brk' => $this->brk, 'srvOver' => $this->srvOver,
            'call' => $this->call(), 'n' => $this->n, 'stats' => $this->stats, 'log' => $this->log,
        ];
    }
}

/** Replay a list of events */
function bd_replay(array $format, bool $doubles, int $first, array $events): BdMatch {
    $m = new BdMatch($format, $doubles, $first);
    foreach ($events as $e) $m->apply($e);
    return $m;
}
/** "21-17 18-21 21-19" - side A's score first */
function bd_summary(array $games, ?string $outcome = null): string {
    $t = implode(' ', array_map(fn($g) => "{$g['a']}-{$g['b']}", $games));
    if ($outcome === 'ret') $t = trim($t . ' ret.');
    if ($outcome === 'def') $t = trim($t . ' dq.');
    if ($outcome === 'wo') $t = 'w/o';
    return $t;
}
/** A result typed in afterwards: [[21,17],[18,21],[21,19]] → finished-match state. Fails with a message if it is not a complete, possible result. */
function bd_manual_state(array $format, bool $doubles, array $rows): array {
    $f = bd_format($format);
    $m = new BdMatch($f, $doubles);
    $need = $m->need();
    $games = []; $won = [0, 0]; $winner = null;
    foreach ($rows as $i => $r) {
        $a = (int)($r[0] ?? -1); $b = (int)($r[1] ?? -1);
        $g = 'Game ' . ($i + 1);
        if ($a < 0 || $b < 0 || $a > 99 || $b > 99 || $a === $b) throw new InvalidArgumentException("$g needs two different scores, e.g. 21 and 17");
        if ($winner !== null) throw new InvalidArgumentException('There are more games than the match needs');
        $w = $a > $b ? 0 : 1; $p = max($a, $b); $q = min($a, $b);
        // the game must end exactly at that score: a winner, and not already won one point earlier
        if (!$m->gameWon($p, $q) || $m->gameWon($p - 1, $q) || $m->gameWon($q, $p - 1)) {
            $cap = $f['cap'] ? ", or {$f['cap']}-" . ($f['cap'] - 1) : '';
            throw new InvalidArgumentException("$g: $a-$b is not a possible score. A game ends at {$f['pts']}" . ($f['two'] ? ' with a 2-point lead' . $cap : ''));
        }
        $games[] = ['a' => $a, 'b' => $b]; $won[$w]++;
        if ($won[$w] >= $need) $winner = $w;
    }
    if ($winner === null) throw new InvalidArgumentException('That is not a finished match - ' . ($need === 1 ? 'enter the game score' : "one side needs to win $need games"));
    $z = BD_STATS_ZERO;
    return ['format' => $f, 'doubles' => $doubles, 'games' => $games, 'won' => $won, 'pts' => [0, 0], 'game' => count($games), 'deciding' => false,
        'server' => null, 'receiver' => null, 'court' => 'right', 'ord' => [0, 0], 'pos' => [0, 0], 'gameStart' => false, 'acts' => 1, 'setting' => false,
        'over' => true, 'winner' => $winner, 'outcome' => null, 'flag' => null, 'interval' => false, 'ends' => false, 'brk' => false, 'srvOver' => false,
        'call' => '', 'n' => 0, 'stats' => [$z, $z], 'log' => [], 'manual' => true];
}

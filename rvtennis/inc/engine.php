<?php
/* RV Tennis - the scoring engine. A match is a format plus a list of events; everything on screen is worked out
   by replaying that list, so "undo" is simply dropping the last event.

   Format:  best   1 | 3 | 5        sets in the match
            games  4 | 6 | 8 ...    games to win a set (win by two)
            tb_at  games-all at which a tiebreak is played (0 = no tiebreak, play on until two clear)
            tb_to  7                points to win that tiebreak
            final  'set' | 'tb'     deciding set played in full, or replaced by a match tiebreak
            final_to 10             points to win the match tiebreak
            noad   bool             at deuce the next point wins the game
   Event:   ['w' => 0|1, 'k' => ''|'ace'|'df']     a point won by side 0 (A) or 1 (B)
            ['t' => 'ret'|'wo', 'w' => 0|1]         retirement / walkover, 'w' is the winner (always the last event) */

const TN_PRESETS = [
    'bo3'    => ['label' => 'Best of 3 sets',                      'best' => 3, 'games' => 6, 'tb_at' => 6, 'tb_to' => 7, 'final' => 'set', 'final_to' => 10, 'noad' => false],
    'bo3mtb' => ['label' => '2 sets + 10-point match tiebreak',    'best' => 3, 'games' => 6, 'tb_at' => 6, 'tb_to' => 7, 'final' => 'tb',  'final_to' => 10, 'noad' => false],
    'set6'   => ['label' => 'One set to 6',                        'best' => 1, 'games' => 6, 'tb_at' => 6, 'tb_to' => 7, 'final' => 'set', 'final_to' => 10, 'noad' => false],
    'short4' => ['label' => 'Short sets to 4, best of 3',          'best' => 3, 'games' => 4, 'tb_at' => 4, 'tb_to' => 7, 'final' => 'tb',  'final_to' => 10, 'noad' => false],
    'pro8'   => ['label' => 'Pro set to 8',                        'best' => 1, 'games' => 8, 'tb_at' => 8, 'tb_to' => 7, 'final' => 'set', 'final_to' => 10, 'noad' => false],
    'tb10'   => ['label' => 'Single 10-point tiebreak',            'best' => 1, 'games' => 6, 'tb_at' => 6, 'tb_to' => 7, 'final' => 'tb',  'final_to' => 10, 'noad' => false],
    'bo5'    => ['label' => 'Best of 5 sets',                      'best' => 5, 'games' => 6, 'tb_at' => 6, 'tb_to' => 7, 'final' => 'set', 'final_to' => 10, 'noad' => false],
];

/** Clean a format that came from the phone or the database */
function tn_format($f): array {
    if (is_string($f)) $f = isset(TN_PRESETS[$f]) ? ['preset' => $f] : (json_decode($f, true) ?: []);
    $f = is_array($f) ? $f : [];
    $base = TN_PRESETS[$f['preset'] ?? ''] ?? TN_PRESETS['bo3'];
    $o = [];
    $o['preset'] = isset(TN_PRESETS[$f['preset'] ?? '']) ? $f['preset'] : 'custom';
    $o['best'] = in_array((int)($f['best'] ?? $base['best']), [1, 3, 5], true) ? (int)($f['best'] ?? $base['best']) : 3;
    $o['games'] = max(1, min(12, (int)($f['games'] ?? $base['games'])));
    $tb = (int)($f['tb_at'] ?? $base['tb_at']);
    $o['tb_at'] = $tb <= 0 ? 0 : max($o['games'] - 1, min($o['games'], $tb));
    $o['tb_to'] = max(5, min(15, (int)($f['tb_to'] ?? $base['tb_to'])));
    $o['final'] = ($f['final'] ?? $base['final']) === 'tb' ? 'tb' : 'set';
    $o['final_to'] = max(5, min(21, (int)($f['final_to'] ?? $base['final_to'])));
    $o['noad'] = !empty($f['noad'] ?? $base['noad']);
    return $o;
}
function tn_format_label(array $f): string {
    $f = tn_format($f);
    if ($f['best'] === 1 && $f['final'] === 'tb') $s = "Single {$f['final_to']}-point tiebreak";
    else {
        $s = $f['best'] === 1 ? "One set to {$f['games']}" : "Best of {$f['best']}, sets to {$f['games']}";
        if ($f['best'] > 1 && $f['final'] === 'tb') $s .= ", {$f['final_to']}-point decider";
        if ($f['tb_at'] === 0) $s .= ', no tiebreak';
        elseif ($f['tb_at'] !== $f['games']) $s .= ", tiebreak at {$f['tb_at']}-all";
    }
    return $s . ($f['noad'] ? ', no-ad' : '');
}

class TnMatch {
    public array $f;
    public bool $doubles;
    public int $first;                 // side serving the first game
    public array $sets = [];           // finished sets: ['a'=>6,'b'=>4,'tb'=>[7,5]|null,'mtb'=>bool]
    public array $games = [0, 0];
    public array $pts = [0, 0];
    public array $setsWon = [0, 0];
    public bool $inTb = false;
    public int $tbTo = 7;
    public bool $mtb = false;
    public int $serveIdx = 0;          // games started so far (a tiebreak counts as one)
    public bool $over = false;
    public ?int $winner = null;
    public ?string $outcome = null;    // null (played out) | 'ret' | 'wo'
    public int $n = 0;                 // points played
    public array $stats;
    public array $log = [[]];          // per set, per game: ['s'=>server side,'w'=>winner,'p'=>'0110..','tb'=>bool]
    private array $cur = ['p' => ''];
    private array $streak = [0, 0];

    public function __construct(array $format, bool $doubles = false, int $first = 0) {
        $this->f = tn_format($format);
        $this->doubles = $doubles;
        $this->first = $first ? 1 : 0;
        $z = ['pts' => 0, 'aces' => 0, 'df' => 0, 'sp' => 0, 'sw' => 0, 'bpc' => 0, 'bpw' => 0, 'games' => 0, 'run' => 0];
        $this->stats = [$z, $z];
        $this->startSet();
    }
    public function need(): int { return intdiv($this->f['best'], 2) + 1; }

    private function startSet(): void {
        $this->games = [0, 0]; $this->pts = [0, 0];
        $deciding = $this->setsWon[0] === $this->need() - 1 && $this->setsWon[1] === $this->need() - 1;
        $this->mtb = $deciding && $this->f['final'] === 'tb';
        $this->inTb = $this->mtb;
        $this->tbTo = $this->mtb ? $this->f['final_to'] : $this->f['tb_to'];
    }
    /** [side, player index within the side] serving the next point */
    public function server(): array {
        $i = $this->serveIdx + ($this->inTb ? intdiv($this->pts[0] + $this->pts[1] + 1, 2) : 0);
        if (!$this->doubles) return [($this->first + $i) % 2, 0];
        $i %= 4;
        return [($this->first + $i) % 2, intdiv($i, 2)];
    }
    /** Would the receiver win the game with the next point? */
    private function isBreakPoint(): bool {
        if ($this->inTb || $this->over) return false;
        $s = $this->server()[0]; $r = 1 - $s;
        return $this->pts[$r] >= 3 && ($this->pts[$r] > $this->pts[$s] || ($this->f['noad'] && $this->pts[$r] === $this->pts[$s]));
    }

    public function apply(array $e): void {
        if ($this->over) return;
        if (isset($e['t'])) {
            $this->over = true; $this->winner = !empty($e['w']) ? 1 : 0; $this->outcome = $e['t'] === 'wo' ? 'wo' : 'ret';
            return;
        }
        $w = !empty($e['w']) ? 1 : 0; $l = 1 - $w;
        $k = (string)($e['k'] ?? '');
        $srv = $this->server()[0];
        $bp = $this->isBreakPoint();
        $this->n++;
        $st = &$this->stats;
        $st[$w]['pts']++;
        $st[$srv]['sp']++;
        if ($w === $srv) $st[$srv]['sw']++;
        if ($k === 'ace' && $w === $srv) $st[$srv]['aces']++;
        if ($k === 'df' && $w !== $srv) $st[$srv]['df']++;
        if ($bp) { $st[1 - $srv]['bpc']++; if ($w !== $srv) $st[1 - $srv]['bpw']++; }
        $this->streak[$w]++; $this->streak[$l] = 0;
        $st[$w]['run'] = max($st[$w]['run'], $this->streak[$w]);
        unset($st);
        if ($this->cur['p'] === '') $this->cur = ['s' => $srv, 'p' => '', 'tb' => $this->inTb];
        $this->cur['p'] .= $w;
        $this->pts[$w]++;

        $won = $this->inTb
            ? ($this->pts[$w] >= $this->tbTo && $this->pts[$w] - $this->pts[$l] >= 2)
            : ($this->pts[$w] >= 4 && ($this->f['noad'] || $this->pts[$w] - $this->pts[$l] >= 2));
        if (!$won) return;

        // game (or tiebreak) to $w
        $wasTb = $this->inTb; $tbScore = $this->pts;
        $this->cur['w'] = $w;
        $this->log[count($this->log) - 1][] = $this->cur;
        $this->cur = ['p' => ''];
        $this->games[$w]++; $this->stats[$w]['games']++;
        $this->pts = [0, 0];
        $this->serveIdx++;
        $g = $this->games; $G = $this->f['games'];
        $setWon = $wasTb || ($g[$w] >= $G && $g[$w] - $g[$l] >= 2);
        if (!$setWon) {
            if ($this->f['tb_at'] > 0 && $g[0] === $this->f['tb_at'] && $g[1] === $this->f['tb_at']) { $this->inTb = true; $this->tbTo = $this->f['tb_to']; }
            return;
        }
        $this->sets[] = $this->mtb ? ['a' => $tbScore[0], 'b' => $tbScore[1], 'tb' => null, 'mtb' => true]
                                   : ['a' => $g[0], 'b' => $g[1], 'tb' => $wasTb ? $tbScore : null, 'mtb' => false];
        $this->setsWon[$w]++;
        if ($this->setsWon[$w] >= $this->need()) { $this->over = true; $this->winner = $w; $this->games = [0, 0]; $this->inTb = false; return; }
        $this->log[] = [];
        $this->startSet();
    }

    /** What the next point would decide for each side: 'mp' match, 'sp' set, 'gp' game, or null */
    private function stakes(int $side): ?string {
        $c = clone $this;
        $c->apply(['w' => $side]);
        if ($c->over) return 'mp';
        if (count($c->sets) > count($this->sets)) return 'sp';
        if ($c->serveIdx > $this->serveIdx) return 'gp';
        return null;
    }
    private function label(): array {
        [$a, $b] = $this->pts;
        if ($this->inTb) return [(string)$a, (string)$b];
        $n = ['0', '15', '30', '40'];
        if ($a >= 3 && $b >= 3) {
            if ($a === $b) return ['40', '40'];
            return $a > $b ? ['Ad', ''] : ['', 'Ad'];
        }
        return [$n[$a], $n[$b]];
    }
    /** How an umpire would call the score, server's score first */
    public function call(): string {
        if ($this->over) return 'Game, set and match';
        [$a, $b] = $this->pts; $s = $this->server()[0];
        $x = $s === 0 ? $a : $b; $y = $s === 0 ? $b : $a;
        if ($this->inTb) return $x === $y ? ($x === 0 ? 'Tiebreak' : "$x all") : "$x $y";
        if ($a === 0 && $b === 0) return 'Game';
        $w = ['love', 'fifteen', 'thirty', 'forty'];
        if ($x >= 3 && $y >= 3) return $x === $y ? ($this->f['noad'] ? 'Deuce, deciding point' : 'Deuce') : 'Advantage ' . ($x > $y ? 'server' : 'receiver');
        return $x === $y ? $w[$x] . ' all' : $w[$x] . ' ' . $w[$y];
    }

    public function state(): array {
        $flag = null;
        if (!$this->over) {
            $rank = ['gp' => 1, 'sp' => 2, 'mp' => 3];
            $srv = $this->server()[0];
            foreach ([0, 1] as $s) {
                $k = $this->stakes($s);
                if (!$k) continue;
                if ($k === 'gp') { if ($s === $srv || $this->inTb) continue; $k = 'bp'; }   // a plain game point is only news when it is a break point
                $r = $rank[$k] ?? 1;
                if (!$flag || $r > $flag['r']) $flag = ['k' => $k, 'side' => $s, 'r' => $r];
            }
            if ($flag) unset($flag['r']);
        }
        $tot = $this->pts[0] + $this->pts[1]; $gsum = $this->games[0] + $this->games[1];
        $ends = false;
        if (!$this->over && $this->n > 0) {
            if ($this->inTb && $tot > 0) $ends = $tot % 6 === 0;
            elseif ($tot === 0 && $gsum > 0) $ends = $gsum % 2 === 1;
            elseif ($tot === 0 && $gsum === 0 && $this->sets) { $l = end($this->sets); $ends = !$l['mtb'] && ($l['a'] + $l['b']) % 2 === 1; }
        }
        $log = $this->log;
        if ($this->cur['p'] !== '') $log[count($log) - 1][] = $this->cur;
        return [
            'format' => $this->f, 'doubles' => $this->doubles, 'sets' => $this->sets, 'setsWon' => $this->setsWon,
            'games' => $this->games, 'pts' => $this->pts, 'label' => $this->label(), 'tb' => $this->inTb, 'mtb' => $this->mtb,
            'deuce' => !$this->inTb && $this->pts[0] >= 3 && $this->pts[0] === $this->pts[1],
            'server' => $this->over ? null : $this->server(), 'court' => $tot % 2 === 0 ? 'deuce' : 'ad',
            'over' => $this->over, 'winner' => $this->winner, 'outcome' => $this->outcome, 'flag' => $flag, 'ends' => $ends,
            'call' => $this->call(), 'n' => $this->n, 'stats' => $this->stats, 'log' => $log,
        ];
    }
}

/** Replay a list of events */
function tn_replay(array $format, bool $doubles, int $first, array $events): TnMatch {
    $m = new TnMatch($format, $doubles, $first);
    foreach ($events as $e) $m->apply($e);
    return $m;
}
/** "6-4 6-7(5) [10-8]" - side A's score first */
function tn_summary(array $sets, ?string $outcome = null): string {
    $p = [];
    foreach ($sets as $s) {
        if (!empty($s['mtb'])) $p[] = "[{$s['a']}-{$s['b']}]";
        else $p[] = "{$s['a']}-{$s['b']}" . (!empty($s['tb']) ? '(' . min($s['tb'][0], $s['tb'][1]) . ')' : '');
    }
    $t = implode(' ', $p);
    if ($outcome === 'ret') $t = trim($t . ' ret.');
    if ($outcome === 'wo') $t = 'w/o';
    return $t;
}
/** A result typed in afterwards: [[6,4],[3,6],[10,8]] → finished-match state. Fails with a message if it is not a complete result. */
function tn_manual_state(array $format, bool $doubles, array $rows): array {
    $f = tn_format($format); $need = intdiv($f['best'], 2) + 1;
    $sets = []; $won = [0, 0]; $winner = null;
    foreach ($rows as $r) {
        $a = (int)($r[0] ?? -1); $b = (int)($r[1] ?? -1);
        if ($a < 0 || $b < 0 || $a > 99 || $b > 99 || $a === $b) throw new InvalidArgumentException('Each set needs two different scores, e.g. 6 and 4');
        if ($winner !== null) throw new InvalidArgumentException('There are more sets than the match format allows');
        $deciding = $won[0] === $need - 1 && $won[1] === $need - 1;
        $mtb = $deciding && $f['final'] === 'tb';
        $sets[] = ['a' => $a, 'b' => $b, 'tb' => null, 'mtb' => $mtb];
        $w = $a > $b ? 0 : 1; $won[$w]++;
        if ($won[$w] >= $need) $winner = $w;
    }
    if ($winner === null) throw new InvalidArgumentException('That is not a finished match - ' . ($need === 1 ? 'enter the set score' : "one side needs $need sets"));
    $z = ['pts' => 0, 'aces' => 0, 'df' => 0, 'sp' => 0, 'sw' => 0, 'bpc' => 0, 'bpw' => 0, 'games' => 0, 'run' => 0];
    return ['format' => $f, 'doubles' => $doubles, 'sets' => $sets, 'setsWon' => $won, 'games' => [0, 0], 'pts' => [0, 0], 'label' => ['', ''],
        'tb' => false, 'mtb' => false, 'deuce' => false, 'server' => null, 'court' => 'deuce', 'over' => true, 'winner' => $winner, 'outcome' => null,
        'flag' => null, 'ends' => false, 'call' => '', 'n' => 0, 'stats' => [$z, $z], 'log' => [], 'manual' => true];
}

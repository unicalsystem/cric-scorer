<?php
/* Cric-Scorer - full scorecard PDF (v1.44). A small pure-PHP PDF writer (no libraries needed on shared hosting) and the
   scorecard layout: result, batting, bowling, extras, fall of wickets, partnerships, over-by-over and EVERY ball.
   Text is Helvetica (Latin letters). Names in other scripts (Telugu, Hindi ...) print as '?' - a PDF limit of the built-in font. */

/** Signed link key so the admin can share a PDF with teams without giving them a login. */
function pdf_key(): string {
    $k = (string)setting('pdf_key', '');
    if ($k === '') { $k = bin2hex(random_bytes(24)); setting_set('pdf_key', $k); }
    return $k;
}
function pdf_token(int $matchId): string { return substr(hash_hmac('sha256', 'scorecard-pdf:' . $matchId, pdf_key()), 0, 24); }
function pdf_token_ok(int $matchId, string $t): bool { return $t !== '' && hash_equals(pdf_token($matchId), $t); }

class CsPdf {
    const W = 595.28, H = 841.89;
    public float $mx = 34, $top = 40, $bottom = 38;
    public float $y = 0;
    private array $pages = [];
    private string $buf = '';
    public string $footer = '';
    private static ?array $wr = null, $wb = null;

    function __construct() { $this->initWidths(); $this->y = $this->top; }

    private function initWidths(): void {
        if (self::$wr) return;
        $r = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,
              667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,
              556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
        $b = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,
              722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,
              556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
        self::$wr = $r; self::$wb = $b;
    }
    /** UTF-8 -> Windows-1252 bytes; anything not in Latin becomes '?'. */
    static function enc(string $s): string {
        $s = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $s) ?? $s;   // emoji
        $s = strtr($s, ["\u{2192}" => '->', "\u{2190}" => '<-', "\u{00B7}" => "\xB7"]);
        $o = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        if ($o === false || $o === '') {
            $o = preg_replace('/[^\x20-\x7E]/', '?', $s);
        }
        // iconv TRANSLIT may leave '?' for non-Latin; keep as is. Strip control characters.
        return preg_replace('/[\x00-\x1F\x7F]/', ' ', $o);
    }
    function width(string $s, float $size, bool $bold = false): float {
        $t = $bold ? self::$wb : self::$wr; $w = 0;
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) { $c = ord($s[$i]); $w += ($c >= 32 && $c <= 126) ? $t[$c - 32] : 556; }
        return $w * $size / 1000;
    }
    function fit(string $s, float $w, float $size, bool $bold = false): string {
        $s = self::enc($s);
        if ($this->width($s, $size, $bold) <= $w) return $s;
        while ($s !== '' && $this->width($s . '..', $size, $bold) > $w) $s = substr($s, 0, -1);
        return rtrim($s) . '..';
    }
    /** @return string[] lines wrapped to width */
    function wrap(string $s, float $w, float $size, bool $bold = false): array {
        $s = self::enc($s); $lines = []; $cur = '';
        foreach (preg_split('/\s+/', trim($s)) as $word) {
            if ($word === '') continue;
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($this->width($try, $size, $bold) <= $w) { $cur = $try; continue; }
            if ($cur !== '') $lines[] = $cur;
            while ($this->width($word, $size, $bold) > $w && strlen($word) > 1) {   // very long word
                $cut = $word; while (strlen($cut) > 1 && $this->width($cut, $size, $bold) > $w) $cut = substr($cut, 0, -1);
                $lines[] = $cut; $word = substr($word, strlen($cut));
            }
            $cur = $word;
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines ?: [''];
    }
    private static function esc(string $s): string { return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']); }
    private static function col(array $c): string { return sprintf('%.3F %.3F %.3F', $c[0], $c[1], $c[2]); }
    /** Draw text; $x from left, $top is the distance from the top of the page to the TOP of the text line. */
    function text(float $x, float $top, string $s, float $size = 9, bool $bold = false, array $color = [0.1, 0.1, 0.1], string $align = 'l', float $w = 0): void {
        $s = self::enc($s); if ($s === '') return;
        $tw = $this->width($s, $size, $bold);
        if ($align === 'r') $x += $w - $tw; elseif ($align === 'c') $x += ($w - $tw) / 2;
        $base = self::H - ($top + $size * 0.85);
        $this->buf .= sprintf("BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, self::col($color), $x, $base, self::esc($s));
    }
    function rect(float $x, float $top, float $w, float $h, array $fill): void {
        $this->buf .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", self::col($fill), $x, self::H - $top - $h, $w, $h);
    }
    function hline(float $x, float $top, float $w, array $c = [0.8, 0.8, 0.8], float $lw = 0.5): void {
        $this->buf .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", self::col($c), $lw, $x, self::H - $top, $x + $w, self::H - $top);
    }
    function usable(): float { return self::W - 2 * $this->mx; }
    function newPage(): void { $this->pages[] = $this->buf; $this->buf = ''; $this->y = $this->top; }
    /** Make sure $h points are free on this page; else start a new one. */
    function need(float $h): bool {
        if ($this->y + $h <= self::H - $this->bottom) return false;
        $this->newPage(); return true;
    }
    function gap(float $h): void { $this->y += $h; }

    /** One table row. $cells strings; $widths points; $al 'l'/'r'/'c' per column. Returns the height used. */
    function row(array $cells, array $widths, array $al, array $o = []): float {
        $size = $o['size'] ?? 8.5; $bold = $o['bold'] ?? false; $pad = 3;
        $lines = 1; $wrapped = [];
        foreach ($cells as $i => $c) {
            if (!empty($o['wrap'][$i])) { $wrapped[$i] = $this->wrap((string)$c, $widths[$i] - 2 * $pad, $size, $bold); $lines = max($lines, count($wrapped[$i])); }
        }
        $lh = $size + 3.2; $h = $lh * $lines + 1.5;
        if ($this->need($h) && !empty($o['repeat'])) { $this->row(...$o['repeat']); }
        if (!empty($o['fill'])) $this->rect($this->mx, $this->y, array_sum($widths), $h, $o['fill']);
        $x = $this->mx; $color = $o['color'] ?? [0.12, 0.12, 0.12];
        foreach ($cells as $i => $c) {
            $cc = is_array($o['colors'] ?? null) && isset($o['colors'][$i]) ? $o['colors'][$i] : $color;
            $b = is_array($o['bolds'] ?? null) && isset($o['bolds'][$i]) ? $o['bolds'][$i] : $bold;
            if (isset($wrapped[$i])) { foreach ($wrapped[$i] as $k => $ln) $this->text($x + $pad, $this->y + 1 + $k * $lh, $ln, $size, $b, $cc); }
            else $this->text($x + $pad, $this->y + 1, $this->fit((string)$c, $widths[$i] - 2 * $pad, $size, $b), $size, $b, $cc, $al[$i] ?? 'l', $widths[$i] - 2 * $pad);
            $x += $widths[$i];
        }
        if (!empty($o['line'])) $this->hline($this->mx, $this->y + $h, array_sum($widths), [0.88, 0.88, 0.88], 0.4);
        $this->y += $h;
        return $h;
    }

    function output(): string {
        $this->pages[] = $this->buf; $this->buf = '';
        $n = count($this->pages);
        foreach ($this->pages as $i => &$p) {
            $p .= sprintf("%s RG 0.5 w %.2F %.2F m %.2F %.2F l S\n", self::col([0.8, 0.8, 0.8]), $this->mx, 30, self::W - $this->mx, 30);
            $tmp = $this->buf; $this->buf = '';
            $this->text($this->mx, self::H - 26, $this->footer, 7.5, false, [0.45, 0.45, 0.45]);
            $this->text($this->mx, self::H - 26, 'Page ' . ($i + 1) . ' of ' . $n, 7.5, false, [0.45, 0.45, 0.45], 'r', $this->usable());
            $p .= $this->buf; $this->buf = $tmp;
        }
        unset($p);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = []; $np = count($this->pages);
        for ($i = 0; $i < $np; $i++) $kids[] = (6 + $i * 2) . ' 0 R';
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $np . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objs[5] = '<< /Title (Cric-Scorer scorecard) /Producer (Cric-Scorer) >>';
        foreach ($this->pages as $i => $content) {
            $pid = 6 + $i * 2; $cid = $pid + 1;
            $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $cid . ' 0 R >>';
            $data = $content; $filter = '';
            if (function_exists('gzcompress')) { $data = gzcompress($content, 6); $filter = ' /Filter /FlateDecode'; }
            $objs[$cid] = ['<< /Length ' . strlen($data) . $filter . ' >>', $data];
        }
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
        ksort($objs);
        foreach ($objs as $id => $o) {
            $off[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . (is_array($o) ? $o[0] . "\nstream\n" . $o[1] . "\nendstream" : $o) . "\nendobj\n";
        }
        $x = strlen($out); $max = max(array_keys($objs));
        $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) $out .= sprintf("%010d 00000 n \n", $off[$i]);
        $out .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $x . "\n%%EOF";
        return $out;
    }
}

/** Build the whole scorecard PDF from a match_state() result. */
function pdf_scorecard(array $st): string {
    $m = $st['match']; $t1 = $st['team1']; $t2 = $st['team2'];
    $tn = [(int)$t1['id'] => $t1['name'], (int)$t2['id'] => $t2['name']];
    $G = [0.05, 0.40, 0.28]; $GL = [0.90, 0.96, 0.93]; $GR = [0.96, 0.96, 0.96]; $RED = [0.75, 0.12, 0.12]; $GREY = [0.4, 0.4, 0.4];
    $p = new CsPdf();
    $p->footer = $t1['name'] . ' v ' . $t2['name'] . ' - full scorecard - Cric-Scorer';
    $uw = $p->usable();

    // ---- Header ----
    $p->rect(0, 0, CsPdf::W, 78, $G);
    $p->text($p->mx, 16, 'FULL SCORECARD', 8, true, [0.75, 0.93, 0.85]);
    $p->text($p->mx, 28, $t1['name'] . '  vs  ' . $t2['name'], 19, true, [1, 1, 1]);
    $sub = array_filter([
        !empty($m['tournament']['name']) ? $m['tournament']['name'] : null,
        !empty($m['stage']) && $m['stage'] !== 'league' ? ucfirst(str_replace('_', ' ', $m['stage'])) : null,
        $m['match_date'] ? date('D j M Y, g:i a', strtotime($m['match_date'])) : null,
        $m['venue'] ?: null,
        $m['overs'] . ' overs a side',
    ]);
    $p->text($p->mx, 56, implode('  |  ', $sub), 8.5, false, [0.9, 0.97, 0.93]);
    $p->y = 90;
    $info = [];
    if ($m['toss_winner_id']) $info[] = 'Toss: ' . ($tn[(int)$m['toss_winner_id']] ?? '?') . ' won and chose to ' . ($m['toss_decision'] === 'bat' ? 'bat' : 'bowl') . ' first';
    if (!empty($m['scorer']['display_name'])) $info[] = 'Scorer: ' . $m['scorer']['display_name'];
    if ($info) { $p->text($p->mx, $p->y, implode('   |   ', $info), 8.5, false, $GREY); $p->gap(14); }
    if (!empty($m['result_text'])) { foreach ($p->wrap($m['result_text'], $uw, 12, true) as $ln) { $p->text($p->mx, $p->y, $ln, 12, true, $G); $p->gap(15); } }
    elseif ($m['status'] !== 'completed') { $p->text($p->mx, $p->y, 'Match status: ' . $m['status'] . ' (scorecard so far)', 11, true, $RED); $p->gap(15); }
    if (!empty($m['player_of_match']['name'])) { $p->text($p->mx, $p->y, 'Player of the match: ' . $m['player_of_match']['name'], 9.5, true, [0.2, 0.2, 0.2]); $p->gap(14); }
    // innings summary strip
    foreach ($st['innings'] as $inn) {
        $p->text($p->mx, $p->y, ($tn[$inn['batting_team_id']] ?? '?') . '  ' . $inn['runs'] . '/' . $inn['wickets'] . '  (' . $inn['overs'] . ' ov)', 10, true);
        $p->gap(13);
    }
    $p->gap(4);

    $bat = [[ 'Batter', 'How out', 'R', 'B', '4s', '6s', 'SR' ], [150, 195, 32, 32, 28, 28, 40], ['l', 'l', 'r', 'r', 'r', 'r', 'r']];
    $bowl = [[ 'Bowler', 'O', 'M', 'R', 'W', 'Econ', 'Wd', 'Nb', 'Dots' ], [200, 44, 36, 40, 36, 50, 40, 40, 34], ['l', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r']];
    $bw = array_sum($bat[1]);
    $scale = fn(array $w) => array_map(fn($x) => $x * $uw / array_sum($w), $w);
    $batW = $scale($bat[1]); $bowlW = $scale($bowl[1]);

    foreach ($st['innings'] as $inn) {
        $p->need(120);
        $title = (!empty($inn['super']) ? 'Super over' : ordinal_inn($inn['no'])) . ' innings - ' . ($tn[$inn['batting_team_id']] ?? '?');
        $p->rect($p->mx, $p->y, $uw, 20, $G);
        $p->text($p->mx + 6, $p->y + 5, $title, 10.5, true, [1, 1, 1]);
        $p->text($p->mx, $p->y + 5, $inn['runs'] . '/' . $inn['wickets'] . '  (' . $inn['overs'] . ' ov)', 10.5, true, [1, 1, 1], 'r', $uw - 6);
        $p->gap(20);
        if ($inn['target'] !== null) { $p->text($p->mx + 2, $p->y + 2, 'Target ' . $inn['target'] . ' in ' . $inn['max_overs'] . ' overs' . (!empty($inn['dls']) ? ' (DLS)' : ''), 8, false, $GREY); $p->gap(12); }
        $hdr = [$bat[0], $batW, $bat[2], ['bold' => true, 'fill' => $GL, 'size' => 8]];
        $p->row($bat[0], $batW, $bat[2], ['bold' => true, 'fill' => $GL, 'size' => 8, 'repeat' => $hdr]);
        $z = 0;
        foreach ($inn['batting'] as $b) {
            $out = !empty($b['out']);
            $p->row([$b['name'], $b['how'], (string)$b['r'], (string)$b['b'], (string)$b['f4'], (string)$b['f6'], number_format((float)$b['sr'], 1)], $batW, $bat[2],
                ['line' => true, 'fill' => ($z++ % 2) ? $GR : null, 'colors' => [1 => $out ? $GREY : [0.05, 0.45, 0.25]], 'bolds' => [0 => true, 2 => true]]);
        }
        $ex = $inn['extras_detail']; $pen = (int)($ex['pen'] ?? 0);
        $exStr = 'Extras  ' . $inn['extras'] . '   (b ' . $ex['b'] . ', lb ' . $ex['lb'] . ', wd ' . $ex['wd'] . ', nb ' . $ex['nb'] . ($pen ? ', penalty ' . $pen : '') . ')';
        $p->row([$exStr, '', '', '', '', '', ''], $batW, $bat[2], ['line' => true, 'size' => 8.5]);
        $p->row(['Total', $inn['wickets'] . ' wickets, ' . $inn['overs'] . ' overs, run rate ' . number_format((float)$inn['crr'], 2), (string)$inn['runs'], '', '', '', ''], $batW, $bat[2], ['bold' => true, 'fill' => $GL, 'size' => 9]);
        if (!empty($inn['dnb'])) { $p->gap(3); foreach ($p->wrap('Did not bat: ' . implode(', ', array_column($inn['dnb'], 'name')), $uw, 8) as $ln) { $p->need(12); $p->text($p->mx + 2, $p->y, $ln, 8, false, $GREY); $p->gap(11); } }
        if (!empty($inn['fow'])) {
            $p->gap(3);
            $f = array_map(fn($x) => $x['score'] . '-' . $x['wkt'] . ' (' . $x['name'] . ', ' . $x['overs'] . ' ov)', $inn['fow']);
            $p->need(24); $p->text($p->mx + 2, $p->y, 'Fall of wickets', 8.5, true); $p->gap(11);
            foreach ($p->wrap(implode(',  ', $f), $uw - 4, 8) as $ln) { $p->need(11); $p->text($p->mx + 2, $p->y, $ln, 8); $p->gap(10.5); }
        }
        $p->gap(6); $p->need(40);
        $p->row($bowl[0], $bowlW, $bowl[2], ['bold' => true, 'fill' => $GL, 'size' => 8, 'repeat' => [$bowl[0], $bowlW, $bowl[2], ['bold' => true, 'fill' => $GL, 'size' => 8]]]);
        $z = 0;
        foreach ($inn['bowling'] as $b) {
            $p->row([$b['name'], $b['o'], (string)$b['m'], (string)$b['r'], (string)$b['w'], number_format((float)$b['econ'], 2), (string)$b['wd'], (string)$b['nb'], (string)($b['dots'] ?? 0)], $bowlW, $bowl[2],
                ['line' => true, 'fill' => ($z++ % 2) ? $GR : null, 'bolds' => [0 => true, 4 => true]]);
        }
        if (!empty($inn['partnerships'])) {
            $p->gap(8); $p->need(40);
            $p->text($p->mx + 2, $p->y, 'Partnerships', 8.5, true); $p->gap(11);
            foreach ($inn['partnerships'] as $pt) {
                $nm = implode(' & ', array_map(fn($x) => $x['name'] . ' ' . $x['r'] . '(' . $x['b'] . ')', $pt['players'] ?? []));
                $line = 'Wkt ' . $pt['wkt'] . ($pt['unbroken'] ? ' (unbroken)' : '') . ': ' . ($nm !== '' ? $nm . ' - ' : '') . $pt['runs'] . ' runs, ' . $pt['balls'] . ' balls';
                $p->need(11); $p->text($p->mx + 2, $p->y, $line, 8); $p->gap(10.5);
            }
        }

        // ---- Over by over summary ----
        $p->newPage();
        $p->rect($p->mx, $p->y, $uw, 18, $G);
        $p->text($p->mx + 6, $p->y + 4, 'Over by over - ' . ($tn[$inn['batting_team_id']] ?? '?') . ' (' . (!empty($inn['super']) ? 'super over' : ordinal_inn($inn['no']) . ' innings') . ')', 10, true, [1, 1, 1]);
        $p->gap(18);
        $ow = $scale([34, 130, 220, 36, 36, 70]); $oal = ['c', 'l', 'l', 'r', 'r', 'r'];
        $oh = [['Ov', 'Bowler', 'Balls', 'Runs', 'Wkts', 'Score'], $ow, $oal, ['bold' => true, 'fill' => $GL, 'size' => 8]];
        $p->row(...array_merge([$oh[0], $ow, $oal], [$oh[3] + ['repeat' => $oh]]));
        $cr = (int)($inn['penalty_runs'] ?? 0); $cw = 0; $z = 0;
        foreach ($inn['overs_list'] as $ov) {
            $cr += $ov['runs']; $cw += $ov['wkts'];
            $p->row([(string)$ov['over'], $ov['bowler'], implode('  ', $ov['balls']), (string)$ov['runs'], (string)$ov['wkts'], $cr . '/' . $cw], $ow, $oal,
                ['line' => true, 'fill' => ($z++ % 2) ? $GR : null, 'repeat' => $oh, 'bolds' => [3 => true]]);
        }
        $p->gap(10);

        // ---- Every ball ----
        $p->need(40);
        $p->rect($p->mx, $p->y, $uw, 18, $G);
        $p->text($p->mx + 6, $p->y + 4, 'Ball by ball - ' . ($tn[$inn['batting_team_id']] ?? '?') . ' (' . (!empty($inn['super']) ? 'super over' : ordinal_inn($inn['no']) . ' innings') . ')', 10, true, [1, 1, 1]);
        $p->gap(18);
        $bwid = $scale([34, 120, 34, 244, 62]); $bal = ['c', 'l', 'c', 'l', 'r'];
        $bh = [['Ball', 'Bowler to batter', 'Result', 'What happened', 'Score'], $bwid, $bal, ['bold' => true, 'fill' => $GL, 'size' => 8]];
        $run = (int)($inn['penalty_runs'] ?? 0); $wk = 0; $curOver = null; $overMeta = [];
        foreach ($inn['overs_list'] as $ov) $overMeta[$ov['over'] - 1] = $ov;
        $p->row(...array_merge([$bh[0], $bwid, $bal], [$bh[3] + ['repeat' => $bh]]));
        foreach ($inn['timeline'] as $t) {
            if ($curOver !== $t['over_no']) {
                $curOver = $t['over_no']; $om = $overMeta[$curOver] ?? null;
                $p->need(34);
                $p->row(['', 'Over ' . ($curOver + 1) . ($om ? ' - ' . $om['bowler'] : ''), '', $om ? ($om['runs'] . ' run' . ($om['runs'] === 1 ? '' : 's') . ($om['wkts'] ? ', ' . $om['wkts'] . ' wkt' . ($om['wkts'] > 1 ? 's' : '') : '')) : '', ''], $bwid, $bal,
                    ['bold' => true, 'fill' => [0.88, 0.93, 0.97], 'size' => 8.5, 'colors' => [0 => $GREY]]);
            }
            $run += (int)$t['rb'] + (int)$t['xr']; if (!empty($t['w'])) $wk++;
            $isW = !empty($t['w']); $isB = in_array($t['label'], ['4', '6', '4+W', '6+W'], true) || (!$isW && ($t['rb'] ?? 0) >= 4);
            $col = $isW ? $RED : ($isB ? [0.05, 0.4, 0.28] : [0.15, 0.15, 0.15]);
            $desc = trim(preg_replace('/\s+/', ' ', (string)$t['desc']));
            $pre = $t['bowl'] . ' to ' . $t['bat'] . ',';
            if (stripos($desc, $pre) === 0) $desc = ltrim(substr($desc, strlen($pre)));
            $p->row([$t['ov'], $t['bowl'] . ' to ' . $t['bat'], $t['label'], $desc, $run . '/' . $wk], $bwid, $bal,
                ['line' => true, 'wrap' => [3 => true], 'size' => 8, 'repeat' => $bh, 'colors' => [2 => $col, 3 => $isW ? $RED : [0.25, 0.25, 0.25]], 'bolds' => [2 => true, 4 => true]]);
        }
        $p->gap(10);
        if (!empty($inn['fielding'])) {
            $p->need(40); $p->text($p->mx + 2, $p->y, 'Fielding notes', 8.5, true); $p->gap(11);
            foreach ($inn['fielding'] as $fl) {
                $k = $fl['kind'] === 'drop' ? 'dropped catch off ' . $fl['bat'] : ($fl['kind'] === 'saved' ? 'saved ' . $fl['runs'] . ' run(s)' : 'misfield cost ' . $fl['runs'] . ' run(s)');
                $p->need(11); $p->text($p->mx + 2, $p->y, 'Over ' . $fl['ov'] . ': ' . $fl['name'] . ' ' . $k, 8); $p->gap(10.5);
            }
        }
    }
    $p->need(40);
    if (!empty($st['notes']['notes'])) {
        $p->gap(6); $p->text($p->mx, $p->y, 'Match notes', 9.5, true); $p->gap(13);
        foreach ($st['notes']['notes'] as $n) { $txt = is_array($n) ? ($n['text'] ?? implode(' ', $n)) : (string)$n; foreach ($p->wrap('- ' . $txt, $uw, 8.5) as $ln) { $p->need(11); $p->text($p->mx, $p->y, $ln, 8.5); $p->gap(11); } }
    }
    $p->gap(10); $p->need(24);
    $p->text($p->mx, $p->y, 'Generated ' . date('j M Y, g:i a') . ' by Cric-Scorer. This sheet lists every ball as recorded - please check it and report any mistake to the organiser.', 7.5, false, $GREY);
    return $p->output();
}
function ordinal_inn(int $n): string { return [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'][$n] ?? ($n . 'th'); }

/** Admin API: the signed share link key for a match. */
function pdf_action(string $action, array $u, array $in): array {
    $id = (int)($in['id'] ?? 0); load_match($id);
    return ['id' => $id, 'k' => pdf_token($id)];
}

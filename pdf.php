<?php
/* Cric-Scorer - full scorecard PDF (v1.44). Open to the administrator, or to anyone holding the signed link the admin shared (k=...). */
require_once __DIR__ . '/inc/core.php';
require_once __DIR__ . '/inc/engine.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
try {
    ensure_schema();
    $u = current_user();
    $isAdmin = $u && !empty($u['is_admin']);
    if (isset($_GET['sheets'])) {   // v1.46: printable paper score sheets for upcoming matches (admin) - ?sheets=12,13  or  ?sheets=blank
        if (!$isAdmin) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Only the administrator can print score sheets.'; exit; }
        $list = [];
        if ($_GET['sheets'] === 'blank') $list = [null];
        else foreach (array_slice(array_unique(array_filter(array_map('intval', explode(',', (string)$_GET['sheets'])))), 0, 40) as $mid) $list[] = match_state($mid, $u);
        if (!$list) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'No matches chosen.'; exit; }
        $pdf = pdf_score_sheets($list);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="score-sheets.pdf"');
        header('Content-Length: ' . strlen($pdf)); echo $pdf; exit;
    }
    $id = (int)($_GET['id'] ?? 0);
    if (!$id || !(pdf_token_ok($id, (string)($_GET['k'] ?? '')) || $isAdmin)) {
        http_response_code(403); header('Content-Type: text/plain; charset=utf-8');
        echo "This scorecard link is not valid. Ask the organiser for a new link."; exit;
    }
    $viewer = $isAdmin ? $u : ['id' => 0, 'is_admin' => 1];   // the signed link is the permission (even for a private match)
    $st = match_state($id, $viewer);
    if (empty($st['innings'])) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'This match has not started yet - no scorecard to print.'; exit; }
    $pdf = pdf_scorecard($st);
    $name = preg_replace('/[^A-Za-z0-9]+/', '-', $st['team1']['name'] . '-vs-' . $st['team2']['name']);
    $name = trim($name, '-') . '-scorecard-' . $id . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $name . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
} catch (Throwable $e) {
    error_log('Cric-Scorer pdf.php: ' . $e->getMessage());
    http_response_code(500); header('Content-Type: text/plain; charset=utf-8');
    echo 'The scorecard could not be made just now. Please try again in a minute.';
}

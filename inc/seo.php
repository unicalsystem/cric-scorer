<?php
/* Cric-Scorer - SEO helpers: meta tags, share previews, structured data, public page layout */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/version.php';

// Safe defaults for servers that still have an older config.php (update zips never replace it)
if (!defined('SITE_URL')) {
    $h = preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST'] ?? 'cricscorerapp.com');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    define('SITE_URL', ($https ? 'https' : 'http') . '://' . $h . $dir);
}
defined('CONTACT_EMAIL')     || define('CONTACT_EMAIL', 'privacy@cricscorerapp.com');
defined('ORG_NAME')          || define('ORG_NAME', 'Unical Systems Private Limited');
defined('ORG_ADDRESS')       || define('ORG_ADDRESS', '206, 3rd Floor, DSL Abacus IT Park, IDA Uppal, Hyderabad, Telangana 500039, India');
defined('GRIEVANCE_OFFICER') || define('GRIEVANCE_OFFICER', 'Shiva Kaluvakota');
defined('POLICY_UPDATED')    || define('POLICY_UPDATED', '14 November 2026');

const SEO_DEFAULT_TITLE = 'Cric-Scorer - Free Cricket Scoring App with Live Scores, Tournaments & Cricket Lessons';
const SEO_DEFAULT_DESC = 'Score cricket matches ball by ball for free on your phone. Live scorecards, commentary, tournaments with points tables, rain rules (DLS), IPL & international squads and a cricket academy for kids. No download needed.';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function site_url(string $path = ''): string { return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/'); }

/** <head> tags for a page: title, description, canonical, Open Graph, Twitter, robots, JSON-LD */
function seo_head(array $o): string {
    $title = $o['title'] ?? SEO_DEFAULT_TITLE;
    $desc = $o['description'] ?? SEO_DEFAULT_DESC;
    $url = site_url($o['path'] ?? '');
    $img = $o['image'] ?? site_url('assets/og-image.png');
    $h = '<title>' . e($title) . "</title>\n"
       . '<meta name="description" content="' . e($desc) . "\">\n"
       . '<link rel="canonical" href="' . e($url) . "\">\n"
       . '<meta name="robots" content="' . (!empty($o['noindex']) ? 'noindex, nofollow' : 'index, follow, max-image-preview:large') . "\">\n"
       . '<meta property="og:site_name" content="Cric-Scorer by Aaryush">' . "\n"
       . '<meta property="og:type" content="' . e($o['type'] ?? 'website') . "\">\n"
       . '<meta property="og:title" content="' . e($o['og_title'] ?? $title) . "\">\n"
       . '<meta property="og:description" content="' . e($desc) . "\">\n"
       . '<meta property="og:url" content="' . e($url) . "\">\n"
       . '<meta property="og:image" content="' . e($img) . "\">\n"
       . '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n"
       . '<meta property="og:image:alt" content="Cric-Scorer - free cricket scoring app">' . "\n"
       . '<meta property="og:locale" content="en_IN">' . "\n"
       . '<meta name="twitter:card" content="summary_large_image">' . "\n"
       . '<meta name="twitter:title" content="' . e($o['og_title'] ?? $title) . "\">\n"
       . '<meta name="twitter:description" content="' . e($desc) . "\">\n"
       . '<meta name="twitter:image" content="' . e($img) . "\">\n";
    foreach ($o['jsonld'] ?? [] as $j) {
        $h .= '<script type="application/ld+json">' . json_encode($j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
    }
    return $h;
}

function ld_organization(): array {
    return ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => ORG_NAME, 'url' => 'https://unical.in',
            'logo' => site_url('assets/icons/icon-512.png'), 'email' => CONTACT_EMAIL,
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => '206, 3rd Floor, DSL Abacus IT Park, IDA Uppal', 'addressLocality' => 'Hyderabad',
                          'addressRegion' => 'Telangana', 'postalCode' => '500039', 'addressCountry' => 'IN']];
}
function ld_app(): array {
    return ['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Cric-Scorer', 'alternateName' => 'Cric-Scorer by Aaryush',
            'url' => site_url(), 'applicationCategory' => 'SportsApplication', 'operatingSystem' => 'Android, iOS, Windows, macOS (web browser)',
            'browserRequirements' => 'Requires a modern web browser', 'inLanguage' => 'en', 'isAccessibleForFree' => true,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'],
            'description' => SEO_DEFAULT_DESC, 'image' => site_url('assets/og-image.png'),
            'publisher' => ['@type' => 'Organization', 'name' => ORG_NAME],
            'featureList' => ['Ball-by-ball cricket scoring', 'Live scorecards and commentary', 'Tournaments with points table and net run rate',
                              'DLS method for rain-affected matches', 'IPL and international team squads', 'Cricket lessons and quizzes for kids',
                              'Player profiles, follow players, private matches', 'Works offline as an installable app']];
}
function ld_breadcrumbs(array $items): array {
    $list = []; $i = 0;
    foreach ($items as [$name, $path]) $list[] = ['@type' => 'ListItem', 'position' => ++$i, 'name' => $name, 'item' => site_url($path)];
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/* ---------- Academy lessons as public pages ---------- */
function seo_slug(string $s): string {
    $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $s), '-'));
    return $s === '' ? 'lesson' : $s;
}
/** All lessons with a stable unique slug, e.g. "lbw-explained" */
function public_lessons(): array {
    static $list = null;
    if ($list !== null) return $list;
    $d = json_decode((string)@file_get_contents(__DIR__ . '/../assets/academy.json'), true) ?: ['modules' => []];
    $list = []; $seen = [];
    foreach ($d['modules'] as $mi => $m) foreach ($m['lessons'] as $li => $l) {
        $slug = seo_slug($l['title']);
        if (isset($seen[$slug])) $slug .= '-' . $l['id'];
        $seen[$slug] = true;
        $list[] = ['slug' => $slug, 'id' => $l['id'], 'n' => ($mi + 1) . '.' . ($li + 1), 'lesson' => $l, 'module' => $m, 'module_n' => $mi + 1];
    }
    return $list;
}
function lesson_url(array $pl): string { return 'learn/' . $pl['slug']; }
function plain_text(string $s): string { return trim(str_replace('**', '', $s)); }
function rich_text(string $s): string { return preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', e($s)); }

/* ---------- Layout for public pages (privacy, terms, learn, match) ---------- */
function page_start(array $o): void {
    $v = APP_VERSION;
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n"
       . seo_head($o)
       . '<meta name="theme-color" content="#0d4a36">' . "\n"
       . '<link rel="icon" type="image/png" sizes="192x192" href="' . e(site_url('assets/icons/icon-192.png')) . "\">\n"
       . '<link rel="apple-touch-icon" href="' . e(site_url('assets/icons/icon-180.png')) . "\">\n"
       . '<link rel="manifest" href="' . e(site_url('manifest.json')) . "\">\n"
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
       . '<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Nunito:wght@500;600;700;800;900&display=swap" rel="stylesheet">' . "\n"
       . '<link rel="stylesheet" href="' . e(site_url('assets/site.css?v=' . $v)) . "\">\n</head>\n<body>\n"
       . '<header class="site-h"><a class="brand" href="' . e(site_url()) . '"><img src="' . e(site_url('assets/icons/icon-192.png')) . '" alt="" width="36" height="36"><span><b>Cric-Scorer</b><i>by Aaryush</i></span></a>'
       . '<nav><a href="' . e(site_url('learn/')) . '">Learn cricket</a><a class="cta" href="' . e(site_url('#/register')) . '">Start free</a></nav></header>' . "\n<main class=\"site-m\">\n";
}
function page_end(): void {
    $y = date('Y');
    echo "</main>\n<footer class=\"site-f\"><div class=\"links\">"
       . '<a href="' . e(site_url()) . '">Home</a><a href="' . e(site_url('learn/')) . '">Learn cricket</a>'
       . '<a href="' . e(site_url('privacy')) . '">Privacy Policy</a><a href="' . e(site_url('terms')) . '">Terms of Use</a>'
       . '<a href="mailto:' . e(CONTACT_EMAIL) . '">Contact</a></div>'
       . '<p>© ' . $y . ' ' . e(ORG_NAME) . ' · Cric-Scorer by Aaryush is free to use.</p></footer>' . "\n</body>\n</html>";
}

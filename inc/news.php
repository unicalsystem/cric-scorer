<?php
/**
 * Cricket news - v1.28
 * Real-world cricket headlines from open RSS feeds (no API key): ESPNcricinfo's news feed plus a Google News
 * "cricket" search for freshness. The server fetches at most every 20 minutes and keeps the result in
 * data/cache/news.json (blocked from the web by data/.htaccess), so phones never call the feeds directly.
 * Only headline, source, time, link and thumbnail are shown - tapping opens the publisher's article.
 */
declare(strict_types=1);

const NEWS_FEEDS = [
    ['url' => 'https://www.espncricinfo.com/rss/content/story/feeds/0.xml', 'source' => 'ESPNcricinfo'],
    ['url' => 'https://news.google.com/rss/search?q=cricket+when:2d&hl=en-IN&gl=IN&ceid=IN:en', 'source' => null],   // source per item
];
const NEWS_TTL = 1200;      // seconds
const NEWS_KEEP = 25;

function news_cache_file(): string { return dirname(__DIR__) . '/data/cache/news.json'; }

/** Parse one RSS document into items */
function news_parse(string $xml, ?string $source): array {
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if (!$doc || !isset($doc->channel->item)) return [];
    $out = [];
    foreach ($doc->channel->item as $it) {
        $title = trim(html_entity_decode(strip_tags((string)$it->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $link = trim((string)$it->link);
        if ($title === '' || !preg_match('#^https?://#', $link)) continue;
        $src = $source;
        if (!$src) {                                       // Google News: "Headline - Publisher" + <source>
            $src = trim((string)$it->source) ?: null;
            if ($src && str_ends_with($title, ' - ' . $src)) $title = substr($title, 0, -strlen(' - ' . $src));
        }
        $ts = strtotime((string)$it->pubDate) ?: 0;
        $img = null;
        if (isset($it->enclosure) && preg_match('#^https://#', (string)$it->enclosure['url'])) $img = (string)$it->enclosure['url'];
        if (!$img && preg_match('#https://[^\s"<>]+?\.(?:jpe?g|png|webp)(?:\?[^\s"<>]*)?#i', $it->asXML() ?: '', $m)) $img = html_entity_decode($m[0]);
        $out[] = ['title' => mb_substr($title, 0, 200), 'link' => $link, 'source' => $src ?: 'News', 'ts' => $ts, 'image' => $img];
    }
    return $out;
}

/** Plain GET (not the updater's http_get, which adds the GitHub token) */
function news_http(string $url): array {
    $ua = 'Mozilla/5.0 (compatible; CricScorer/' . APP_VERSION . '; +' . SITE_URL . ')';
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true, 'header' => "User-Agent: $ua\r\nAccept: application/rss+xml, application/xml, text/xml\r\n", 'follow_location' => 1]]);
        $body = @file_get_contents($url, false, $ctx); $code = 0;
        foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $code = (int)$m[1];
        return ['code' => $code, 'body' => $body === false ? null : $body];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => $ua, CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_ENCODING => '']);
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $code, 'body' => $body === false ? null : $body];
}

/** Fetch every feed, merge newest first, drop duplicates and stale stories */
function news_fetch(): array {
    $all = [];
    foreach (NEWS_FEEDS as $f) {
        $r = news_http($f['url']);
        if (($r['code'] ?? 0) === 200 && !empty($r['body'])) $all = array_merge($all, news_parse($r['body'], $f['source']));
    }
    usort($all, fn($a, $b) => $b['ts'] <=> $a['ts']);
    $seen = []; $out = [];
    $cut = time() - 7 * 86400;                          // nothing older than a week
    foreach ($all as $n) {
        if ($n['ts'] && $n['ts'] < $cut) continue;
        $k = preg_replace('/[^a-z0-9]+/', '', strtolower($n['title']));
        $k = substr($k, 0, 60);
        if (isset($seen[$k])) continue;
        $seen[$k] = true; $out[] = $n;
        if (count($out) >= NEWS_KEEP) break;
    }
    return $out;
}

/** Cached news; refreshes when older than NEWS_TTL, falls back to the last good copy on failure */
function cricket_news(int $limit = 20): array {
    $file = news_cache_file();
    $cache = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: null) : null;
    if (!$cache || time() - (int)($cache['at'] ?? 0) > NEWS_TTL) {
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        // claim the refresh first so a burst of visitors doesn't hit the feeds at once
        $stale = $cache ?: ['at' => 0, 'items' => []];
        @file_put_contents($file, json_encode(['at' => time(), 'items' => $stale['items']]), LOCK_EX);
        $items = [];
        try { $items = news_fetch(); } catch (Throwable $e) { error_log('Cric-Scorer news: ' . $e->getMessage()); }
        if ($items) { $cache = ['at' => time(), 'items' => $items]; @file_put_contents($file, json_encode($cache), LOCK_EX); }
        else { $cache = ['at' => time() - NEWS_TTL + 300, 'items' => $stale['items']]; @file_put_contents($file, json_encode($cache), LOCK_EX); }   // retry in 5 min
    }
    return ['items' => array_slice($cache['items'] ?? [], 0, max(1, min(NEWS_KEEP, $limit))), 'updated' => (int)($cache['at'] ?? 0)];
}

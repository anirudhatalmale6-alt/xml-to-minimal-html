<?php
/**
 * Server-side jobs renderer for Horse Checker.
 *
 * Drop this where your listings go (either rename your page to .php and
 * `include 'jobs.php';` inside the <div class="job-listings">, or use an
 * Apache SSI include). It fetches the Careers in Racing RSS feed, caches it
 * to a local file, and prints the job listings as real HTML — so search
 * engines see genuine markup, and it updates itself with no cron and no clicks.
 *
 * Rebuilds from the current feed each time, so jobs that leave the feed
 * disappear automatically. The cache means the feed is only hit occasionally.
 *
 * Fail-safe: the feed is validated as real RSS before it is ever stored or
 * used, and the last good set of listings is kept on disk. If the feed goes
 * down, redirects elsewhere, or starts returning a web page instead of XML,
 * the page keeps showing the last known jobs rather than going blank.
 *
 * Diagnostics: append ?jobsdiag=<the value of $DIAG_KEY below> to the page URL
 * to see exactly what the server received from the feed.
 */

$FEED_URL   = 'https://jobs.careersinracing.com/jobsrss/?Sector=1&countrycode=GB';
$CACHE_FILE = __DIR__ . '/feed-cache.xml';
$CACHE_TTL  = 3600; // seconds (1 hour). Raise/lower to taste.
$SEEN_FILE  = __DIR__ . '/seen.json';       // remembers when each job first appeared
$LAST_GOOD  = __DIR__ . '/jobs-last-good.html'; // last successfully rendered listings
$NEW_DAYS   = 3;     // how many days a newly-added job shows the "New" tag
$NEW_LABEL  = 'New'; // wording of the tag (e.g. 'New', 'New Job', 'Just Added')
$DIAG_KEY   = 'hc-feed-check';

$HC_DIAG = [];

/**
 * Does this payload actually look like the jobs RSS feed?
 * Guards against error pages, redirects to a homepage, WAF interstitials and
 * truncated downloads being mistaken for a feed.
 */
function hc_is_feed($data) {
    if (!is_string($data) || strlen($data) < 200) return false;
    if (stripos(ltrim($data), '<!doctype html') === 0) return false;
    if (stripos($data, '<html') !== false && stripos($data, '<rss') === false) return false;
    $rss = @simplexml_load_string($data);
    return ($rss && isset($rss->channel->item) && count($rss->channel->item) > 0);
}

function hc_fetch_url($url) {
    global $HC_DIAG;

    $headers = [
        'Accept: application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
        'Accept-Language: en-GB,en;q=0.9',
        'Cache-Control: no-cache',
    ];
    // Two attempts: a browser-like agent first, then the plain one. Some feed
    // hosts filter unfamiliar agents.
    $agents = [
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        'HorseChecker-Jobs/1.0',
    ];

    foreach ($agents as $ua) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_ENCODING       => '',      // accept gzip
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $data  = curl_exec($ch);
            $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $type  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $err   = curl_error($ch);
            curl_close($ch);

            $HC_DIAG[] = [
                'via' => 'curl', 'ua' => $ua, 'http' => $code, 'type' => $type,
                'bytes' => is_string($data) ? strlen($data) : 0, 'final' => $final,
                'error' => $err, 'looks_like_feed' => hc_is_feed($data) ? 'yes' : 'no',
                'starts' => is_string($data) ? substr(preg_replace('/\s+/', ' ', $data), 0, 160) : '',
            ];

            if ($code >= 200 && $code < 300 && hc_is_feed($data)) return $data;
        }

        // Fallback: file_get_contents (only works if allow_url_fopen is on)
        $ctx = stream_context_create(['http' => [
            'timeout' => 20,
            'header'  => "User-Agent: $ua\r\n" . implode("\r\n", $headers) . "\r\n",
        ]]);
        $data = @file_get_contents($url, false, $ctx);
        $HC_DIAG[] = [
            'via' => 'file_get_contents', 'ua' => $ua,
            'bytes' => is_string($data) ? strlen($data) : 0,
            'looks_like_feed' => hc_is_feed($data) ? 'yes' : 'no',
        ];
        if (hc_is_feed($data)) return $data;
    }

    return '';
}

/**
 * Returns valid feed XML, or '' if none can be had.
 * The cache is only ever written with content that parses as a real feed, so a
 * bad response can never poison it.
 */
function hc_load_feed($url, $cache, $ttl) {
    global $HC_DIAG;

    $cached = is_readable($cache) ? (string) file_get_contents($cache) : '';
    $fresh  = ($cached !== '' && (time() - filemtime($cache) < $ttl));

    if ($fresh && hc_is_feed($cached)) {
        $HC_DIAG[] = ['step' => 'served from cache', 'age_seconds' => time() - filemtime($cache)];
        return $cached;
    }

    $xml = hc_fetch_url($url);
    if (hc_is_feed($xml)) {
        @file_put_contents($cache, $xml, LOCK_EX);
        return $xml;
    }

    // Feed unreachable or not a feed — fall back to the last good copy, however old.
    if (hc_is_feed($cached)) {
        $HC_DIAG[] = ['step' => 'feed unavailable, using last good cache',
                      'age_seconds' => time() - filemtime($cache)];
        return $cached;
    }

    $HC_DIAG[] = ['step' => 'no feed and no usable cache'];
    return '';
}

function hc_clean($s) { return trim(preg_replace('/\s+/', ' ', (string) $s)); }

$xml = hc_load_feed($FEED_URL, $CACHE_FILE, $CACHE_TTL);
$rss = $xml !== '' ? @simplexml_load_string($xml) : false;

if ($rss && isset($rss->channel->item) && count($rss->channel->item) > 0) {
    $now      = time();
    $firstRun = !is_readable($SEEN_FILE);
    $seen     = $firstRun ? [] : (json_decode(@file_get_contents($SEEN_FILE), true) ?: []);
    $current  = [];
    $out      = '';

    foreach ($rss->channel->item as $item) {
        $title = hc_clean($item->title);
        $desc  = hc_clean($item->description);
        $link  = hc_clean($item->link);
        if ($title === '' && $desc === '' && $link === '') continue;

        $id = $link !== '' ? $link : $title;      // stable identifier per job
        $ts = strtotime((string) $item->pubDate) ?: $now;

        // When did this job first appear on the page?
        if (isset($seen[$id])) {
            $first = (int) $seen[$id];
        } else {
            // On the very first run, seed from the posting date so the whole
            // list isn't flagged "New" at once. After that, a job counts as new
            // from the moment it first shows up here.
            $first = $firstRun ? min($ts, $now) : $now;
        }
        $current[$id] = $first;

        $isNew  = ($now - $first) < $NEW_DAYS * 86400 ? $NEW_LABEL : '';
        $iso    = gmdate('Y-m-d\TH:i:s\Z', $ts);
        $posted = 'Posted: ' . date('l, d M Y', $ts);

        $L = htmlspecialchars($link, ENT_QUOTES);
        $T = htmlspecialchars($title, ENT_QUOTES);
        $D = htmlspecialchars($desc, ENT_QUOTES);
        $P = htmlspecialchars($posted, ENT_QUOTES);
        $out .= <<<HTML
  <article class="job-item">
    <h2><a href="$L" target="_blank" rel="nofollow">$T</a></h2>
    <p class="job-description">$D</p>
    <p class="new">$isNew</p>
    <a href="$L" target="_blank" rel="nofollow" class="apply-link"><button type="button" class="custom-btn">Apply Here</button></a>
    <time datetime="$iso">$P</time>
  </article>

HTML;
    }

    if (trim($out) !== '') {
        echo $out;
        // Keep a copy so the page can never go blank if the feed misbehaves.
        @file_put_contents($LAST_GOOD, $out, LOCK_EX);
        // Remember what's on the page now; also prunes jobs that have left the
        // feed (so if one ever returns, it's treated as new again).
        @file_put_contents($SEEN_FILE, json_encode($current), LOCK_EX);
    } elseif (is_readable($LAST_GOOD)) {
        echo "<!-- jobs: feed returned no usable items, showing last known listings -->\n";
        echo file_get_contents($LAST_GOOD);
    }
} elseif (is_readable($LAST_GOOD)) {
    // Feed unreachable and no valid cache — show the last known listings rather
    // than an empty page.
    echo "<!-- jobs: feed unavailable, showing last known listings -->\n";
    echo file_get_contents($LAST_GOOD);
} else {
    echo "<!-- jobs: feed unavailable and no previous listings stored -->\n";
}

// ---- Diagnostics -----------------------------------------------------------
// Visit the page with ?jobsdiag=<key> to see what the server got from the feed.
if (isset($_GET['jobsdiag']) && $_GET['jobsdiag'] === $DIAG_KEY) {
    clearstatcache(); // otherwise sizes/times read before this request's writes
    echo "\n<pre style=\"grid-column:1/-1;white-space:pre-wrap;font:12px/1.5 monospace;"
       . "background:#111;color:#0f0;padding:14px;border-radius:6px\">\n";
    echo "FEED: " . htmlspecialchars($FEED_URL) . "\n";
    echo "PHP: " . PHP_VERSION . "  curl: " . (function_exists('curl_init') ? 'yes' : 'no')
       . "  allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'on' : 'off') . "\n";
    echo "cache: " . (is_readable($CACHE_FILE)
        ? filesize($CACHE_FILE) . " bytes, age " . (time() - filemtime($CACHE_FILE)) . "s, valid feed: "
          . (hc_is_feed(file_get_contents($CACHE_FILE)) ? 'yes' : 'NO')
        : 'missing') . "\n";
    echo "folder writable: " . (is_writable(__DIR__) ? 'yes' : 'NO') . "\n";
    echo "last-good listings: " . (is_readable($LAST_GOOD) ? filesize($LAST_GOOD) . " bytes" : 'none') . "\n\n";
    foreach ($HC_DIAG as $i => $d) {
        echo "[" . ($i + 1) . "] ";
        foreach ($d as $k => $v) echo $k . '=' . htmlspecialchars((string) $v) . '  ';
        echo "\n";
    }
    echo "</pre>\n";
}

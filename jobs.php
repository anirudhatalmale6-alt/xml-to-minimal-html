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
 */

$FEED_URL   = 'https://jobs.careersinracing.com/jobsrss/?Sector=1&countrycode=GB';
$CACHE_FILE = __DIR__ . '/feed-cache.xml';
$CACHE_TTL  = 3600; // seconds (1 hour). Raise/lower to taste.
$SEEN_FILE  = __DIR__ . '/seen.json'; // remembers when each job first appeared
$NEW_DAYS   = 3;     // how many days a newly-added job shows the "New" tag
$NEW_LABEL  = 'New'; // wording of the tag (e.g. 'New', 'New Job', 'Just Added')

function hc_fetch_url($url) {
    // cURL first (works even when allow_url_fopen is disabled on the host)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'HorseChecker-Jobs/1.0',
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($data !== false && $code >= 200 && $code < 400) return $data;
    }
    // Fallback: file_get_contents (only works if allow_url_fopen is on)
    $ctx = stream_context_create(['http' => [
        'timeout' => 20,
        'header'  => "User-Agent: HorseChecker-Jobs/1.0\r\n",
    ]]);
    $data = @file_get_contents($url, false, $ctx);
    return $data === false ? '' : $data;
}

function hc_load_feed($url, $cache, $ttl) {
    if (is_readable($cache) && (time() - filemtime($cache) < $ttl)) {
        return file_get_contents($cache);
    }
    $xml = hc_fetch_url($url);
    if ($xml === '') {                     // feed down? fall back to last good copy
        return is_readable($cache) ? file_get_contents($cache) : '';
    }
    @file_put_contents($cache, $xml);
    return $xml;
}

function hc_clean($s) { return trim(preg_replace('/\s+/', ' ', (string)$s)); }

$xml = hc_load_feed($FEED_URL, $CACHE_FILE, $CACHE_TTL);
$rss = $xml ? @simplexml_load_string($xml) : false;

if ($rss && isset($rss->channel->item)) {
    $now      = time();
    $firstRun = !is_readable($SEEN_FILE);
    $seen     = $firstRun ? [] : (json_decode(@file_get_contents($SEEN_FILE), true) ?: []);
    $current  = [];

    foreach ($rss->channel->item as $item) {
        $title = hc_clean($item->title);
        $desc  = hc_clean($item->description);
        $link  = hc_clean($item->link);
        if ($title === '' && $desc === '' && $link === '') continue;

        $id = $link !== '' ? $link : $title;      // stable identifier per job
        $ts = strtotime((string)$item->pubDate) ?: $now;

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
        echo <<<HTML
  <article class="job-item">
    <h2><a href="$L" target="_blank" rel="nofollow">$T</a></h2>
    <p class="job-description">$D</p>
    <p class="new">$isNew</p>
    <a href="$L" target="_blank" rel="nofollow" class="apply-link"><button type="button" class="custom-btn">Apply Here</button></a>
    <time datetime="$iso">$P</time>
  </article>

HTML;
    }

    // Remember what's on the page now; also prunes jobs that have left the feed
    // (so if one ever returns, it's treated as new again).
    @file_put_contents($SEEN_FILE, json_encode($current), LOCK_EX);
}

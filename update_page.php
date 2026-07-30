<?php
/**
 * update_page.php — fully-automatic updater for the Horse Checker jobs page.
 *
 * Run by a cron job. It fetches the Careers in Racing RSS feed and writes the
 * job listings straight into your existing .html page, in place, between two
 * marker comments. Your page stays a normal static .html file — no renaming,
 * no changing any links, and search engines still see real HTML.
 *
 * Rebuilds from the current feed every run, so jobs that leave the feed drop
 * off the page automatically.
 *
 * USAGE (command line / cron):
 *   php update_page.php /full/path/to/jobs-in-racing.html
 *
 * ONE-TIME: inside your <div class="job-listings"> put these two markers
 * (delete your current manual listings between them):
 *   <!-- JOBS:START -->
 *   <!-- JOBS:END -->
 */

$FEED_URL = 'https://jobs.careersinracing.com/jobsrss/?Sector=1&countrycode=GB';
$START = '<!-- JOBS:START -->';
$END   = '<!-- JOBS:END -->';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php update_page.php /path/to/jobs-in-racing.html [feed_url]\n");
    exit(1);
}
$page = $argv[1];
$feed = $argc > 2 ? $argv[2] : $FEED_URL;

function hc_clean($s) { return trim(preg_replace('/\s+/', ' ', (string)$s)); }

$ctx = stream_context_create(['http' => [
    'timeout' => 20,
    'header'  => "User-Agent: HorseChecker-Jobs/1.0\r\n",
]]);
$xml = @file_get_contents($feed, false, $ctx);
if ($xml === false) { fwrite(STDERR, "Could not fetch feed: $feed\n"); exit(1); }

$rss = @simplexml_load_string($xml);
if (!$rss || !isset($rss->channel->item)) { fwrite(STDERR, "No items in feed.\n"); exit(1); }

$today = date('Y-m-d');
$blocks = [];
foreach ($rss->channel->item as $item) {
    $title = hc_clean($item->title);
    $desc  = hc_clean($item->description);
    $link  = hc_clean($item->link);
    if ($title === '' && $desc === '' && $link === '') continue;

    $ts     = strtotime((string)$item->pubDate) ?: time();
    $iso    = gmdate('Y-m-d\TH:i:s\Z', $ts);
    $posted = 'Posted: ' . date('l, d M Y', $ts);
    $isNew  = (date('Y-m-d', $ts) === $today) ? 'New Today' : '';

    $L = htmlspecialchars($link, ENT_QUOTES);
    $T = htmlspecialchars($title, ENT_QUOTES);
    $D = htmlspecialchars($desc, ENT_QUOTES);
    $P = htmlspecialchars($posted, ENT_QUOTES);
    $blocks[] = <<<HTML
  <article class="job-item">
    <h2><a href="$L" target="_blank" rel="nofollow">$T</a></h2>
    <p class="job-description">$D</p>
    <p class="new">$isNew</p>
    <a href="$L" target="_blank" rel="nofollow" class="apply-link"><button type="button" class="custom-btn">Apply Here</button></a>
    <time datetime="$iso">$P</time>
  </article>
HTML;
}
$block = implode("\n", $blocks);

$html = @file_get_contents($page);
if ($html === false) { fwrite(STDERR, "Cannot read page: $page\n"); exit(1); }

$sPos = strpos($html, $START);
$ePos = strpos($html, $END);
if ($sPos === false || $ePos === false || $ePos < $sPos) {
    fwrite(STDERR, "Markers not found. Add these once inside your listings div:\n  $START\n  $END\n");
    exit(1);
}

$before = substr($html, 0, $sPos);
$after  = substr($html, $ePos + strlen($END));
$new    = $before . $START . "\n" . $block . "\n    " . $END . $after;

if (@file_put_contents($page, $new) === false) {
    fwrite(STDERR, "Cannot write page: $page (check file permissions)\n");
    exit(1);
}
echo "Updated $page with " . count($blocks) . " jobs.\n";

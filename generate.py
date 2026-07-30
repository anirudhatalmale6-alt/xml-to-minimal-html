#!/usr/bin/env python3
"""
Careers-in-Racing RSS  ->  Horse Checker jobs HTML.

Fetches the live jobs RSS feed and writes a block of static HTML that matches
the existing markup on https://horsechecker.com/jobs-in-racing — one
<article class="job-item"> per job, using your current classes so your
existing CSS styles it automatically. No feed widget / script, just plain
HTML that's good for SEO.

Run it once a day:

    python3 generate.py

It writes:
    jobs.html      -> the listing block to paste into your page
    preview.html   -> a standalone page so you can eyeball it in a browser

Only the Python 3 standard library is used — nothing to install.
"""

import html
import re
import sys
from datetime import datetime, timezone
from email.utils import parsedate_to_datetime
from urllib.request import Request, urlopen
import xml.etree.ElementTree as ET

# ---------------------------------------------------------------------------
# Settings — the only things you might ever want to change.
# ---------------------------------------------------------------------------
FEED_URL = "https://jobs.careersinracing.com/jobsrss/?Sector=1&countrycode=GB"
JOBS_OUT = "jobs.html"       # the block to paste into your page
PREVIEW_OUT = "preview.html"  # standalone page for a quick visual check
# ---------------------------------------------------------------------------


def fetch_feed(url):
    """Download the RSS feed. Falls back to a local file if given one."""
    if url.startswith(("http://", "https://")):
        req = Request(url, headers={"User-Agent": "HorseChecker-Jobs/1.0"})
        with urlopen(req, timeout=30) as resp:
            return resp.read()
    with open(url, "rb") as fh:
        return fh.read()


def clean(text):
    """Collapse the feed's line breaks / stray spaces into one tidy line."""
    return re.sub(r"\s+", " ", (text or "")).strip()


def item_text(item, tag):
    el = item.find(tag)
    return clean(el.text if el is not None else "")


def posted_bits(item):
    """Return (iso_datetime, 'Posted: Weekday, DD Mon YYYY', is_today)."""
    raw = item.findtext("pubDate", "")
    try:
        dt = parsedate_to_datetime(raw)
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=timezone.utc)
    except (TypeError, ValueError):
        dt = datetime.now(timezone.utc)
    iso = dt.strftime("%Y-%m-%dT%H:%M:%SZ")
    nice = dt.strftime("Posted: %A, %d %b %Y")
    is_today = dt.date() == datetime.now(dt.tzinfo).date()
    return iso, nice, is_today


ARTICLE = """\
  <article class="job-item">
    <h2><a href="{link}" target="_blank" rel="nofollow">{title}</a></h2>
    <p class="job-description">{desc}</p>
    <p class="new">{new}</p>
    <a href="{link}" target="_blank" rel="nofollow" class="apply-link"><button type="button" class="custom-btn">Apply Here</button></a>
    <time datetime="{iso}">{posted}</time>
  </article>"""


def build_articles(xml_bytes):
    root = ET.fromstring(xml_bytes)
    articles = []
    for item in root.iter("item"):
        title = item_text(item, "title")
        desc = item_text(item, "description")
        link = item_text(item, "link")
        if not (title or desc or link):
            continue
        iso, posted, is_today = posted_bits(item)
        articles.append(
            ARTICLE.format(
                link=html.escape(link, quote=True),
                title=html.escape(title),
                desc=html.escape(desc),
                new="New Today" if is_today else "",
                iso=iso,
                posted=html.escape(posted),
            )
        )
    return articles


PREVIEW_CSS = """\
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;
color:#1a1a1a;line-height:1.55;max-width:44rem;margin:0 auto;padding:2.5rem 1.25rem 4rem}
h1{font-size:1.5rem;margin:0 0 1.5rem}
.job-item{padding:1.4rem 0;border-top:1px solid #e6e6e6}
.job-item h2{font-size:1.15rem;margin:0 0 .4rem}
.job-item h2 a{color:#12492f;text-decoration:none}
.job-item h2 a:hover{text-decoration:underline}
.job-description{margin:0 0 .7rem;color:#333}
.new{margin:0 0 .6rem;color:#0a7d3c;font-weight:600;font-size:.85rem}
.new:empty{display:none}
.custom-btn{border:1px solid #12492f;background:#12492f;color:#fff;padding:.45rem .9rem;
border-radius:4px;font-size:.85rem;cursor:pointer}
.apply-link{text-decoration:none}
time{display:block;margin-top:.6rem;color:#777;font-size:.8rem}"""


def write_preview(articles, count):
    page = (
        "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
        "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        "<title>Equine and Racing Job Listings</title>\n<style>\n"
        + PREVIEW_CSS
        + "\n</style>\n</head>\n<body>\n"
        + f"<h1>Equine and Racing Job Listings ({count})</h1>\n"
        + '<div class="job-listings">\n'
        + "\n".join(articles)
        + "\n</div>\n</body>\n</html>\n"
    )
    with open(PREVIEW_OUT, "w", encoding="utf-8") as fh:
        fh.write(page)


def main(argv):
    source = argv[1] if len(argv) > 1 else FEED_URL
    xml_bytes = fetch_feed(source)
    articles = build_articles(xml_bytes)

    block = '<div class="job-listings">\n' + "\n".join(articles) + "\n</div>\n"
    with open(JOBS_OUT, "w", encoding="utf-8") as fh:
        fh.write(block)
    write_preview(articles, len(articles))

    print(f"{len(articles)} jobs written to {JOBS_OUT} and {PREVIEW_OUT}")


if __name__ == "__main__":
    main(sys.argv)

#!/usr/bin/env python3
"""
Fully-automated updater for the Horse Checker "Jobs in Racing" page.

It fetches the live Careers in Racing RSS feed, builds the job listings as
static HTML (your existing article.job-item markup) and writes them straight
into your real .html page — in place, between two marker comments. The page
stays a genuine static HTML file (great for SEO), it just gets refreshed
automatically.

Because it rebuilds the whole block from the current feed every run, jobs that
have dropped out of the feed disappear from the page automatically — no stale
listings to clean up by hand.

ONE-TIME SETUP
--------------
In your jobs page, wrap the listings area with these two comment markers
(put them inside <div class="job-listings"> ... </div>):

    <div class="job-listings">
    <!-- JOBS:START -->
    <!-- JOBS:END -->
    </div>

Anything between the markers is what this script replaces.

RUN IT
------
    python3 update_page.py /path/to/jobs-in-racing.html

Then schedule it (see cron.txt) and it runs on its own — zero clicks.
"""

import sys

from generate import build_articles, fetch_feed, FEED_URL

START = "<!-- JOBS:START -->"
END = "<!-- JOBS:END -->"


def main(argv):
    if len(argv) < 2:
        sys.exit("Usage: python3 update_page.py /path/to/jobs-in-racing.html [feed_url]")

    page_path = argv[1]
    feed = argv[2] if len(argv) > 2 else FEED_URL

    articles = build_articles(fetch_feed(feed))
    block = "\n".join(articles)

    with open(page_path, "r", encoding="utf-8") as fh:
        html = fh.read()

    if START not in html or END not in html:
        sys.exit(
            "Markers not found. Add these once inside your listings div:\n"
            f"  {START}\n  {END}"
        )

    before, rest = html.split(START, 1)
    _, after = rest.split(END, 1)
    new_html = f"{before}{START}\n{block}\n    {END}{after}"

    with open(page_path, "w", encoding="utf-8") as fh:
        fh.write(new_html)

    print(f"Updated {page_path} with {len(articles)} jobs.")


if __name__ == "__main__":
    main(sys.argv)

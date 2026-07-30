# Horse Checker — Jobs in Racing (RSS → static HTML)

Fetches the live Careers in Racing RSS feed and writes a block of **static HTML**
that matches the existing markup on <https://horsechecker.com/jobs-in-racing>.
No feed widget or JavaScript — real HTML in the page, which is what you want for SEO.

Each job becomes one `<article class="job-item">` using your current classes
(`job-item`, `job-description`, `new`, `custom-btn`, `time`), so your existing
CSS styles it automatically.

## Requirements
- Python 3 (standard library only — nothing to install)

## Daily use — one command
```bash
python3 generate.py
```
It downloads the feed and writes two files:
- **`jobs.html`** — the `<div class="job-listings">…</div>` block. Paste it into
  your page in place of the current listings (or copy the inner `<article>`s).
- **`preview.html`** — a standalone page so you can open it in a browser and
  check how it looks before publishing.

That's the whole daily job: run it, paste `jobs.html`, done.

## Changing the feed
Everything is at the top of `generate.py`:
```python
FEED_URL = "https://jobs.careersinracing.com/jobsrss/?Sector=1&countrycode=GB"
```
Swap that URL (e.g. a different Sector or country code) and re-run — no other
changes needed. You can also point it at a saved file: `python3 generate.py feed.xml`.

## Notes
- "New Today" is shown automatically on jobs whose posted date is today; older
  ones leave that line blank (same as your page).
- Descriptions are tidied into a single clean line (the feed adds line breaks).
- Links open in a new tab with `rel="nofollow"`, matching your current markup.

---

`convert.py` in this repo is a small general-purpose XML→HTML converter (any
Title/Description/Link XML). `generate.py` is the one tailored to your job feed.

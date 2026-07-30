# Horse Checker — Jobs in Racing (RSS → static HTML)

Fetches the live Careers in Racing RSS feed and renders it as **real static
HTML** matching the existing markup on <https://horsechecker.com/jobs-in-racing>
(each job is an `<article class="job-item">` using your current classes, so your
CSS styles it automatically). No feed widget, no JavaScript — genuine HTML in the
page, which is what you want for SEO.

It always rebuilds from the current feed, so jobs that drop out of the feed
disappear from the page automatically — no stale listings to remove by hand.

Requires Python 3 (standard library only) and/or PHP — both are on your Apache host.

---

## Recommended: PHP + cron — no renaming, no link changes

`update_page.php` is run by a cron job and writes the listings straight into
your existing `.html` page. Your page stays exactly where it is (no `index.php`,
no updating any links) and search engines still see real HTML.

One-time setup:
1. Inside your `<div class="job-listings">`, delete the manual listings and add
   these two markers:
   ```html
   <!-- JOBS:START -->
   <!-- JOBS:END -->
   ```
2. Upload `update_page.php` to your hosting (e.g. into `public_html`).
3. Run it once to fill the page (or via cPanel Terminal):
   ```bash
   php /home/USER/public_html/update_page.php /home/USER/public_html/jobs-in-racing.html
   ```
4. Add a cron job so it runs on its own (see **cron.txt** for exact lines).

Each run rebuilds from the current feed, so new jobs appear and expired ones
drop off automatically.

## Alternatives

- **No cron at all — `jobs.php`**: server-side include that refreshes itself on
  page load. Needs the page to be `.php` (rename + one `include` line), so only
  use this if you'd rather not set up a cron job.
- **Python instead of PHP — `update_page.py`**: same in-place updater as the
  recommended route, if you'd prefer Python. Also driven by cron (see cron.txt).

### Manual (if you ever want to run it by hand)
`generate.py` writes `jobs.html` (the block to paste) + `preview.html`:
```bash
python3 generate.py
```

---

## Changing the feed
Edit the URL at the top of `generate.py` / `jobs.php`:
```
https://jobs.careersinracing.com/jobsrss/?Sector=1&countrycode=GB
```
Swap the Sector / countrycode and re-run — nothing else to change.

## Files
- `jobs.php`        — server-side renderer (option 1, no schedule)
- `update_page.py`  — in-place updater for a static .html page (option 2)
- `generate.py`     — core feed→HTML; also the manual "make me a block" tool
- `cron.txt`        — how to schedule option 2
- `convert.py`      — bonus generic XML→HTML converter (any Title/Description/Link XML)

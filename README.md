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

## Pick one of two ways to run it

### 1. Fully automatic, no schedule needed — PHP  (recommended)
`jobs.php` fetches + caches the feed and prints the listings server-side, so the
page updates itself on its own and search engines still see real HTML.

- Rename your jobs page to `jobs-in-racing.php` (or keep `.html` + Apache SSI).
- Inside `<div class="job-listings">`, replace the manual listings with:
  ```php
  <?php include __DIR__ . '/jobs.php'; ?>
  ```
- Upload `jobs.php` alongside it. Done — it refreshes hourly by itself
  (cache TTL is set at the top of `jobs.php`; change `3600` to adjust).

### 2. Fully automatic on a schedule — Python + cron
Keeps the page a pure `.html` file; a scheduled job rewrites it in place.

- One-time: inside `<div class="job-listings">` add two markers:
  ```html
  <!-- JOBS:START -->
  <!-- JOBS:END -->
  ```
- Schedule `update_page.py` (see **cron.txt**). Each run replaces everything
  between the markers with the latest jobs:
  ```bash
  python3 update_page.py /path/to/jobs-in-racing.html
  ```

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

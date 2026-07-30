#!/usr/bin/env python3
"""
XML -> Minimalist HTML converter.

Reads an XML file whose records each carry a Title, Description and Link,
and renders them as one clean, standards-compliant HTML page.

Usage:
    python3 convert.py input.xml output.html
    python3 convert.py input.xml            # writes output.html next to it
    python3 convert.py                      # uses data.xml -> output.html

The tag names are matched case-insensitively, so <Title>/<title>,
<Description>/<desc>, <Link>/<url>/<href> all work. Just drop in a new
XML file and re-run — no code changes needed.
"""

import html
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

# Field name aliases (lower-cased). Add more here if your XML uses other tags.
TITLE_TAGS = {"title", "name", "heading"}
DESC_TAGS = {"description", "desc", "summary", "content", "body"}
LINK_TAGS = {"link", "url", "href", "guid"}


def _text_of(record, wanted):
    """Return the stripped text of the first child whose tag matches `wanted`."""
    for child in record:
        tag = child.tag.split("}")[-1].lower()  # strip XML namespace if present
        if tag in wanted:
            return (child.text or "").strip()
    return ""


def _find_records(root):
    """
    Find the repeating record elements. Works for a flat list of records as
    well as RSS-style <channel><item>...</item></channel> documents.
    """
    items = root.findall(".//{*}item")
    if items:
        return items
    # Otherwise: every element that has at least one recognised field child.
    recognised = TITLE_TAGS | DESC_TAGS | LINK_TAGS
    records = []
    for el in root.iter():
        for child in el:
            if child.tag.split("}")[-1].lower() in recognised:
                records.append(el)
                break
    # Deduplicate while keeping order; drop the root if it slipped in.
    seen, out = set(), []
    for r in records:
        if id(r) not in seen and r is not root:
            seen.add(id(r))
            out.append(r)
    return out


CSS = """\
:root { --ink: #1a1a1a; --muted: #666; --rule: #e5e5e5; --link: #0b5fff; }
* { box-sizing: border-box; }
body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
  color: var(--ink);
  line-height: 1.6;
  max-width: 42rem;
  margin: 0 auto;
  padding: 3rem 1.25rem 5rem;
  background: #fff;
}
h1 { font-size: 1.6rem; font-weight: 600; margin: 0 0 2.5rem; }
article { padding: 1.75rem 0; border-top: 1px solid var(--rule); }
article:first-of-type { border-top: none; }
article h2 { font-size: 1.2rem; font-weight: 600; margin: 0 0 .5rem; }
article p { margin: 0 0 .9rem; color: #333; }
article a { color: var(--link); text-decoration: none; word-break: break-word; }
article a:hover { text-decoration: underline; }
@media (prefers-color-scheme: dark) {
  :root { --ink: #eaeaea; --muted: #999; --rule: #2a2a2a; --link: #6ea8ff; }
  body { background: #121212; }
  article p { color: #cfcfcf; }
}
"""

PAGE = """\
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{page_title}</title>
<style>
{css}
</style>
</head>
<body>
<h1>{page_title}</h1>
{items}
</body>
</html>
"""

ITEM = """\
<article>
  <h2>{title}</h2>
  {desc}
  {link}
</article>"""


def build_html(xml_path, page_title="Items"):
    tree = ET.parse(xml_path)
    root = tree.getroot()
    records = _find_records(root)

    blocks = []
    for rec in records:
        title = _text_of(rec, TITLE_TAGS)
        desc = _text_of(rec, DESC_TAGS)
        link = _text_of(rec, LINK_TAGS)
        if not (title or desc or link):
            continue

        desc_html = f"<p>{html.escape(desc)}</p>" if desc else ""
        if link:
            safe = html.escape(link, quote=True)
            link_html = f'<a href="{safe}">{html.escape(link)}</a>'
        else:
            link_html = ""

        blocks.append(
            ITEM.format(
                title=html.escape(title) or "(untitled)",
                desc=desc_html,
                link=link_html,
            )
        )

    return PAGE.format(page_title=html.escape(page_title), css=CSS, items="\n".join(blocks))


def main(argv):
    xml_in = Path(argv[1]) if len(argv) > 1 else Path("data.xml")
    out = Path(argv[2]) if len(argv) > 2 else xml_in.with_suffix(".html")
    if not xml_in.exists():
        sys.exit(f"XML file not found: {xml_in}")
    out.write_text(build_html(xml_in, page_title=xml_in.stem.replace('_', ' ').title()),
                   encoding="utf-8")
    print(f"Wrote {out}")


if __name__ == "__main__":
    main(sys.argv)

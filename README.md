# XML → Minimalist HTML

Turns an XML file where each record has a **Title**, **Description** and **Link**
into a single, clean, standards-compliant HTML page. No frameworks, no external
CSS or fonts — just semantic markup and whitespace.

## Requirements
- Python 3 (any recent version — uses only the standard library, nothing to install)

## Run it
```bash
python3 convert.py data.xml output.html
```
- `data.xml`  — your input file
- `output.html` — the page that gets written

Shortcuts:
```bash
python3 convert.py data.xml       # writes data.html next to it
python3 convert.py                # uses data.xml -> output.html
```

Drop in a different XML file and re-run the same command — no code changes needed.

## XML format
```xml
<records>
  <record>
    <Title>...</Title>
    <Description>...</Description>
    <Link>https://...</Link>
  </record>
</records>
```
Tag names are matched case-insensitively and a few common aliases are accepted
(`title/name`, `description/desc/summary`, `link/url/href`). RSS-style
`<channel><item>...</item></channel>` files also work out of the box. If your
tags are different, add them to the small lists at the top of `convert.py`.

## Files
- `convert.py`  — the converter
- `data.xml`    — sample input
- `output.html` — sample output

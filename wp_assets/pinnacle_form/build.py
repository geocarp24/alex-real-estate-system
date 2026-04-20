#!/usr/bin/env python3
"""Bundle Pinnacle Form into a single HTML string ready to post to WP.
Reads: pinnacle_form.html (shell), pinnacle_form.css, pinnacle_form_i18n.js,
       pinnacle_form_core.js, pinnacle_form_screens.js
Writes: dist/pinnacle_form_bundle.html
"""
from pathlib import Path
HERE = Path(__file__).parent
DIST = HERE / "dist"
DIST.mkdir(exist_ok=True)

shell = (HERE / "pinnacle_form.html").read_text()
css   = (HERE / "pinnacle_form.css").read_text()
i18n  = (HERE / "pinnacle_form_i18n.js").read_text()
core  = (HERE / "pinnacle_form_core.js").read_text()
scr   = (HERE / "pinnacle_form_screens.js").read_text()

out = (shell
       .replace("/* ==== PNF_CSS_START ==== */", css)
       .replace("/* ==== PNF_I18N_START ==== */", i18n)
       .replace("/* ==== PNF_CORE_START ==== */", core)
       .replace("/* ==== PNF_SCREENS_START ==== */", scr))

bundle = DIST / "pinnacle_form_bundle.html"
bundle.write_text(out)
print(f"OK {bundle} {bundle.stat().st_size} bytes, {len(out.splitlines())} lines")

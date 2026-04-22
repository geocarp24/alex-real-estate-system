# Pinnacle WP Backup

Full snapshot of pinnaclegroupwi.com (WordPress 6.9.4 + Astra theme).

## Contents
- `ping.json` — site identity + WP/PHP/theme versions at backup time
- `pages_index.json` — list of all 7 published pages with ids
- `pages/*.json` — full JSON record per page (id, title, slug, content, modified)
- `pages/*.html` — raw Gutenberg block markup per page (ready to paste back)
- `option_*.json` — key WP options (active_plugins, stylesheet, page_on_front, etc.)
- `draft_1748_get-my-offer.html` — the new multi-step form page (still draft at backup time)

## Restore a single page
```bash
# Example: restore home to this snapshot
python3 - <<PY
import json, os, base64, urllib.request, pathlib
USER = os.environ["PINNACLE_WP_USER"]; PASS = os.environ["PINNACLE_WP_APP_PASSWORD"].replace(" ","")
auth = base64.b64encode(f"{USER}:{PASS}".encode()).decode()
content = pathlib.Path("pages/1373_home.html").read_text()
req = urllib.request.Request(
    "https://agents.pinnaclegroupwi.com/pinnacle_wp_bridge.php",
    data=json.dumps({"action":"update_post","id":1373,"content":content}).encode(),
    headers={"Content-Type":"application/json","Authorization":f"Basic {auth}"})
print(urllib.request.urlopen(req).read().decode())
PY
```

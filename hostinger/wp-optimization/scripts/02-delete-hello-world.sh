#!/usr/bin/env bash
# 02-delete-hello-world.sh
# Trashes the default "hello-world" posts and adds 301 redirects to home.
# Requires the "Redirection" plugin OR appends to .htaccess as fallback.

set -euo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
cd "$WP_PATH"

declare -a SLUGS=("hello-world" "hello-world-2")

for slug in "${SLUGS[@]}"; do
  pid=$(wp post list --post_type=post --name="$slug" --post_status=any --field=ID 2>/dev/null | head -1)
  if [ -z "$pid" ]; then
    echo "skip: $slug (not found)"
    continue
  fi
  wp post delete "$pid" --force
  echo "deleted: $slug (id=$pid)"
done

# Add 301 redirects via .htaccess if Redirection plugin is not present
if ! wp plugin is-installed redirection 2>/dev/null; then
  HTACCESS="$WP_PATH/.htaccess"
  if [ -f "$HTACCESS" ] && ! grep -q "# PINNACLE_REDIRECTS" "$HTACCESS"; then
    cat >> "$HTACCESS" <<'EOF'

# PINNACLE_REDIRECTS — added by wp-optimization/02-delete-hello-world.sh
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^hello-world/?$ / [R=301,L]
RewriteRule ^hello-world-2/?$ / [R=301,L]
</IfModule>
# /PINNACLE_REDIRECTS
EOF
    echo "appended 301 redirects to .htaccess"
  else
    echo "skip: .htaccess redirects already present or file missing"
  fi
else
  wp redirection add --source=/hello-world/ --target=/ --type=301 2>/dev/null || true
  wp redirection add --source=/hello-world-2/ --target=/ --type=301 2>/dev/null || true
  echo "added redirects via Redirection plugin"
fi

echo
echo "OK. Verify with: curl -sI https://pinnaclegroupwi.com/hello-world/ | head -3"

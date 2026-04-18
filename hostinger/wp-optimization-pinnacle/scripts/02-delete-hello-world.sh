#!/usr/bin/env bash
# 02-delete-hello-world.sh
# Trashes the default "hello-world" posts and adds 301 redirects to home.
# Uses .htaccess for redirects (works with or without the Redirection plugin).

set -uo pipefail

WP_PATH="${WP_PATH:-$HOME/domains/pinnaclegroupwi.com/public_html}"
cd "$WP_PATH"

for slug in hello-world hello-world-2; do
  pid=$(wp post list --post_type=post --name="$slug" --post_status=any --field=ID 2>/dev/null | head -1)
  if [ -z "${pid:-}" ] || [ "$pid" = "0" ]; then
    echo "skip: $slug (not found)"
    continue
  fi
  if wp post delete "$pid" --force >/dev/null 2>&1; then
    echo "deleted: $slug (id=$pid)"
  else
    echo "ERROR: wp post delete failed for $slug (id=$pid)"
  fi
done

HTACCESS="$WP_PATH/.htaccess"
if [ -f "$HTACCESS" ]; then
  if grep -q "# PINNACLE_REDIRECTS" "$HTACCESS" 2>/dev/null; then
    echo "skip: .htaccess redirects already present"
  else
    cat >> "$HTACCESS" <<'EOF'

# PINNACLE_REDIRECTS
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^hello-world/?$ / [R=301,L]
RewriteRule ^hello-world-2/?$ / [R=301,L]
</IfModule>
# /PINNACLE_REDIRECTS
EOF
    echo "appended 301 redirects to .htaccess"
  fi
else
  echo "WARN: .htaccess not found at $HTACCESS — skipping redirects"
fi

echo
echo "OK. Verify with: curl -sI https://pinnaclegroupwi.com/hello-world/"
exit 0

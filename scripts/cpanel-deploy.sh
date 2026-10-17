#!/bin/bash
set -euo pipefail
CHECKOUT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
INSTALL_ROOT="${DIVARAAP_INSTALL_ROOT:-$(dirname "$CHECKOUT")/divaraap}"
PUBLIC_ROOT="${DIVARAAP_PUBLIC_ROOT:-$(dirname "$CHECKOUT")/public_html}"
GIT=/usr/local/cpanel/3rdparty/bin/git
[ -x "$GIT" ] || GIT=/usr/bin/git
PHP_CLI="${DIVARAAP_PHP:-}"
if [ -z "$PHP_CLI" ]; then
    for candidate in /opt/cloudlinux/alt-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php; do
        if [ -x "$candidate" ]; then PHP_CLI="$candidate"; break; fi
    done
fi
[ -n "$PHP_CLI" ] || { echo 'PHP 8.4 CLI not found.' >&2; exit 1; }
[ -f "$INSTALL_ROOT/installed.lock" ] && [ -f "$INSTALL_ROOT/.env" ] || { echo 'Existing installation not found; nothing changed.' >&2; exit 1; }
REVISION="$("$GIT" -C "$CHECKOUT" rev-parse HEAD)"
STAGING="$(mktemp -d "$INSTALL_ROOT/cpanel-stage.XXXXXXXX")"
trap 'rm -rf -- "$STAGING"' EXIT
ASSET="cpanel-$REVISION.zip"
URL="https://github.com/yasharomranie/divaraap/releases/download/deploy-main"
for name in "$ASSET" "$ASSET.sha256"; do
    curl --fail --location --http1.1 --retry 3 --connect-timeout 20 --max-time 600 "$URL/$name" --output "$STAGING/$name"
done
"$PHP_CLI" "$CHECKOUT/scripts/cpanel-release.php" prepare "$INSTALL_ROOT" "$PUBLIC_ROOT" "$STAGING/$ASSET" "$REVISION"
"$PHP_CLI" "$CHECKOUT/scripts/cpanel-release.php" activate "$INSTALL_ROOT" "$PUBLIC_ROOT" "$STAGING/$ASSET" "$REVISION"

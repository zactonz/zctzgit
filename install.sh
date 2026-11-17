#!/bin/bash

set -euo pipefail

PLUGIN_DIR="/usr/local/cpanel/base/frontend/jupiter/zctzgit"
CONFIG_DIR="/etc/zctzgit"
CRON_FILE="/etc/cron.d/zctzgit"
POLLER="/usr/local/bin/zctzgit-poll"

if [ "$(id -u)" -ne 0 ]; then
    echo "This script must be run as root." >&2
    exit 1
fi

if [ ! -d /usr/local/cpanel ]; then
    echo "cPanel & WHM was not found on this server." >&2
    exit 1
fi

PHP="/usr/local/cpanel/3rdparty/bin/php"
[ -x "$PHP" ] || PHP="$(command -v php || true)"

if [ -z "$PHP" ]; then
    echo "No PHP interpreter was found." >&2
    exit 1
fi

PHP_OK="$("$PHP" -r 'echo PHP_VERSION_ID >= 80100 ? "yes" : "no";')"
if [ "$PHP_OK" != "yes" ]; then
    echo "Zactonz Git needs PHP 8.1 or newer. Found: $("$PHP" -r 'echo PHP_VERSION;')" >&2
    exit 1
fi

echo "Installing plugin files..."
mkdir -p "$PLUGIN_DIR"
rsync -a --delete --delete-excluded \
    --exclude='.git' --exclude='.gitignore' --exclude='.github' \
    --exclude='install.sh' --exclude='uninstall.sh' \
    --exclude='tests' \
    --exclude='README.md' --exclude='CHANGELOG.md' --exclude='LICENSE' \
    ./ "$PLUGIN_DIR/"

chown -R root:root "$PLUGIN_DIR"
find "$PLUGIN_DIR" -type d -exec chmod 755 {} \;
find "$PLUGIN_DIR" -type f -exec chmod 644 {} \;
chmod 755 "$PLUGIN_DIR/bin/askpass.sh"

echo "Installing the poller..."
install -m 0755 "$PLUGIN_DIR/bin/zctzgit-poll" "$POLLER"

cat > "$CRON_FILE" <<CRON
# Zactonz Git: deploy repositories whose remote has moved.
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
*/5 * * * * root $POLLER >/dev/null 2>&1
CRON
chmod 644 "$CRON_FILE"

if [ ! -f "$CONFIG_DIR/config.json" ]; then
    echo "Writing the default server policy..."
    mkdir -p "$CONFIG_DIR"
    cat > "$CONFIG_DIR/config.json" <<'JSON'
{
    "allowed_hosts": ["github.com", "gitlab.com", "bitbucket.org", "codeberg.org"],
    "allowed_commands": [],
    "allow_tokens": true,
    "block_unpatched_git": false,
    "allow_local_paths": false,
    "deploy_timeout_seconds": 600
}
JSON
    chmod 644 "$CONFIG_DIR/config.json"
fi

echo "Registering the plugin..."
/usr/local/cpanel/scripts/install_plugin "$PLUGIN_DIR/install.json"
/usr/local/cpanel/scripts/install_plugin "$PLUGIN_DIR" --theme jupiter

echo "Restarting the cPanel interface..."
/usr/local/cpanel/scripts/restartsrv_cpsrvd

GIT_BIN="$(command -v git || echo /usr/local/cpanel/3rdparty/bin/git)"
echo
echo "----------------------------------------------------"
echo " Zactonz Git installed."
echo " Git in use: $("$GIT_BIN" --version 2>/dev/null || echo 'not found')"
echo " Server policy: $CONFIG_DIR/config.json"
echo " Poller: $CRON_FILE (every 5 minutes)"
echo "----------------------------------------------------"

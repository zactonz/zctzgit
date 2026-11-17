#!/bin/bash

set -uo pipefail

PLUGIN_DIR="/usr/local/cpanel/base/frontend/jupiter/zctzgit"

if [ "$(id -u)" -ne 0 ]; then
    echo "This script must be run as root." >&2
    exit 1
fi

echo "Removing the cron entry and poller..."
rm -f /etc/cron.d/zctzgit /usr/local/bin/zctzgit-poll

if [ -f "$PLUGIN_DIR/install.json" ]; then
    echo "Unregistering the plugin..."
    /usr/local/cpanel/scripts/uninstall_plugin "$PLUGIN_DIR/install.json"
fi

rm -rf "$PLUGIN_DIR"

echo
echo "----------------------------------------------------"
echo " Zactonz Git removed."
echo " Kept: /etc/zctzgit/config.json, each account's"
echo " ~/.zctzgit directory, and every deployed repository."
echo "----------------------------------------------------"

#!/bin/bash

# Zactonz Git Plugin - Uninstallation Script
#
# This script is responsible for uninstalling the Zactonz Git plugin
# from the cPanel server. It unregisters the plugin and removes the
# plugin files from the server.
#
# @author Team Zactonz
# @copyright Zactonz Technologies
# @link https://zactonz.com/
# @version 1.0

PLUGIN_DIR="/usr/local/cpanel/base/frontend/jupiter/zctzgit"

# --- Pre-flight Checks ---
if [ "$(id -u)" -ne 0 ]; then
    echo "This script must be run as root."
    exit 1
fi

# --- Uninstallation ---
echo "Unregistering and removing Zactonz Git plugin..."

if [ -f "$PLUGIN_DIR/install.json" ]; then
    /usr/local/cpanel/scripts/uninstall_plugin "$PLUGIN_DIR/install.json"
fi

if [ -d "$PLUGIN_DIR" ]; then
    rm -rf "$PLUGIN_DIR"
fi

echo "----------------------------------------------------"
echo " Zactonz Git Plugin uninstalled."
echo " Note: User-space webhook directories in public_html are not removed."
echo "----------------------------------------------------"
exit 0
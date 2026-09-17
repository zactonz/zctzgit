#!/bin/bash

# Zactonz Git Plugin - Installation Script
#
# This script is responsible for installing the Zactonz Git plugin
# on the cPanel server. It copies the plugin files to the appropriate
# directory and registers the plugin with cPanel.
#
# @author Zactonz Technologies
# @copyright Zactonz Technologies
# @link https://zactonz.com/
# @version 1.0.1

PLUGIN_DIR="/usr/local/cpanel/base/frontend/jupiter/zctzgit"

# --- Pre-flight Checks ---
if [ "$(id -u)" -ne 0 ]; then
    echo "This script must be run as root."
    exit 1
fi

# --- Installation ---
echo "Installing Zactonz Git Plugin files..."
mkdir -p "$PLUGIN_DIR"
rsync -a --delete --delete-excluded \
    --exclude='.git' --exclude='.gitignore' --exclude='.github' \
    --exclude='install.sh' --exclude='uninstall.sh' \
    --exclude='README.md' --exclude='CHANGELOG.md' --exclude='LICENSE' \
    ./ "$PLUGIN_DIR/"
chown root:root -R "$PLUGIN_DIR"
find "$PLUGIN_DIR" -type d -exec chmod 755 {} \;
find "$PLUGIN_DIR" -type f -exec chmod 644 {} \;

echo "Registering the cPanel plugin..."
/usr/local/cpanel/scripts/install_plugin "$PLUGIN_DIR/install.json"
/usr/local/cpanel/scripts/install_plugin "$PLUGIN_DIR" --theme jupiter

echo "Restarting cPanel UI.."
/scripts/restartsrv_cpsrvd

echo "----------------------------------------------------"
echo " Zactonz Git Plugin installed successfully."
echo "----------------------------------------------------"
exit 0
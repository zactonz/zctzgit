#!/bin/bash
#
# Runs the real install.sh and uninstall.sh against a sandboxed filesystem.
# cPanel's own scripts are replaced with stubs that record their arguments, so
# the installer's file placement, permissions, exclusions and ordering are
# exercised without a cPanel server and without root.

set -uo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
SANDBOX="$(mktemp -d)"
PASS=0
FAIL=0

check() {
    if [ "$2" = "yes" ]; then PASS=$((PASS + 1)); echo "  ok   $1"
    else FAIL=$((FAIL + 1)); echo "  FAIL $1"; fi
}

exists()  { [ -e "$1" ] && echo yes || echo no; }
absent()  { [ -e "$1" ] && echo no || echo yes; }
mode()    { stat -f '%Lp' "$1" 2>/dev/null || stat -c '%a' "$1" 2>/dev/null; }

mkdir -p "$SANDBOX/usr/local/cpanel/scripts" "$SANDBOX/usr/local/bin" "$SANDBOX/etc/cron.d" "$SANDBOX/bin"

for script in install_plugin uninstall_plugin restartsrv_cpsrvd; do
    cat > "$SANDBOX/usr/local/cpanel/scripts/$script" <<STUB
#!/bin/bash
echo "$script \$*" >> "$SANDBOX/calls.log"
exit 0
STUB
    chmod +x "$SANDBOX/usr/local/cpanel/scripts/$script"
done

REAL_PHP="$(command -v php)"
cat > "$SANDBOX/bin/php" <<PHPWRAP
#!/bin/bash
exec "$REAL_PHP" "\$@"
PHPWRAP
chmod +x "$SANDBOX/bin/php"

sandboxed() {
    sed \
        -e "s#^PLUGIN_DIR=\"/usr/local/cpanel#PLUGIN_DIR=\"$SANDBOX/usr/local/cpanel#" \
        -e "s#^CONFIG_DIR=\"/etc/zctzgit\"#CONFIG_DIR=\"$SANDBOX/etc/zctzgit\"#" \
        -e "s#^CRON_FILE=\"/etc/cron.d/zctzgit\"#CRON_FILE=\"$SANDBOX/etc/cron.d/zctzgit\"#" \
        -e "s#^CONFIG_DIR=\"/etc/zctzgit\"#CONFIG_DIR=\"$SANDBOX/etc/zctzgit\"#" \
        -e "s#^POLLER=\"/usr/local/bin/zctzgit-poll\"#POLLER=\"$SANDBOX/usr/local/bin/zctzgit-poll\"#" \
        -e "s#/usr/local/cpanel/3rdparty/bin/php#$SANDBOX/bin/php#" \
        -e "s#/usr/local/cpanel/scripts/#$SANDBOX/usr/local/cpanel/scripts/#g" \
        -e "s#\[ ! -d /usr/local/cpanel \]#[ ! -d $SANDBOX/usr/local/cpanel ]#" \
        -e 's#^if \[ "$(id -u)" -ne 0 \]; then#if false; then#' \
        -e 's#^chown -R root:root#true root:root#' \
        "$1"
}

PLUGIN="$SANDBOX/usr/local/cpanel/base/frontend/jupiter/zctzgit"

echo "Installer"
sandboxed "$REPO/install.sh" > "$SANDBOX/install.sandboxed.sh"
chmod +x "$SANDBOX/install.sandboxed.sh"
( cd "$REPO" && bash "$SANDBOX/install.sandboxed.sh" > "$SANDBOX/install.out" 2>&1 )
INSTALL_RC=$?

check "the installer exits cleanly (rc=$INSTALL_RC)" "$([ $INSTALL_RC -eq 0 ] && echo yes || echo no)"
check "the plugin directory is created"              "$(exists "$PLUGIN")"
check "the interface is installed"                   "$(exists "$PLUGIN/index.live.php")"
check "the library is installed"                     "$(exists "$PLUGIN/lib/Deployer.php")"
check "assets are installed"                         "$(exists "$PLUGIN/assets/zctzgit.css")"
check "the poller script is installed"               "$(exists "$PLUGIN/cron/poll.php")"

check "tests are not shipped to the server"          "$(absent "$PLUGIN/tests")"
check "the repository history is not shipped"        "$(absent "$PLUGIN/.git")"
check "CI config is not shipped"                     "$(absent "$PLUGIN/.github")"
check "the installer does not ship itself"           "$(absent "$PLUGIN/install.sh")"
check "the README is not served from the plugin dir" "$(absent "$PLUGIN/README.md")"

check "askpass is executable"                        "$([ -x "$PLUGIN/bin/askpass.sh" ] && echo yes || echo no)"
check "the interface is world-readable, not writable" "$([ "$(mode "$PLUGIN/index.live.php")" = "644" ] && echo yes || echo no)"

check "the poller is installed to the system path"   "$(exists "$SANDBOX/usr/local/bin/zctzgit-poll")"
check "the poller is executable"                     "$([ -x "$SANDBOX/usr/local/bin/zctzgit-poll" ] && echo yes || echo no)"
check "a cron entry is written"                      "$(exists "$SANDBOX/etc/cron.d/zctzgit")"
check "cron runs the poller as root"                 "$(grep -q 'root .*zctzgit-poll' "$SANDBOX/etc/cron.d/zctzgit" && echo yes || echo no)"
check "cron sets an explicit PATH"                   "$(grep -q '^PATH=' "$SANDBOX/etc/cron.d/zctzgit" && echo yes || echo no)"

check "a default server policy is written"           "$(exists "$SANDBOX/etc/zctzgit/config.json")"
check "the policy is valid JSON"                     "$(php -r 'exit(json_decode(file_get_contents($argv[1]),true)===null?1:0);' "$SANDBOX/etc/zctzgit/config.json" && echo yes || echo no)"
check "the policy forbids local paths by default"    "$(php -r '$c=json_decode(file_get_contents($argv[1]),true); exit(empty($c["allow_local_paths"])?0:1);' "$SANDBOX/etc/zctzgit/config.json" && echo yes || echo no)"
check "the policy warns rather than blocks by default" "$(php -r '$c=json_decode(file_get_contents($argv[1]),true); exit(empty($c["block_unpatched_git"])?0:1);' "$SANDBOX/etc/zctzgit/config.json" && echo yes || echo no)"

check "install_plugin was called with the manifest"  "$(grep -q 'install_plugin .*install.json' "$SANDBOX/calls.log" && echo yes || echo no)"
check "install_plugin registered the Jupiter theme"  "$(grep -q 'theme jupiter' "$SANDBOX/calls.log" && echo yes || echo no)"
check "cpsrvd was restarted after registration"      "$(tail -1 "$SANDBOX/calls.log" | grep -q restartsrv && echo yes || echo no)"

echo
echo "Reinstall over an existing install"
echo "stale" > "$PLUGIN/leftover-from-1.0.x.php"
( cd "$REPO" && bash "$SANDBOX/install.sandboxed.sh" > "$SANDBOX/reinstall.out" 2>&1 )
check "reinstalling succeeds"                        "$([ $? -eq 0 ] && echo yes || echo no)"
check "a file from a previous version is removed"    "$(absent "$PLUGIN/leftover-from-1.0.x.php")"
check "the plugin still works after reinstall"       "$(exists "$PLUGIN/index.live.php")"

echo
echo "PHP version gate"
cat > "$SANDBOX/bin/php" <<'OLDPHP'
#!/bin/bash
if [ "$1" = "-r" ]; then
    case "$2" in
        *PHP_VERSION_ID*) echo "no"; exit 0 ;;
        *PHP_VERSION*)    echo "8.0.30"; exit 0 ;;
    esac
fi
exit 0
OLDPHP
chmod +x "$SANDBOX/bin/php"
( cd "$REPO" && bash "$SANDBOX/install.sandboxed.sh" > "$SANDBOX/old.out" 2>&1 )
check "the installer refuses PHP below 8.1"          "$([ $? -ne 0 ] && echo yes || echo no)"
check "and says which version it found"              "$(grep -q '8.0.30' "$SANDBOX/old.out" && echo yes || echo no)"

echo
echo "Uninstaller"
sandboxed "$REPO/uninstall.sh" > "$SANDBOX/uninstall.sandboxed.sh"
( bash "$SANDBOX/uninstall.sandboxed.sh" > "$SANDBOX/uninstall.out" 2>&1 )
check "the uninstaller exits cleanly"                "$([ $? -eq 0 ] && echo yes || echo no)"
check "the plugin directory is removed"              "$(absent "$PLUGIN")"
check "the cron entry is removed"                    "$(absent "$SANDBOX/etc/cron.d/zctzgit")"
check "the poller is removed"                        "$(absent "$SANDBOX/usr/local/bin/zctzgit-poll")"
check "the server policy is deliberately kept"       "$(exists "$SANDBOX/etc/zctzgit/config.json")"
check "uninstall_plugin was called"                  "$(grep -q uninstall_plugin "$SANDBOX/calls.log" && echo yes || echo no)"

echo
[ -n "${KEEP_SANDBOX:-}" ] || rm -rf "$SANDBOX"
echo "$PASS passed, $FAIL failed"
[ $FAIL -eq 0 ]

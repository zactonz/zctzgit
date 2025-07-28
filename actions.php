<?php
/**
 * Zactonz Git Plugin - Repository Actions
 *
 * This script handles various actions related to the management of GitHub repositories
 * in the Zactonz Git plugin, such as toggling auto-sync, deleting repositories, and
 * manually syncing repositories.
 *
 * @author Team Zactonz
 * @copyright Zactonz Technologies
 * @link https://zactonz.com/
 * @version 1.0
 */

define('PLUGIN_BASE_DIR', '/usr/local/cpanel/base/frontend/jupiter/zctzgit');
require_once PLUGIN_BASE_DIR . '/includes/config.php';
require_once PLUGIN_BASE_DIR . '/includes/git-config.php';
require_once PLUGIN_BASE_DIR . '/includes/git-helper.php';

$username = getenv('USER');
if (!$username) { die("Could not determine cPanel username."); }

$config = new ZctzGitConfig($username);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $index = isset($_POST['index']) ? (int)$_POST['index'] : -1;

    switch ($action) {
        case 'toggle_sync':
            $repos = $config->getReposConfig();
            if (isset($repos[$index])) {
                $repos[$index]['auto_sync'] = (bool)$_POST['status'];
                $config->updateRepo($index, $repos[$index]);
                echo "Success";
            }
            break;

        case 'delete_repo':
            $config->removeRepoConfig($index);
            echo "Success";
            break;

        case 'sync_repo':
            $repos = $config->getReposConfig();
            if (isset($repos[$index])) {
                if (deployRepo($repos[$index])) {
                    $repos[$index]['last_sync'] = time();
                    $config->updateRepo($index, $repos[$index]);
                    echo "Repository synced successfully!";
                } else {
                    http_response_code(500);
                    echo "Failed to sync repository. Check logs for details.";
                }
            } else {
                http_response_code(404);
                echo "Repository configuration not found.";
            }
            break;

        case 'reinstall_gateway':
            $config->getGatewayDirectory(true); // Force recreation of the gateway
            echo "Webhook gateway has been reinstalled successfully.";
            break;

        default:
            http_response_code(400);
            echo "Invalid action.";
    }
}
?>
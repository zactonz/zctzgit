<?php
/**
 * Zactonz Git Plugin - Repository Deployment
 *
 * This script handles the deployment of a new GitHub repository for the Zactonz Git plugin.
 * It processes the form data submitted from the plugin's main interface and saves the
 * repository configuration, then attempts to deploy the repository to the user's server.
 *
 * @author Zactonz Technologies
 * @copyright Zactonz Technologies
 * @link https://zactonz.com/
 * @version 1.0.1
 */

define('PLUGIN_BASE_DIR', '/usr/local/cpanel/base/frontend/jupiter/zctzgit');
require_once PLUGIN_BASE_DIR . '/includes/git-config.php';
require_once PLUGIN_BASE_DIR . '/includes/git-helper.php';

$username = getenv('USER');
if (!$username) { die("Could not determine cPanel username."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $homeDir = rtrim(getenv('HOME') ?: "/home/{$username}", '/');
    $relativePath = trim($_POST['repo_destination_path'] ?? '', '/');

    if ($relativePath === ''
        || strpos($relativePath, "\0") !== false
        || preg_match('#(^|/)\.\.(/|$)#', $relativePath)) {
        http_response_code(400);
        exit('Invalid repository path. <a href="index.live.php">Go back</a>.');
    }

    $repoName = $_POST['repo_name'];
    $repoUrl = $_POST['repo_url'];
    $destinationDir = $homeDir . '/' . $relativePath;
    $token = $_POST['github_token'] ?? null;
    $branch = $_POST['repo_branch'] ?? 'main';
    $autoSync = isset($_POST['auto_sync']);

    $config = new ZctzGitConfig($username);

    $config->saveRepoConfig($repoName, $repoUrl, $destinationDir, $token, $branch, $autoSync);

    $repoData = ['repo_url' => $repoUrl, 'destination_dir' => $destinationDir, 'github_token' => $token, 'branch' => $branch];
    if (deployRepo($repoData)) {

        $repos = $config->getReposConfig();
        $newRepoIndex = count($repos) - 1;
        if ($newRepoIndex >= 0) {
            $repos[$newRepoIndex]['last_sync'] = time();
            $config->updateRepo($newRepoIndex, $repos[$newRepoIndex]);
        }
        echo '<script>window.location.href="index.live.php?success=1";</script>';
    } else {
        echo 'Failed to deploy the repository. <a href="index.live.php">Go back</a>.';
    }
}
?>
<?php
/**
 * Zactonz Git Plugin - Git Repository Deployment Helper
 *
 * This file contains the function responsible for deploying a GitHub repository
 * to the user's server. It handles cloning the repository for the first time or
 * pulling the latest changes if the repository is already cloned.
 *
 * @author Zactonz Technologies
 * @copyright Zactonz Technologies
 * @link https://zactonz.com/
 * @version 1.0.1
 */



/**
 * Deploy a GitHub repository to the user's server.
 *
 * @param array $repo An associative array containing the repository details:
 *                    - 'destination_dir': The local path where the repository should be deployed.
 *                    - 'repo_url': The URL of the GitHub repository.
 *                    - 'branch': The branch to be deployed.
 *                    - 'github_token': (optional) The GitHub access token for authentication.
 * @return bool True if the deployment was successful, false otherwise.
 */
function deployRepo(array $repo): bool {
    // Validate essential keys
    if (empty($repo['destination_dir']) || empty($repo['repo_url']) || empty($repo['branch'])) {
        return false;
    }

    // Sanitize inputs
    $destinationDir = rtrim($repo['destination_dir'], '/');
    $repoUrl        = trim($repo['repo_url']);
    $branch         = trim($repo['branch']);
    $token          = $repo['github_token'] ?? null;

    // Validate repo URL (basic check)
    if (!preg_match('#^https://github\.com/[a-zA-Z0-9._-]+/[a-zA-Z0-9._-]+(\.git)?$#', $repoUrl)) {
        return false;
    }

    // Ensure destination directory exists
    if (!is_dir($destinationDir)) {
        if (!mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            return false;
        }
    }

    // Build authenticated URL if token is provided
    $authUrl = $repoUrl;
    if ($token) {
        // Sanitize token before embedding in URL
        $parsed = parse_url($repoUrl);
        if (!isset($parsed['host']) || strpos($parsed['host'], 'github.com') === false) {
            return false; // Invalid or non-GitHub URL
        }

        $authUrl = 'https://' . rawurlencode($token) . '@' . $parsed['host'] . $parsed['path'];
    }

    // Escape shell arguments
    $escBranch    = escapeshellarg($branch);
    $escAuthUrl   = escapeshellarg($authUrl);
    $escDestDir   = escapeshellarg($destinationDir);

    // Determine if we should clone or pull
    if (!is_dir($destinationDir . '/.git')) {
        // Clone the repository
        $cmd = "git clone -b $escBranch $escAuthUrl $escDestDir";
    } else {
        // Pull the latest changes
        $cmd = "cd $escDestDir && git config remote.origin.url $escAuthUrl && git pull origin $escBranch";
    }

    // Execute command
    $output = [];
    $returnCode = 0;
    exec($cmd . ' 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        protectGitDirectory($destinationDir);
    }

    return $returnCode === 0;
}

/**
 * Deny web access to the .git directory of a deployed repository.
 *
 * The remote URL git stores in .git/config carries the access token for private
 * repositories, so the directory must never be served. This covers Apache and
 * LiteSpeed; NGINX ignores .htaccess and needs a server-level rule instead.
 *
 * @param string $destinationDir The deployed repository directory.
 * @return void
 */
function protectGitDirectory(string $destinationDir): void {
    $gitDir = $destinationDir . '/.git';
    if (!is_dir($gitDir)) {
        return;
    }

    $htaccessFile = $gitDir . '/.htaccess';
    $rules = "<IfModule mod_authz_core.c>\n"
        . "Require all denied\n"
        . "</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n"
        . "Order allow,deny\n"
        . "Deny from all\n"
        . "</IfModule>\n";

    if (!is_file($htaccessFile) || file_get_contents($htaccessFile) !== $rules) {
        file_put_contents($htaccessFile, $rules);
    }

    @chmod($gitDir, 0700);
}
?>
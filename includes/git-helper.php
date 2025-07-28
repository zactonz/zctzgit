<?php
/**
 * Zactonz Git Plugin - Git Repository Deployment Helper
 *
 * This file contains the function responsible for deploying a GitHub repository
 * to the user's server. It handles cloning the repository for the first time or
 * pulling the latest changes if the repository is already cloned.
 *
 * @author Team Zactonz
 * @copyright Zactonz Technologies
 * @link https://zactonz.com/
 * @version 1.0
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

    // Optional: Log for debugging (ensure this path is private)
    // file_put_contents('/home/USERNAME/git_deploy.log', implode("\n", $output) . "\n", FILE_APPEND);

    return $returnCode === 0;
}
?>
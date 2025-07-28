<?php
/**
 * Zactonz Git Plugin - Webhook (backend)
 *
 * This webhook will be included at user/cPanel webhook file.
 * It will actually pull and sync the repo which is hit the Github
 * only if it has auto sync enabled by the admin.
 *
 * @author Team Zactonz
 * @copyright Zactonz Technologies
 * @link https://zactonz.com/
 * @version 1.0
 */
 
 
// We need to make sure its run by parent file and its not hit directly.
if (!defined('ZCTZGIT_WORKER')) { 
    
    http_response_code(403);
    exit('Access denied.'); 
    
}

$repo_secret_id = $_SERVER['argv'][1] ?? null;

if (!$repo_secret_id) { 
    
    http_response_code(400); 
    
    exit("Error: No repo secret ID provided."); 
    
}

$username = getenv('USER');
w
if (!$username) {
    $path_parts = explode('/', __DIR__);
    $user_index = array_search('home', $path_parts);
    if ($user_index !== false && isset($path_parts[$user_index + 1])) {
        $username = $path_parts[$user_index + 1];
    } else {
        http_response_code(500);
        exit("Server Error: Could not determine user context.");
    }
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/git-config.php';
require_once __DIR__ . '/includes/git-helper.php';

$config = new ZctzGitConfig($username);
$repos = $config->getReposConfig();
$repoToDeploy = null;
$repoIndex = -1;

foreach ($repos as $index => $repo) {
    if (isset($repo['repo_secret_id']) && hash_equals($repo['repo_secret_id'], $repo_secret_id)) {
        $repoToDeploy = $repo;
        $repoIndex = $index;
        break;
    }
}

if ($repoToDeploy && !empty($repoToDeploy['auto_sync'])) {
    if (deployRepo($repoToDeploy)) {
        $repoToDeploy['last_sync'] = time();
        $config->updateRepo($repoIndex, $repoToDeploy);
        echo "Deployment successful for: " . htmlspecialchars($repoToDeploy['repo_name']);
    } else {
        http_response_code(500);
        echo "Deployment failed.";
    }
} else {
    http_response_code(200);
    echo "Request received, but repository not found or auto-sync is disabled.";
}
?>
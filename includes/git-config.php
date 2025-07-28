<?php
/**
 * Zactonz Git Configuration Manager
 *
 * This class is responsible for managing the configuration of the Zactonz Git plugin,
 * including storing and retrieving GitHub repository information, as well as handling
 * encryption and decryption of sensitive data like GitHub access tokens.
 *
 * @author Team Zactonz
 * @copyright Zactonz Technologies
 * @link https://zactonz.com/
 * @version 1.0
 */

<?php

class ZctzGitConfig {
    private $username;
    private $configDir;
    private $encryptionKey;
    private $debug;

    public function __construct($username, $debug = false) {
        $this->username = $username;
        $this->configDir = "/home/{$this->username}/.zctzgit";
        $this->encryptionKey = 'zctz-secure-key-' . sha1($this->username);
        $this->debug = $debug;
    }

    // Returns path to config file for storing repo settings
    private function getConfigFile() {
        return $this->configDir . '/repos.json';
    }

    // Ensure the config directory exists, create if not
    private function ensureConfigDir() {
        if (!file_exists($this->configDir)) {
            mkdir($this->configDir, 0700, true);  // Strict permissions
            file_put_contents($this->configDir . '/.htaccess', 'deny from all');
        }
    }

    // Log messages for debugging
    public function logMessage($message) {
        if ($this->debug) {
            $logFile = '/home/' . $this->username . '/zctzgit_debug.log';
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
        }
    }

    // Encrypt sensitive data (e.g., GitHub tokens)
    private function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        return base64_encode($encrypted . '::' . bin2hex($iv));
    }

    // Decrypt sensitive data (e.g., GitHub tokens)
    private function decrypt($data) {
        if (empty($data)) return null;
        $data = base64_decode($data);
        $parts = explode('::', $data);
        if (count($parts) !== 2) return null;
        $encrypted_data = $parts[0];
        $iv = hex2bin($parts[1]);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
    }

    // Retrieve repository configurations
    public function getReposConfig() {
        $repos = json_decode(file_get_contents($this->getConfigFile()), true);
        foreach ($repos as $key => $repo) {
            if (isset($repo['github_token'])) {
                $repos[$key]['github_token'] = $this->decrypt($repo['github_token']);
            }
        }
        return $repos;
    }

    // Save updated repository configurations
    private function saveReposConfig($repos) {
        $this->ensureConfigDir();
        foreach ($repos as $key => $repo) {
            if (isset($repo['github_token'])) {
                $repos[$key]['github_token'] = $this->encrypt($repo['github_token']);
            }
        }
        file_put_contents($this->getConfigFile(), json_encode(array_values($repos), JSON_PRETTY_PRINT));
    }

    // Save a new repository configuration
    public function saveRepoConfig($repoName, $repoUrl, $destinationDir, $githubToken, $branch, $autoSync) {
        $this->getGatewayDirectory(true);  // Ensure gateway directory exists
        $repos = $this->getReposConfig();
        $repoSecretId = bin2hex(random_bytes(16));  // Unique ID for the repo
        $newRepo = [
            'repo_name' => $repoName,
            'repo_url' => $repoUrl,
            'destination_dir' => $destinationDir,
            'github_token' => $githubToken,
            'branch' => $branch,
            'auto_sync' => (bool)$autoSync,
            'last_sync' => null,
            'repo_secret_id' => $repoSecretId,
        ];
        $repos[] = $newRepo;
        $this->saveReposConfig($repos);
    }

    // Update an existing repository configuration
    public function updateRepo($index, $repoData) {
        $repos = $this->getReposConfig();
        if (isset($repos[$index])) {
            $repos[$index] = $repoData;
            $this->saveReposConfig($repos);
        }
    }

    // Remove a repository configuration
    public function removeRepoConfig($index) {
        $repos = $this->getReposConfig();
        if (isset($repos[$index])) {
            unset($repos[$index]);
            $this->saveReposConfig($repos);
        }
    }

    // Handle gateway directory (create if necessary)
    public function getGatewayDirectory($forceCreate = false) {
        $this->ensureConfigDir();
        $gatewayConfigFile = $this->configDir . '/gateway.conf';
        if ($forceCreate || !file_exists($gatewayConfigFile)) {
            $gatewayDirName = 'zctz_gateway_' . bin2hex(random_bytes(12));
            file_put_contents($gatewayConfigFile, $gatewayDirName);
            $this->createGatewayDirectory($gatewayDirName);
            return $gatewayDirName;
        }
        return trim(file_get_contents($gatewayConfigFile));
    }

    // Create a new gateway directory
    public function createGatewayDirectory($gatewayDirName = null) {
        if (is_null($gatewayDirName)) {
            $gatewayDirName = $this->getGatewayDirectory();
        }
        $gatewayPath = "/home/{$this->username}/public_html/{$gatewayDirName}";
        if (!is_dir($gatewayPath)) {
            mkdir($gatewayPath, 0755, true);
        }

        // .htaccess to secure access
        $htaccessContent = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^(.*)$ index.php [L,QSA]\n</IfModule>";
        file_put_contents("{$gatewayPath}/.htaccess", $htaccessContent);

        // Gateway script to handle requests
        $gatewayScriptContent = <<<'EOD'
<?php
define('ZCTZGIT_WORKER', true);
$workerScriptPath = '/usr/local/cpanel/base/frontend/jupiter/zctzgit/webhook.php';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$parts = array_values(array_filter(explode('/', $path)));
$repoSecretId = end($parts);
if (!$repoSecretId) {
    http_response_code(400);
    exit('Repository ID missing from URL.');
}
$_SERVER['argv'] = [__FILE__, $repoSecretId];
require_once $workerScriptPath;
EOD;
        file_put_contents("{$gatewayPath}/index.php", $gatewayScriptContent);
    }
}
?>
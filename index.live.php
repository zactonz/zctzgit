<?php
/**
 * Zactonz Git Plugin for cPanel
 *
 * This is the main entry point for the Zactonz Git plugin, which is designed to
 * integrate with cPanel and provide users with a convenient way to manage their
 * GitHub repositories.
 *
 * @author Team Zactonz
 * @copyright Zactonz Technologies
 * @link https://zactonz.com/
 * @version 1.0
 */


require_once '/usr/local/cpanel/php/cpanel.php';

define('PLUGIN_BASE_DIR', '/usr/local/cpanel/base/frontend/jupiter/zctzgit');

require_once PLUGIN_BASE_DIR . '/includes/config.php';
require_once PLUGIN_BASE_DIR . '/includes/git-config.php';

$cpanel = new CPANEL();

$username = getenv('USER');
$config = new ZctzGitConfig($username);
$repos = $config->getReposConfig();
$gatewayDir = $config->getGatewayDirectory();

echo $cpanel->header('Zactonz Git');
?>
<style>
    .info-block { margin-top: 0; margin-bottom: 5px; }
    .btn-group .btn { margin-right: 5px; }
    .zctz-flx{ display:flex; flex-wrap: wrap; gap:5px; }
    a.list-group-item{ color: #428bca; }
</style>

<div class="row">
    <div class="col-xs-12">
        <div class="description">
            Use this interface to automatically pull/clone existing remote repositories.
            To add an existing repository to the list of Zactonz Git repositories, select that repository path when you pull the repository.
        </div>
        <div class="row">
            <div class="col-xs-12 col-sm-6">
                <h4>Add New Repository</h4>
                <form action="deploy.php" method="POST">
                <div class="form-group">
                  <label>Repository Name</label>
                    <span class="info-block">
                        <span>Enter the desired path for the repository's directory that you want to pull automatically</span>
                    </span>
                  <input type="text" name="repo_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <label>Clone URL</label>
                            <span class="info-block">
                                <span>Enter the clone URL for the remote repository. All clone URLs must begin with the http:// or https://</span>
                            </span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <input type="url" name="repo_url" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                  <label>Branch</label>
                  <input type="text" name="repo_branch" class="form-control" value="main">
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <div class="toggle-info-label-container" for="repoPathField" label-text="Repository Path" show-info-block="true">
                                <label id="lblrepoPathFieldLabel11" for="repoPathField">
                                    Repository Path
                                </label>
                                <span class="info-block">
                                    <span>Enter the desired path for the repository's directory.
                                    If you enter a path that does not exist, the system will create the directory when it creates or clones the repository.
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div id="repoPathFieldSegment" class="col-xs-12">
                            <div class="input-group">
                                <span class="input-group-addon truncate">
                                    <span uib-tooltip="/home/<?php echo $username;?>/" class="home-dir-text">/home/<?php echo $username;?>/</span>
                                    <span class="sr-only">
                                        Enter a valid directory path, relative to your home directory.
                                    </span>
                                </span>
                                <input name="repo_destination_path" type="text" class="form-control" autocomplete="new-path" required="" pattern="[^'&quot;:\\*?<>|@&amp;=%#`$(){};\[\]\s]+" aria-autocomplete="list" aria-expanded="false" role="combobox">
                            </div>
                            <span class="help-block">
                                The path cannot contain the "./" and "../" directory references, whitespace, or the following characters: \ * | " ' &lt; &gt; &amp; @ ` $ { } [ ] ( ) ; ? : = % #
                            </span>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <label>Access Token (for private repo)</label>
                            <span id="toggleLabelInfo14infoText17" class="info-block">
                                <span>You can get it from Github <a href="https://github.com/settings/personal-access-tokens/" noreferrer target="_blank">Personal access tokens</a></span>
                            </span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <input type="password" name="github_token" class="form-control" autocomplete="new-password" required>
                            <span class="help-block">Read access to code, metadata, pull requests, and repository</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <label><input type="checkbox" name="auto_sync" checked> Enable Auto Sync (via Webhook)</label>
                            <span class="help-block">Auto syncing as soon as Git updated at Github</span>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save & Deploy</button>
              </form>
            </div>
            <div class="col-xs-12 col-sm-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Related Links</h3>
                    </div>
                    <div class="list-group">
                      <a href="https://github.com/settings/personal-access-tokens/" class="list-group-item list-group-item-action" noreferrer target="_blank" uib-tooltip="Manage your PAT.">
                         <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                         Github Personal access tokens
                      </a>
                      <a href="https://docs.github.com/en/webhooks/using-webhooks/creating-webhooks" class="list-group-item list-group-item-action" noreferrer target="_blank" uib-tooltip="Webhooks">
                         <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                         Creating webhooks at Github
                      </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-12" style="margin-top:20px;padding:0">
            <hr/>
        </div>
        <div class="row">
            <div class="col-xs-12">
                <h3>Configured Repositories</h3>
                <button class="btn btn-default pull-right" onclick="reinstallGateway()" title="Recreate the webhook gateway directory in your public_html folder if it gets deleted.">
                    <i class="fas fa-sync"></i> Repair Webhook Gateway
                </button>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Repository</th>
                            <th>Auto sync</th>
                            <th>Last sync</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($repos)): ?>
                            <tr><td colspan="4">No repositories configured yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($repos as $index => $repo): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($repo['repo_name'] ?? 'N/A'); ?></strong><br>
                                    <small>Path: <?php echo str_replace("/home/{$username}", "", htmlspecialchars($repo['destination_dir']) ); ?></small>
                                </td>
                                <td>
                                    <span class="label label-<?php echo $repo['auto_sync'] ? 'success' : 'warning'; ?>">
                                        <?php echo $repo['auto_sync'] ? 'On' : 'Off'; ?>
                                    </span>
                                </td>
                                <td><?php 
                                $timestamp = $repo['last_sync'] ?? null;

                                if ($timestamp !== null) {

                                    $serverTimezone = date_default_timezone_get(); 
                                    
                                    $dt = new DateTime('@' . $timestamp);  // The '@' symbol ensures the timestamp is treated as UTC

                                    $dt->setTimezone(new DateTimeZone($serverTimezone));

                                    echo $dt->format('M j, Y, g:i a');
                                    
                                } else {
                                    echo 'Never';
                                }
                                ?></td>
                                <td>
                                    <div class="btn-group zctz-flx">
                                        <button class="btn btn-xs btn-primary" onclick="syncNow(<?php echo $index; ?>)">Sync Now</button>
                                        <button class="btn btn-xs btn-info" onclick="showWebhookUrl('<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>', '<?php echo htmlspecialchars($gatewayDir); ?>', '<?php echo htmlspecialchars($repo['repo_secret_id']); ?>')">Webhook</button>
                                        <button class="btn btn-xs btn-<?php echo $repo['auto_sync'] ? 'warning' : 'success'; ?>" onclick="toggleAutoSync(<?php echo $index; ?>, <?php echo $repo['auto_sync'] ? '0' : '1'; ?>)">
                                            <?php echo $repo['auto_sync'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                        <button class="btn btn-xs btn-danger" onclick="deleteRepo(<?php echo $index; ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="webhookModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Webhook URL</h4>
      </div>
      <div class="modal-body">
        <p>Use this URL in your GitHub repository's webhook settings (Content type: <code>application/json</code>):</p>
        <input type="text" id="webhookUrlInput" class="form-control" readonly>
      </div>
    </div>
  </div>
</div>

<script>
function showWebhookUrl(domain, gatewayDir, repoSecret) {
    var webhookUrl = window.location.protocol + "//" + domain + "/" + gatewayDir + "/" + repoSecret;
    document.getElementById('webhookUrlInput').value = webhookUrl;
    var zctzPop = document.getElementById('webhookModal');
    zctzPop.classList.add('in', 'show');
}

function closeWebhookModal() {
    const zctzPop = document.getElementById('webhookModal');
    zctzPop.classList.remove('in', 'show');
}

// Close modal when clicking on the 'x' close button
document.querySelector('#webhookModal .close').addEventListener('click', closeWebhookModal);

// Close modal when clicking outside the modal content (on the backdrop)
window.addEventListener('click', function(event) {
    const modalElement = document.getElementById('webhookModal');
    if (event.target === modalElement) {
        closeWebhookModal();
    }
});

function reinstallGateway() {
    if (confirm('This will recreate the main webhook gateway directory in your public_html folder. This is useful if it was accidentally deleted. Continue?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ action: 'reinstall_gateway' })
        })
        .then(response => response.text())
        .then(response => {
            alert(response);
            window.location.reload();
        })
        .catch(() => {
            alert('An error occurred.');
        });
    }
}

function syncNow(index) {
    if (confirm('Are you sure you want to manually sync this repository now?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ action: 'sync_repo', index: index })
        })
        .then(response => response.text())
        .then(response => {
            alert(response);
            location.reload();
        })
        .catch(xhr => {
            alert('Error: ' + xhr.responseText);
        });
    }
}

function toggleAutoSync(index, status) {
    fetch('actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ action: 'toggle_sync', index: index, status: status })
    })
    .then(() => {
        location.reload();
    });
}

function deleteRepo(index) {
    if (confirm('Are you sure you want to delete this repository configuration? The files on your server will not be deleted.')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ action: 'delete_repo', index: index })
        })
        .then(() => {
            location.reload();
        });
    }
}

</script>

<?php
echo $cpanel->footer();
$cpanel->end();
?>
<?php

declare(strict_types=1);

require_once '/usr/local/cpanel/php/cpanel.php';
require_once __DIR__ . '/lib/autoload.php';

use Zactonz\Git\App;
use Zactonz\Git\CredentialType;
use Zactonz\Git\DeployMode;
use Zactonz\Git\GitVersionStatus;

$cpanel = new CPANEL();
echo $cpanel->header('Zactonz Git');

$app = App::forAccount(__DIR__);
$repositories = $app->store->all();
$git = $app->git();
$version = $app->config->allowTokens;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function when(?int $timestamp): string
{
    if ($timestamp === null) {
        return 'Never';
    }

    return (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('j M Y, g:i a');
}
?>
<link rel="stylesheet" href="assets/zctzgit.css">

<div id="zctz" class="zctz-wrap">
    <div class="description">
        Deploy a Git repository into a directory in this account. Changes are pulled automatically
        on a schedule when automatic deployment is on, or whenever you choose Deploy now.
    </div>

    <div id="zctz-notice" class="zctz-note zctz-hide"></div>

<?php if ($git === null): ?>
    <div class="zctz-note zctz-note--error">
        Git was not found on this server. Ask your administrator to install it.
    </div>
<?php else: ?>
    <?php $status = $git->status(); ?>
    <?php if ($status !== GitVersionStatus::Supported): ?>
    <div class="zctz-note zctz-note--<?= $status === GitVersionStatus::Unserviced ? 'error' : 'warn' ?>">
        <?= e($status->message($git->version)) ?>
    </div>
    <?php endif; ?>
    <p class="zctz-env">Using <?= e($git->describe()) ?></p>
<?php endif; ?>

    <div class="zctz-grid">
        <div class="zctz-grid__main">
            <h4>Add a repository</h4>
            <form id="zctz-add">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label for="zctz-name">Name</label>
                    <input id="zctz-name" name="name" type="text" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="zctz-url">Clone URL</label>
                    <input id="zctz-url" name="url" type="text" class="form-control" required
                           placeholder="https://github.com/owner/repository">
                    <span class="help-block">
                        Allowed hosts: <?= e(implode(', ', $app->config->allowedHosts)) ?>.
                        Use an https:// or git@ URL.
                    </span>
                </div>

                <div class="form-group">
                    <label for="zctz-branch">Branch</label>
                    <input id="zctz-branch" name="branch" type="text" class="form-control" value="main" required>
                </div>

                <div class="form-group">
                    <label for="zctz-path">Directory</label>
                    <div class="input-group">
                        <span class="input-group-addon"><?= e($app->paths->home) ?>/</span>
                        <input id="zctz-path" name="path" type="text" class="form-control" required
                               placeholder="public_html">
                    </div>
                    <span class="help-block">
                        The directory does not have to be empty. It is created if it does not exist.
                    </span>
                </div>

                <div class="form-group">
                    <label for="zctz-credential">Access</label>
                    <select id="zctz-credential" name="credential" class="form-control">
                        <option value="none">Public repository, no credential</option>
<?php if ($app->config->allowTokens): ?>
                        <option value="token">Private, using an access token</option>
<?php endif; ?>
                        <option value="deploy_key">Private, using a deploy key generated here</option>
                    </select>
                    <span class="help-block">
                        A deploy key never leaves this server. An access token is stored encrypted and
                        is handed to Git only while a deployment runs.
                    </span>
                </div>

                <div class="form-group zctz-hide" id="zctz-token-field">
                    <label for="zctz-token">Access token</label>
                    <input id="zctz-token" name="token" type="password" class="form-control"
                           autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="zctz-mode">If the directory has local changes</label>
                    <select id="zctz-mode" name="mode" class="form-control">
                        <option value="safe"><?= e(DeployMode::Safe->label()) ?></option>
                        <option value="force"><?= e(DeployMode::Force->label()) ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="zctz-keep">Files to keep, one per line</label>
                    <textarea id="zctz-keep" name="keep" class="form-control" rows="2"
                              placeholder=".env"></textarea>
                    <span class="help-block">These are restored after a forced deployment.</span>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="auto_sync" value="1" checked> Deploy automatically</label>
                </div>

                <button type="submit" class="btn btn-primary">Add repository</button>
            </form>
        </div>

        <div class="zctz-grid__side">
            <div class="panel panel-default">
                <div class="panel-heading"><h3 class="panel-title">Deploy keys and tokens</h3></div>
                <div class="list-group">
                    <a class="list-group-item" target="_blank" rel="noreferrer"
                       href="https://github.com/settings/personal-access-tokens/">GitHub access tokens</a>
                    <a class="list-group-item" target="_blank" rel="noreferrer"
                       href="https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys">Using deploy keys</a>
                </div>
            </div>
        </div>
    </div>

    <hr class="zctz-rule">

    <h3>Repositories</h3>

    <table class="table table-striped zctz-table">
        <thead>
            <tr>
                <th>Repository</th>
                <th>Automatic</th>
                <th>Last deployment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
<?php if ($repositories === []): ?>
            <tr><td colspan="4">No repositories yet.</td></tr>
<?php endif; ?>
<?php foreach ($repositories as $repository): ?>
            <tr>
                <td>
                    <strong><?= e($repository->name) ?></strong><br>
                    <span class="zctz-muted">
                        <?= e($repository->url) ?> · <?= e($repository->branch) ?><br>
                        <?= e($app->paths->relativise($repository->targetDir)) ?>
                        · <?= e($repository->mode === DeployMode::Force ? 'force' : 'safe') ?>
                        · <?= e(match ($repository->credentialType) {
                            CredentialType::None => 'public',
                            CredentialType::Token => 'token',
                            CredentialType::DeployKey => 'deploy key',
                        }) ?>
                    </span>
                </td>
                <td>
                    <span class="label label-<?= $repository->autoSync ? 'success' : 'default' ?>">
                        <?= $repository->autoSync ? 'On' : 'Off' ?>
                    </span>
                </td>
                <td>
                    <?= e(when($repository->lastSync)) ?>
<?php if ($repository->lastStatus === 'failed'): ?>
                    <br><span class="label label-danger">Failed</span>
<?php endif; ?>
                </td>
                <td>
                    <div class="zctz-actions">
                        <button type="button" class="btn btn-xs btn-primary"
                                data-zctz-action="deploy" data-zctz-id="<?= e($repository->id) ?>">Deploy now</button>
                        <button type="button" class="btn btn-xs btn-default"
                                data-zctz-action="log" data-zctz-id="<?= e($repository->id) ?>">Log</button>
<?php if ($repository->credentialType === CredentialType::DeployKey): ?>
                        <button type="button" class="btn btn-xs btn-default"
                                data-zctz-action="key" data-zctz-id="<?= e($repository->id) ?>">Deploy key</button>
<?php endif; ?>
                        <button type="button" class="btn btn-xs btn-default"
                                data-zctz-action="toggle" data-zctz-id="<?= e($repository->id) ?>">
                            <?= $repository->autoSync ? 'Turn off' : 'Turn on' ?>
                        </button>
                        <button type="button" class="btn btn-xs btn-danger"
                                data-zctz-action="delete" data-zctz-id="<?= e($repository->id) ?>">Remove</button>
                    </div>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>

    <div id="zctz-log" class="panel panel-default zctz-panel zctz-hide">
        <div class="panel-heading">
            <h3 class="panel-title">Deployment log
                <button type="button" class="btn btn-xs btn-default pull-right" data-zctz-action="close">Close</button>
            </h3>
        </div>
        <div class="panel-body"><pre id="zctz-log-output" class="zctz-log"></pre></div>
    </div>

    <div id="zctz-key" class="panel panel-default zctz-panel zctz-hide">
        <div class="panel-heading">
            <h3 class="panel-title">Deploy key
                <button type="button" class="btn btn-xs btn-default pull-right" data-zctz-action="close">Close</button>
            </h3>
        </div>
        <div class="panel-body">
            <p>Add this public key to the repository on its host, with read access.</p>
            <code id="zctz-key-output" class="zctz-key"></code>
        </div>
    </div>
</div>

<script src="assets/zctzgit.js"></script>
<?php
echo $cpanel->footer();
$cpanel->end();

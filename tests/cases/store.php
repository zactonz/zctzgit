<?php

declare(strict_types=1);

use Zactonz\Git\CredentialType;
use Zactonz\Git\DeployMode;
use Zactonz\Git\Paths;
use Zactonz\Git\Repository;
use Zactonz\Git\Store;

return static function (TestRun $t): void {
    $t->group('Store');

    $home = sys_get_temp_dir() . '/zctz-store-' . bin2hex(random_bytes(6));
    mkdir($home, 0700, true);
    $paths = new Paths('testuser', $home);
    $store = new Store($paths);

    $t->same('a fresh account has no repositories', [], $store->all());
    $t->assert('finding an unknown id returns null', $store->find('nope') === null);

    $site = Repository::create('Site', 'https://github.com/zactonz/zctzgit', 'main', $home . '/public_html', DeployMode::Safe, CredentialType::None, true);
    $store->save($site);

    $t->same('one repository is stored', 1, count($store->all()));
    $t->same('it is keyed by id', $site->id, $store->find($site->id)?->id);
    $t->same('the name round-trips', 'Site', $store->find($site->id)?->name);
    $t->assert('an id is a 32-character hex string', preg_match('/^[0-9a-f]{32}$/', $site->id) === 1);
    $t->assert('a webhook secret is generated', strlen((string) $site->webhookSecret) === 64);

    $t->same('the file is owner-only', '0600', substr(sprintf('%o', fileperms($paths->reposFile())), -4));
    $t->same('the config dir is owner-only', '0700', substr(sprintf('%o', fileperms($paths->configDir())), -4));

    $app = Repository::create('App', 'https://gitlab.com/x/y', 'release', $home . '/app', DeployMode::Force, CredentialType::DeployKey, false);
    $store->save($app);
    $t->same('a second repository is added', 2, count($store->all()));

    $store->save($site->with(['last_sync' => 1700000000, 'last_status' => 'ok']));
    $t->same('updating does not add a row', 2, count($store->all()));
    $t->same('the update persisted', 1700000000, $store->find($site->id)?->lastSync);
    $t->same('the other row is untouched', 'App', $store->find($app->id)?->name);

    $t->same('lookup by webhook secret finds the right repo', $app->id, $store->findByWebhookSecret((string) $app->webhookSecret)?->id);
    $t->assert('an unknown secret finds nothing', $store->findByWebhookSecret('deadbeef') === null);
    $t->assert('an empty secret finds nothing', $store->findByWebhookSecret('') === null);

    $t->assert('deleting a known id succeeds', $store->delete($site->id));
    $t->assert('deleting twice is a no-op', !$store->delete($site->id));
    $t->same('the survivor keeps its id after a delete', $app->id, array_key_first($store->all()));

    $t->group('Store migration from 1.0.x');

    $legacyHome = sys_get_temp_dir() . '/zctz-legacy-' . bin2hex(random_bytes(6));
    mkdir($legacyHome . '/.zctzgit', 0700, true);
    file_put_contents($legacyHome . '/.zctzgit/repos.json', json_encode([
        [
            'repo_name' => 'Old site',
            'repo_url' => 'https://github.com/zactonz/zctzgit',
            'destination_dir' => $legacyHome . '/public_html',
            'github_token' => 'ENCRYPTED',
            'branch' => 'main',
            'auto_sync' => true,
            'last_sync' => 1690000000,
            'repo_secret_id' => 'abc123',
        ],
        [
            'repo_name' => 'Public thing',
            'repo_url' => 'https://github.com/zactonz/sllms-framework',
            'destination_dir' => $legacyHome . '/pub',
            'branch' => 'main',
            'auto_sync' => false,
            'last_sync' => null,
            'repo_secret_id' => 'def456',
        ],
    ], JSON_PRETTY_PRINT));

    $legacy = new Store(new Paths('testuser', $legacyHome));
    $migrated = $legacy->all();

    $t->same('both legacy rows are read', 2, count($migrated));
    $t->assert('the old secret id becomes the stable id', isset($migrated['abc123']));
    $t->same('the name carries over', 'Old site', $migrated['abc123']->name);
    $t->same('the target directory carries over', $legacyHome . '/public_html', $migrated['abc123']->targetDir);
    $t->same('auto sync carries over', true, $migrated['abc123']->autoSync);
    $t->same('last sync carries over', 1690000000, $migrated['abc123']->lastSync);
    $t->same('a row that had a token is marked as token-backed', CredentialType::Token, $migrated['abc123']->credentialType);
    $t->same('a row without a token is marked credential-free', CredentialType::None, $migrated['def456']->credentialType);
    $t->same('migration defaults to the safe deploy mode', DeployMode::Safe, $migrated['abc123']->mode);
    $t->assert('each migrated row gets a fresh webhook secret', strlen((string) $migrated['abc123']->webhookSecret) === 64);
    $t->assert(
        'the old URL secret is never reused as the webhook secret',
        $migrated['abc123']->webhookSecret !== 'abc123',
    );

    $t->group('Store corruption handling');

    foreach (['', 'not json', '{"schema":2}', '"a string"', '[1,2,3]'] as $junk) {
        $junkHome = sys_get_temp_dir() . '/zctz-junk-' . bin2hex(random_bytes(6));
        mkdir($junkHome . '/.zctzgit', 0700, true);
        file_put_contents($junkHome . '/.zctzgit/repos.json', $junk);
        $result = (new Store(new Paths('u', $junkHome)))->all();
        $t->assert(sprintf('%s yields an empty list, not a fatal', var_export($junk, true)), $result === []);
    }
};

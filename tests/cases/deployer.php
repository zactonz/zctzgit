<?php

declare(strict_types=1);

use Zactonz\Git\CommandRunner;
use Zactonz\Git\CredentialType;
use Zactonz\Git\Credentials;
use Zactonz\Git\DeployMode;
use Zactonz\Git\Deployer;
use Zactonz\Git\DeployLog;
use Zactonz\Git\GitBinary;
use Zactonz\Git\Paths;
use Zactonz\Git\Repository;
use Zactonz\Git\Secrets;
use Zactonz\Git\ServerConfig;
use Zactonz\Git\Store;

return static function (TestRun $t): void {
    $t->group('Deployer');

    $runner = new CommandRunner(timeoutSeconds: 60);
    $git = GitBinary::locate($runner);

    if ($git === null) {
        $t->assert('no git available, deploy engine untested', true);
        return;
    }

    $root = sys_get_temp_dir() . '/zctz-deploy-' . bin2hex(random_bytes(6));
    $origin = $root . '/origin';
    $home = $root . '/home';
    mkdir($origin, 0755, true);
    mkdir($home, 0700, true);

    $run = static fn (array $args, ?string $cwd = null) => $runner->run(
        array_merge([$git->path], $args),
        $cwd,
        [
            'GIT_AUTHOR_NAME' => 'Zactonz Technologies',
            'GIT_AUTHOR_EMAIL' => 'github_manager@zactonz.com',
            'GIT_COMMITTER_NAME' => 'Zactonz Technologies',
            'GIT_COMMITTER_EMAIL' => 'github_manager@zactonz.com',
        ],
    );

    $run(['init', '--quiet', '--initial-branch=main'], $origin);
    $run(['config', 'user.name', 'Zactonz Technologies'], $origin);
    $run(['config', 'user.email', 'github_manager@zactonz.com'], $origin);
    file_put_contents($origin . '/index.html', "v1\n");
    $run(['add', '-A'], $origin);
    $commit = $run(['commit', '-qm', 'first'], $origin);
    if (!$commit->succeeded()) {
        $t->assert('fixture repository could not be created: ' . $commit->output(), false);
        return;
    }

    $config = new ServerConfig(['localhost'], ['true'], true, false, 600, allowLocalPaths: true);
    $paths = new Paths('testuser', $home);
    $store = new Store($paths);
    $credentials = new Credentials($paths, new Secrets($paths));


    $target = $home . '/public_html';
    mkdir($target, 0755, true);
    file_put_contents($target . '/leftover.txt', "pre-existing\n");

    $repository = Repository::create('Site', $origin, 'main', $target, DeployMode::Safe, CredentialType::None, true);
    $store->save($repository);

    $deployer = new Deployer($paths, $store, $credentials, $config, $git, $runner, __DIR__ . '/../..');

    $t->group('Deployer — first deploy into a non-empty directory');

    $first = $deployer->deploy($repository);
    $t->assert('deploys where git clone would refuse: ' . $first->summary, $first->ok);
    $t->same('the tracked file arrives', "v1\n", @file_get_contents($target . '/index.html'));
    $t->assert('the pre-existing untracked file survives safe mode', is_file($target . '/leftover.txt'));
    $t->assert('a revision is reported', $first->revision !== null);
    $t->assert('.git is protected from the web', is_file($target . '/.git/.htaccess'));
    $t->same('.git is owner-only', '0700', substr(sprintf('%o', fileperms($target . '/.git')), -4));
    $t->assert('no credential is written into the remote url',
        !str_contains((string) @file_get_contents($target . '/.git/config'), '@'));
    $t->same('submodule recursion is disabled in the clone', 'false',
        trim($runner->run([$git->path, 'config', '--get', 'submodule.recurse'], $target)->stdout));

    $t->group('Deployer — no-op when the remote has not moved');

    $fresh = $store->find($repository->id);
    $unchanged = $deployer->deploy($fresh, onlyIfRemoteMoved: true);
    $t->assert('a poll with no new commit does no work', $unchanged->skipped);
    $t->assert('and still reports success', $unchanged->ok);

    $t->group('Deployer — second deploy picks up a new commit');

    file_put_contents($origin . '/index.html', "v2\n");
    $run(['add', '-A'], $origin);
    $run(['commit', '-qm', 'second'], $origin);

    $second = $deployer->deploy($store->find($repository->id), onlyIfRemoteMoved: true);
    $t->assert('a moved remote deploys: ' . $second->summary, $second->ok && !$second->skipped);
    $t->same('the working tree is updated', "v2\n", @file_get_contents($target . '/index.html'));

    $t->group('Deployer — safe mode refuses to discard local edits');

    file_put_contents($target . '/index.html', "edited on the server\n");
    $blocked = $deployer->deploy($store->find($repository->id));
    $t->assert('safe mode refuses', !$blocked->ok);
    $t->assert('and says why: ' . $blocked->summary, str_contains($blocked->summary, 'local changes'));
    $t->same('the edit is left alone', "edited on the server\n", @file_get_contents($target . '/index.html'));

    $t->group('Deployer — force mode overwrites, keep list survives');

    file_put_contents($target . '/.env', "SECRET=keepme\n");
    $forced = $store->find($repository->id)->with(['mode' => 'force', 'keep' => ['.env']]);
    $store->save($forced);

    $result = $deployer->deploy($store->find($repository->id));
    $t->assert('force mode deploys over local edits: ' . $result->summary, $result->ok);
    $t->same('the tracked file matches the remote again', "v2\n", @file_get_contents($target . '/index.html'));
    $t->same('the kept file survives', "SECRET=keepme\n", @file_get_contents($target . '/.env'));

    $t->group('Deployer — failures are explained, not swallowed');

    $missingBranch = $store->find($repository->id)->with(['branch' => 'does-not-exist']);
    $branchResult = $deployer->deploy($missingBranch);
    $t->assert('a missing branch fails', !$branchResult->ok);
    $t->assert('and names the branch: ' . $branchResult->summary, str_contains($branchResult->summary, 'does-not-exist'));

    $unreachable = $store->find($repository->id)->with(['url' => $root . '/no-such-repo']);
    $gone = $deployer->deploy($unreachable);
    $t->assert('an unreachable remote fails cleanly', !$gone->ok);
    $t->assert('with a non-empty explanation', trim($gone->summary) !== '');

    $t->group('Deployer — the log records what happened');

    $log = (new DeployLog($paths, $repository->id))->tail();
    $t->assert('the log is not empty', trim($log) !== '');
    $t->assert('it names the git binary used', str_contains($log, $git->path));
    $t->assert('it records a fetch', str_contains($log, 'fetch'));
    $t->assert('it never contains a token placeholder', !str_contains($log, 'ZCTZGIT_TOKEN'));

    $t->same('the store recorded a status', 'failed', $store->find($repository->id)?->lastStatus);
    $t->assert('the store recorded a sync time', $store->find($repository->id)?->lastSync !== null);
};

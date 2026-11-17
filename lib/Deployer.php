<?php

declare(strict_types=1);

namespace Zactonz\Git;

use RuntimeException;
use Throwable;

final class Deployer
{
    public function __construct(
        private readonly Paths $paths,
        private readonly Store $store,
        private readonly Credentials $credentials,
        private readonly ServerConfig $config,
        private readonly GitBinary $git,
        private readonly CommandRunner $runner,
        private readonly string $pluginDir,
    ) {
    }

    public function deploy(Repository $repository, bool $onlyIfRemoteMoved = false): DeployResult
    {
        $log = new DeployLog($this->paths, $repository->id);
        $lock = Lock::acquire($this->paths, $repository->id);

        if ($lock === null) {
            $log->append('Skipped: another deployment is already running.');

            return DeployResult::failure('Another deployment for this repository is already running.');
        }

        try {
            return $this->record($repository, $this->run($repository, $log, $onlyIfRemoteMoved), $log);
        } catch (Throwable $e) {
            $log->append('Failed: ' . $e->getMessage());

            return $this->record($repository, DeployResult::failure($e->getMessage()), $log);
        } finally {
            $lock->release();
        }
    }

    private function record(Repository $repository, DeployResult $result, DeployLog $log): DeployResult
    {
        if ($result->skipped) {
            return $result;
        }

        $log->append($result->ok ? 'Done: ' . $result->summary : 'Error: ' . $result->summary);

        $this->store->save($repository->with([
            'last_sync' => time(),
            'last_status' => $result->ok ? 'ok' : 'failed',
        ]));

        return $result;
    }

    private function run(Repository $repository, DeployLog $log, bool $onlyIfRemoteMoved): DeployResult
    {
        $this->assertGitIsUsable($log);

        $remote = RemoteUrl::parse($repository->url, $this->config);
        $target = $repository->targetDir;
        $environment = $this->credentials->environmentFor($repository, $this->pluginDir);

        $log->append(sprintf('Deploying %s (%s) into %s', $remote->withoutCredentials(), $repository->branch, $this->paths->relativise($target)));
        $log->append('Using ' . $this->git->describe());

        $this->ensureWorkingTree($target, $remote, $log);

        $remoteHead = $this->remoteHead($repository, $remote, $environment, $log);
        $localHead = $this->localHead($target);

        if ($onlyIfRemoteMoved && $remoteHead !== null && $remoteHead === $localHead) {
            return DeployResult::unchanged($remoteHead);
        }

        $fetch = $this->git($target, $environment, ['fetch', '--no-tags', '--prune', '--recurse-submodules=no', 'origin', $repository->branch]);
        $log->appendResult('fetch', $fetch);

        if (!$fetch->succeeded()) {
            return DeployResult::failure($this->explain($fetch, $repository));
        }

        if ($repository->mode === DeployMode::Safe) {
            $dirty = $this->git($target, $environment, ['status', '--porcelain', '--untracked-files=no']);

            if (trim($dirty->stdout) !== '') {
                $log->append('Refusing to overwrite local changes in the target directory.');

                return DeployResult::failure(
                    'The target directory has local changes. Commit them, or switch this repository to the force mode.',
                );
            }
        }

        $kept = $this->stashKept($repository, $target);

        $checkout = $this->git($target, $environment, ['checkout', '--force', '--no-recurse-submodules', '-B', $repository->branch, 'FETCH_HEAD']);
        $log->appendResult('checkout', $checkout);

        if (!$checkout->succeeded()) {
            $this->restoreKept($kept, $target);

            return DeployResult::failure($this->explain($checkout, $repository));
        }

        if ($repository->mode === DeployMode::Force) {
            $clean = $this->git($target, $environment, ['clean', '-fd']);
            $log->appendResult('clean', $clean);
        }

        $this->restoreKept($kept, $target);
        $this->protectGitDirectory($target);

        $revision = $this->localHead($target);
        $log->append('Now at ' . ($revision ?? 'unknown'));

        return DeployResult::success('Deployed ' . substr((string) $revision, 0, 8), $revision);
    }

    private function assertGitIsUsable(DeployLog $log): void
    {
        $status = $this->git->status();

        if ($status === GitVersionStatus::Supported) {
            return;
        }

        $message = $status->message($this->git->version);

        if ($status === GitVersionStatus::Unserviced || $this->config->blockUnpatchedGit) {
            throw new RuntimeException($message);
        }

        $log->append('Warning: ' . $message);
    }

    private function ensureWorkingTree(string $target, RemoteUrl $remote, DeployLog $log): void
    {
        if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
            throw new RuntimeException('Unable to create ' . $this->paths->relativise($target));
        }

        if (!is_dir($target . '/.git')) {
            $log->append('Initialising a repository in the existing directory.');
            $init = $this->git($target, [], ['init', '--quiet']);

            if (!$init->succeeded()) {
                throw new RuntimeException('Unable to initialise a repository: ' . $init->output());
            }
        }

        $existing = trim($this->git($target, [], ['config', '--get', 'remote.origin.url'])->stdout);

        if ($existing === '') {
            $this->git($target, [], ['remote', 'add', 'origin', $remote->withoutCredentials()]);
        } elseif ($existing !== $remote->withoutCredentials()) {
            $this->git($target, [], ['remote', 'set-url', 'origin', $remote->withoutCredentials()]);
        }

        $this->git($target, [], ['config', 'submodule.recurse', 'false']);
    }

    private function remoteHead(Repository $repository, RemoteUrl $remote, array $environment, DeployLog $log): ?string
    {
        $result = $this->git(null, $environment, ['ls-remote', '--heads', $remote->withoutCredentials(), $repository->branch]);

        if (!$result->succeeded()) {
            $log->appendResult('ls-remote', $result);

            throw new RuntimeException($this->explain($result, $repository));
        }

        if (preg_match('/^([0-9a-f]{40})\s/m', $result->stdout, $m) === 1) {
            return $m[1];
        }

        throw new RuntimeException(sprintf('The branch "%s" does not exist on the remote.', $repository->branch));
    }

    private function localHead(string $target): ?string
    {
        $result = $this->git($target, [], ['rev-parse', 'HEAD']);
        $head = trim($result->stdout);

        return $result->succeeded() && $head !== '' ? $head : null;
    }

    private function stashKept(Repository $repository, string $target): array
    {
        $kept = [];

        foreach ($repository->keep as $relative) {
            $path = $target . '/' . trim($relative, '/');

            if (is_file($path)) {
                $kept[$relative] = (string) file_get_contents($path);
            }
        }

        return $kept;
    }

    private function restoreKept(array $kept, string $target): void
    {
        foreach ($kept as $relative => $contents) {
            $path = $target . '/' . trim((string) $relative, '/');
            $dir = dirname($path);

            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            @file_put_contents($path, $contents);
        }
    }

    private function protectGitDirectory(string $target): void
    {
        $gitDir = $target . '/.git';

        if (!is_dir($gitDir)) {
            return;
        }

        $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";

        $file = $gitDir . '/.htaccess';

        if (!is_file($file) || file_get_contents($file) !== $rules) {
            @file_put_contents($file, $rules);
        }

        @chmod($gitDir, 0700);
    }

    private function explain(CommandResult $result, Repository $repository): string
    {
        $output = strtolower($result->output());

        if ($result->timedOut) {
            return 'The Git operation timed out. The remote may be unreachable from this server.';
        }

        if (str_contains($output, 'authentication failed')
            || str_contains($output, 'could not read username')
            || str_contains($output, 'invalid username or password')
            || str_contains($output, 'http basic: access denied')) {
            return $repository->credentialType === CredentialType::Token
                ? 'The access token was rejected. It has probably expired or been revoked; enter a new one.'
                : 'Authentication failed. Add a credential for this repository.';
        }

        if (str_contains($output, 'permission denied (publickey)')) {
            return 'The deploy key was rejected. Add its public key to the repository on the remote.';
        }

        if (str_contains($output, 'repository not found') || str_contains($output, 'not found')) {
            return 'The remote repository was not found. Check the URL, and the credential if it is private.';
        }

        if (str_contains($output, 'could not resolve host')) {
            return 'The server could not reach the remote host. Check outbound network access.';
        }

        return trim($result->output()) !== ''
            ? 'Git reported: ' . substr(trim($result->output()), 0, 400)
            : 'The Git operation failed (' . $result->describe() . ').';
    }

    private function git(?string $workingDirectory, array $environment, array $arguments): CommandResult
    {
        return $this->runner->run(
            array_merge([$this->git->path, '-c', 'submodule.recurse=false'], $arguments),
            $workingDirectory,
            $environment,
        );
    }
}

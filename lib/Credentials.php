<?php

declare(strict_types=1);

namespace Zactonz\Git;

use RuntimeException;

final class Credentials
{
    public function __construct(
        private readonly Paths $paths,
        private readonly Secrets $secrets,
    ) {
    }

    public function storeToken(string $repositoryId, string $token): void
    {
        $this->writePrivate($this->tokenFile($repositoryId), $this->secrets->encrypt($token));
    }

    public function forget(string $repositoryId): void
    {
        foreach ([$this->tokenFile($repositoryId), $this->keyFile($repositoryId), $this->keyFile($repositoryId) . '.pub'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function hasToken(string $repositoryId): bool
    {
        return is_file($this->tokenFile($repositoryId));
    }

    public function hasDeployKey(string $repositoryId): bool
    {
        return is_file($this->keyFile($repositoryId));
    }

    public function publicKey(string $repositoryId): ?string
    {
        $file = $this->keyFile($repositoryId) . '.pub';

        return is_file($file) ? trim((string) file_get_contents($file)) : null;
    }

    public function generateDeployKey(CommandRunner $runner, string $repositoryId, string $comment): string
    {
        $file = $this->keyFile($repositoryId);

        foreach ([$file, $file . '.pub'] as $existing) {
            if (is_file($existing)) {
                @unlink($existing);
            }
        }

        $this->ensureKeyDir();

        $result = $runner->run([
            '/usr/bin/ssh-keygen',
            '-t', 'ed25519',
            '-N', '',
            '-C', $comment,
            '-f', $file,
        ]);

        if (!$result->succeeded() || !is_file($file . '.pub')) {
            throw new RuntimeException('Unable to generate a deploy key: ' . $result->output());
        }

        chmod($file, 0600);
        chmod($file . '.pub', 0600);

        return trim((string) file_get_contents($file . '.pub'));
    }

    public function environmentFor(Repository $repository, string $pluginDir): array
    {
        return match ($repository->credentialType) {
            CredentialType::None => [],
            CredentialType::Token => $this->tokenEnvironment($repository, $pluginDir),
            CredentialType::DeployKey => $this->deployKeyEnvironment($repository),
        };
    }

    private function tokenEnvironment(Repository $repository, string $pluginDir): array
    {
        $token = $this->secrets->decrypt(
            is_file($this->tokenFile($repository->id))
                ? (string) file_get_contents($this->tokenFile($repository->id))
                : null,
        );

        if ($token === null) {
            throw new RuntimeException('The stored access token could not be read. Enter it again.');
        }

        return [
            'GIT_ASKPASS' => $pluginDir . '/bin/askpass.sh',
            'ZCTZGIT_TOKEN' => $token,
            'ZCTZGIT_USERNAME' => 'x-access-token',
        ];
    }

    private function deployKeyEnvironment(Repository $repository): array
    {
        $file = $this->keyFile($repository->id);

        if (!is_file($file)) {
            throw new RuntimeException('No deploy key exists for this repository. Generate one first.');
        }

        return [
            'GIT_SSH_COMMAND' => sprintf(
                'ssh -i %s -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o BatchMode=yes',
                escapeshellarg($file),
            ),
        ];
    }

    private function tokenFile(string $repositoryId): string
    {
        return $this->paths->keyDir() . '/' . $this->safe($repositoryId) . '.token';
    }

    private function keyFile(string $repositoryId): string
    {
        return $this->paths->keyDir() . '/' . $this->safe($repositoryId) . '.key';
    }

    private function safe(string $repositoryId): string
    {
        return (string) preg_replace('/[^0-9a-f]/', '', $repositoryId);
    }

    private function ensureKeyDir(): void
    {
        $dir = $this->paths->keyDir();

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create ' . $dir);
        }

        @chmod($dir, 0700);
    }

    private function writePrivate(string $file, string $contents): void
    {
        $this->ensureKeyDir();

        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $contents) === false) {
            throw new RuntimeException('Unable to store the credential.');
        }

        chmod($temporary, 0600);
        rename($temporary, $file);
    }
}

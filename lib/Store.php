<?php

declare(strict_types=1);

namespace Zactonz\Git;

use RuntimeException;

final class Store
{
    private const SCHEMA = 2;

    public function __construct(private readonly Paths $paths)
    {
    }

    public function all(): array
    {
        $file = $this->paths->reposFile();

        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        if (!is_array($decoded)) {
            return [];
        }

        $rows = $this->isLegacy($decoded) ? $this->migrate($decoded) : (array) ($decoded['repositories'] ?? []);

        $repositories = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $repository = Repository::fromArray($row);
                $repositories[$repository->id] = $repository;
            }
        }

        return $repositories;
    }

    public function find(string $id): ?Repository
    {
        return $this->all()[$id] ?? null;
    }

    public function findByWebhookSecret(string $secret): ?Repository
    {
        if ($secret === '') {
            return null;
        }

        foreach ($this->all() as $repository) {
            if ($repository->webhookSecret !== null
                && hash_equals($repository->webhookSecret, $secret)) {
                return $repository;
            }
        }

        return null;
    }

    public function save(Repository $repository): void
    {
        $repositories = $this->all();
        $repositories[$repository->id] = $repository;
        $this->write($repositories);
    }

    public function delete(string $id): bool
    {
        $repositories = $this->all();

        if (!isset($repositories[$id])) {
            return false;
        }

        unset($repositories[$id]);
        $this->write($repositories);

        return true;
    }

    private function isLegacy(array $decoded): bool
    {
        return !isset($decoded['schema']) && array_is_list($decoded);
    }

    private function migrate(array $legacy): array
    {
        $rows = [];

        foreach ($legacy as $old) {
            if (!is_array($old)) {
                continue;
            }

            $rows[] = [
                'id' => (string) ($old['repo_secret_id'] ?? bin2hex(random_bytes(16))),
                'name' => (string) ($old['repo_name'] ?? 'Untitled'),
                'url' => (string) ($old['repo_url'] ?? ''),
                'branch' => (string) ($old['branch'] ?? 'main'),
                'target_dir' => (string) ($old['destination_dir'] ?? ''),
                'mode' => DeployMode::Safe->value,
                'credential_type' => !empty($old['github_token'])
                    ? CredentialType::Token->value
                    : CredentialType::None->value,
                'auto_sync' => (bool) ($old['auto_sync'] ?? false),
                'webhook_secret' => bin2hex(random_bytes(32)),
                'last_sync' => isset($old['last_sync']) ? (int) $old['last_sync'] : null,
                'last_status' => null,
                'keep' => [],
            ];
        }

        return $rows;
    }

    private function write(array $repositories): void
    {
        $this->ensureConfigDir();

        $payload = [
            'schema' => self::SCHEMA,
            'repositories' => array_values(array_map(
                static fn (Repository $repository): array => $repository->toArray(),
                $repositories,
            )),
        ];

        $file = $this->paths->reposFile();
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Unable to encode the repository configuration.');
        }

        if (file_put_contents($temporary, $encoded . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the repository configuration.');
        }

        chmod($temporary, 0600);

        if (!rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to replace the repository configuration.');
        }
    }

    private function ensureConfigDir(): void
    {
        foreach ([$this->paths->configDir(), $this->paths->logDir(), $this->paths->lockDir(), $this->paths->keyDir()] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Unable to create ' . $dir);
            }
            @chmod($dir, 0700);
        }
    }
}

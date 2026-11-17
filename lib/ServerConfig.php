<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class ServerConfig
{
    public const FILE = '/etc/zctzgit/config.json';

    private const DEFAULT_PROVIDERS = [
        'github.com',
        'gitlab.com',
        'bitbucket.org',
        'codeberg.org',
    ];

    private const DEFAULT_COMMANDS = [
        'composer install --no-dev --no-interaction --prefer-dist',
        'npm ci --omit=dev',
        'npm run build',
    ];

    public function __construct(
        public readonly array $allowedHosts,
        public readonly array $allowedCommands,
        public readonly bool $allowTokens,
        public readonly bool $blockUnpatchedGit,
        public readonly int $deployTimeoutSeconds,
        public readonly bool $allowLocalPaths = false,
    ) {
    }

    public static function load(string $file = self::FILE): self
    {
        $decoded = is_file($file) && is_readable($file)
            ? json_decode((string) file_get_contents($file), true)
            : null;

        if (!is_array($decoded)) {
            $decoded = [];
        }

        return new self(
            self::hosts($decoded['allowed_hosts'] ?? self::DEFAULT_PROVIDERS),
            array_values(array_filter(array_map('strval', (array) ($decoded['allowed_commands'] ?? self::DEFAULT_COMMANDS)))),
            (bool) ($decoded['allow_tokens'] ?? true),
            (bool) ($decoded['block_unpatched_git'] ?? false),
            max(30, (int) ($decoded['deploy_timeout_seconds'] ?? 600)),
            (bool) ($decoded['allow_local_paths'] ?? false),
        );
    }

    private static function hosts(mixed $raw): array
    {
        $hosts = [];

        foreach ((array) $raw as $host) {
            $host = strtolower(trim((string) $host));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    public function permitsHost(string $host): bool
    {
        $host = strtolower($host);

        foreach ($this->allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    public function permitsCommand(string $command): bool
    {
        return in_array(trim($command), $this->allowedCommands, true);
    }
}

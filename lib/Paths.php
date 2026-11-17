<?php

declare(strict_types=1);

namespace Zactonz\Git;

use RuntimeException;

final class Paths
{
    public function __construct(
        public readonly string $username,
        public readonly string $home,
    ) {
    }

    public static function forCurrentAccount(): self
    {
        $username = self::resolveUsername();
        $home = rtrim((string) (getenv('HOME') ?: ''), '/');

        if ($home === '' || !str_contains($home, $username)) {
            $home = '/home/' . $username;
        }

        return new self($username, $home);
    }

    public static function fromRequest(array $server): self
    {
        foreach (['SCRIPT_FILENAME', 'DOCUMENT_ROOT'] as $key) {
            $candidate = (string) ($server[$key] ?? '');
            if ($candidate !== '' && preg_match('#^(/home\d*/)([^/]+)/#', $candidate, $m) === 1) {
                return new self($m[2], rtrim($m[1] . $m[2], '/'));
            }
        }

        $username = self::resolveUsername();

        if ($username === '') {
            throw new RuntimeException('Unable to determine the cPanel account for this request.');
        }

        return self::forCurrentAccount();
    }

    private static function resolveUsername(): string
    {
        $username = (string) (getenv('USER') ?: '');
        if ($username !== '' && $username !== 'nobody' && $username !== 'root') {
            return $username;
        }

        if (function_exists('posix_geteuid')) {
            $owner = posix_getpwuid(posix_geteuid());
            $name = is_array($owner) ? (string) ($owner['name'] ?? '') : '';
            if ($name !== '' && $name !== 'nobody') {
                return $name;
            }
        }

        return '';
    }

    public function configDir(): string
    {
        return $this->home . '/.zctzgit';
    }

    public function reposFile(): string
    {
        return $this->configDir() . '/repos.json';
    }

    public function logDir(): string
    {
        return $this->configDir() . '/logs';
    }

    public function lockDir(): string
    {
        return $this->configDir() . '/locks';
    }

    public function keyDir(): string
    {
        return $this->configDir() . '/keys';
    }

    public function resolveTarget(string $relativePath): string
    {
        $trimmed = trim($relativePath, '/');

        if ($trimmed === ''
            || str_contains($trimmed, "\0")
            || preg_match('#(^|/)\.\.(/|$)#', $trimmed) === 1) {
            throw new RuntimeException('The repository path must sit inside the account home directory.');
        }

        return $this->home . '/' . $trimmed;
    }

    public function relativise(string $absolutePath): string
    {
        if (str_starts_with($absolutePath, $this->home . '/')) {
            return substr($absolutePath, strlen($this->home) + 1);
        }

        return $absolutePath;
    }
}

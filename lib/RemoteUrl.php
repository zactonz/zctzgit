<?php

declare(strict_types=1);

namespace Zactonz\Git;

use RuntimeException;

final class RemoteUrl
{
    private function __construct(
        public readonly string $original,
        public readonly string $host,
        public readonly bool $isSsh,
    ) {
    }

    public static function parse(string $url, ServerConfig $config): self
    {
        $url = trim($url);

        if ($url === '' || str_contains($url, "\0") || preg_match('/[\r\n]/', $url) === 1) {
            throw new RuntimeException('That clone URL is not valid.');
        }

        if (str_starts_with($url, '-')) {
            throw new RuntimeException('A clone URL cannot begin with a dash.');
        }

        if ($config->allowLocalPaths) {
            $local = str_starts_with($url, 'file://') ? substr($url, 7) : $url;

            if (str_starts_with($local, '/') && is_dir($local)) {
                return new self($local, 'localhost', false);
            }
        }

        if (preg_match('#^(?:ssh://)?git@([a-z0-9.-]+)[:/]([\w.\-/]+?)(?:\.git)?$#i', $url, $m) === 1) {
            $host = strtolower($m[1]);
            self::assertAllowed($host, $config);

            return new self($url, $host, true);
        }

        if (preg_match('#^https://([a-z0-9.-]+)(?::\d+)?/([\w.\-/]+?)(?:\.git)?/?$#i', $url, $m) === 1) {
            $host = strtolower($m[1]);
            self::assertAllowed($host, $config);

            return new self($url, $host, false);
        }

        throw new RuntimeException('Use an https:// or git@ clone URL.');
    }

    private static function assertAllowed(string $host, ServerConfig $config): void
    {
        if (!$config->permitsHost($host)) {
            throw new RuntimeException(sprintf('%s is not an allowed Git host on this server.', $host));
        }
    }

    public function withoutCredentials(): string
    {
        return $this->original;
    }
}

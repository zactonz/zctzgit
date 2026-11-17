<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class GitBinary
{
    private const CANDIDATES = [
        '/usr/local/cpanel/3rdparty/bin/git',
        '/usr/local/bin/git',
        '/usr/bin/git',
        '/bin/git',
        '/opt/homebrew/bin/git',
    ];

    private function __construct(
        public readonly string $path,
        public readonly GitVersion $version,
    ) {
    }

    public static function locate(CommandRunner $runner, array $candidates = self::CANDIDATES): ?self
    {
        foreach ($candidates as $candidate) {
            if (!is_file($candidate) || !is_executable($candidate)) {
                continue;
            }

            $result = $runner->run([$candidate, '--version']);

            if (!$result->succeeded()) {
                continue;
            }

            $version = GitVersion::parse($result->stdout);

            if ($version !== null) {
                return new self($candidate, $version);
            }
        }

        return null;
    }

    public function status(): GitVersionStatus
    {
        return $this->version->status();
    }

    public function describe(): string
    {
        return sprintf('%s (%s)', $this->version, $this->path);
    }
}

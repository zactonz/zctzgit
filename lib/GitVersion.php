<?php

declare(strict_types=1);

namespace Zactonz\Git;

use Stringable;

final class GitVersion implements Stringable
{
    private const PATCHED_IN_SERIES = [
        '2.56' => '2.56.1',
        '2.55' => '2.55.1',
        '2.54' => '2.54.1',
        '2.53' => '2.53.1',
        '2.52' => '2.52.1',
        '2.51' => '2.51.3',
        '2.50' => '2.50.2',
    ];

    private const OLDEST_SERVICED_SERIES = '2.50';

    private function __construct(
        public readonly string $version,
        public readonly string $series,
    ) {
    }

    public static function parse(string $output): ?self
    {
        if (preg_match('/\b(\d+)\.(\d+)(?:\.(\d+))?/', $output, $m) !== 1) {
            return null;
        }

        $version = $m[1] . '.' . $m[2] . '.' . ($m[3] ?? '0');

        return new self($version, $m[1] . '.' . $m[2]);
    }

    public function status(): GitVersionStatus
    {
        if (version_compare($this->series, self::OLDEST_SERVICED_SERIES, '<')) {
            return GitVersionStatus::Unserviced;
        }

        $patched = self::PATCHED_IN_SERIES[$this->series] ?? null;

        if ($patched === null) {
            return GitVersionStatus::Supported;
        }

        return version_compare($this->version, $patched, '>=')
            ? GitVersionStatus::Supported
            : GitVersionStatus::Vulnerable;
    }

    public function patchedRelease(): ?string
    {
        return self::PATCHED_IN_SERIES[$this->series] ?? null;
    }

    public function __toString(): string
    {
        return $this->version;
    }
}

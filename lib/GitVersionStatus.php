<?php

declare(strict_types=1);

namespace Zactonz\Git;

enum GitVersionStatus
{
    case Supported;
    case Vulnerable;
    case Unserviced;

    public function isUsable(): bool
    {
        return $this !== self::Unserviced;
    }

    public function message(GitVersion $version): string
    {
        return match ($this) {
            self::Supported => sprintf('Git %s is a patched release.', $version),
            self::Vulnerable => sprintf(
                'Git %s is missing the security fixes released on 10 November 2026. Update to %s.',
                $version,
                (string) $version->patchedRelease(),
            ),
            self::Unserviced => sprintf(
                'Git %s is from a series that upstream no longer services. Some distributions backport '
                . 'fixes without changing the version, so check with yours before assuming it is unpatched; '
                . 'otherwise update to 2.50.2 or newer.',
                $version,
            ),
        };
    }
}

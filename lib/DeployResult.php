<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class DeployResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $summary,
        public readonly ?string $revision,
        public readonly bool $skipped,
    ) {
    }

    public static function success(string $summary, ?string $revision): self
    {
        return new self(true, $summary, $revision, false);
    }

    public static function unchanged(string $revision): self
    {
        return new self(true, 'Already up to date.', $revision, true);
    }

    public static function failure(string $summary): self
    {
        return new self(false, $summary, null, false);
    }
}

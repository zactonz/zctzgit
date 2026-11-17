<?php

declare(strict_types=1);

namespace Zactonz\Git;

enum DeployMode: string
{
    case Safe = 'safe';
    case Force = 'force';

    public static function from_(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Safe;
    }

    public function label(): string
    {
        return match ($this) {
            self::Safe => 'Stop if the working tree has local changes',
            self::Force => 'Discard local changes and match the remote',
        };
    }
}

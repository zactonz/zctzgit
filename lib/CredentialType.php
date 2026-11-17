<?php

declare(strict_types=1);

namespace Zactonz\Git;

enum CredentialType: string
{
    case None = 'none';
    case Token = 'token';
    case DeployKey = 'deploy_key';

    public static function from_(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::None;
    }
}

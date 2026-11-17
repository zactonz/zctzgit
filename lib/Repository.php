<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class Repository
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly string $branch,
        public readonly string $targetDir,
        public readonly DeployMode $mode,
        public readonly CredentialType $credentialType,
        public readonly bool $autoSync,
        public readonly ?string $webhookSecret,
        public readonly ?int $lastSync,
        public readonly ?string $lastStatus,
        public readonly array $keep,
    ) {
    }

    public static function create(
        string $name,
        string $url,
        string $branch,
        string $targetDir,
        DeployMode $mode,
        CredentialType $credentialType,
        bool $autoSync,
        array $keep = [],
    ): self {
        return new self(
            bin2hex(random_bytes(16)),
            $name,
            $url,
            $branch,
            $targetDir,
            $mode,
            $credentialType,
            $autoSync,
            bin2hex(random_bytes(32)),
            null,
            null,
            $keep,
        );
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? bin2hex(random_bytes(16))),
            (string) ($row['name'] ?? 'Untitled'),
            (string) ($row['url'] ?? ''),
            (string) ($row['branch'] ?? 'main'),
            (string) ($row['target_dir'] ?? ''),
            DeployMode::from_($row['mode'] ?? null),
            CredentialType::from_($row['credential_type'] ?? null),
            (bool) ($row['auto_sync'] ?? false),
            isset($row['webhook_secret']) ? (string) $row['webhook_secret'] : null,
            isset($row['last_sync']) ? (int) $row['last_sync'] : null,
            isset($row['last_status']) ? (string) $row['last_status'] : null,
            array_values(array_filter(array_map('strval', (array) ($row['keep'] ?? [])))),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'branch' => $this->branch,
            'target_dir' => $this->targetDir,
            'mode' => $this->mode->value,
            'credential_type' => $this->credentialType->value,
            'auto_sync' => $this->autoSync,
            'webhook_secret' => $this->webhookSecret,
            'last_sync' => $this->lastSync,
            'last_status' => $this->lastStatus,
            'keep' => $this->keep,
        ];
    }

    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->toArray(), $changes));
    }
}

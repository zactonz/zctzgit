<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class DeliveryLedger
{
    private const KEEP = 200;

    public function __construct(private readonly Paths $paths)
    {
    }

    public function isReplay(?string $deliveryId): bool
    {
        if ($deliveryId === null || $deliveryId === '') {
            return false;
        }

        $seen = $this->read();

        if (in_array($deliveryId, $seen, true)) {
            return true;
        }

        $seen[] = $deliveryId;
        $this->write(array_slice($seen, -self::KEEP));

        return false;
    }

    private function file(): string
    {
        return $this->paths->configDir() . '/deliveries.json';
    }

    private function read(): array
    {
        if (!is_file($this->file())) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->file()), true);

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    private function write(array $ids): void
    {
        $dir = $this->paths->configDir();

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return;
        }

        @file_put_contents($this->file(), json_encode(array_values($ids)), LOCK_EX);
        @chmod($this->file(), 0600);
    }
}

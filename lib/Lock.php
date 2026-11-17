<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class Lock
{
    /** @var resource|null */
    private $handle = null;

    private function __construct(private readonly string $file)
    {
    }

    public static function acquire(Paths $paths, string $repositoryId): ?self
    {
        $dir = $paths->lockDir();

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }

        $lock = new self($dir . '/' . preg_replace('/[^0-9a-f]/', '', $repositoryId) . '.lock');
        $handle = @fopen($lock->file, 'c');

        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        ftruncate($handle, 0);
        fwrite($handle, (string) getmypid());
        fflush($handle);
        $lock->handle = $handle;

        return $lock;
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
        @unlink($this->file);
    }

    public function __destruct()
    {
        $this->release();
    }
}

<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class DeployLog
{
    private const MAX_BYTES = 131072;

    public function __construct(
        private readonly Paths $paths,
        private readonly string $repositoryId,
    ) {
    }

    public function append(string $line): void
    {
        $dir = $this->paths->logDir();

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return;
        }

        $file = $this->file();
        $this->rotate($file);

        @file_put_contents(
            $file,
            sprintf('[%s] %s%s', gmdate('Y-m-d H:i:s'), rtrim($line), PHP_EOL),
            FILE_APPEND | LOCK_EX,
        );

        @chmod($file, 0600);
    }

    public function appendResult(string $label, CommandResult $result): void
    {
        $this->append(sprintf('%s — %s', $label, $result->describe()));

        $output = $result->output();

        if ($output !== '') {
            foreach (explode("\n", $output) as $line) {
                $this->append('    ' . $line);
            }
        }
    }

    public function tail(int $lines = 200): string
    {
        $file = $this->file();

        if (!is_file($file)) {
            return '';
        }

        $all = explode("\n", rtrim((string) file_get_contents($file), "\n"));

        return implode("\n", array_slice($all, -$lines));
    }

    public function clear(): void
    {
        @unlink($this->file());
    }

    private function file(): string
    {
        return $this->paths->logDir() . '/' . preg_replace('/[^0-9a-f]/', '', $this->repositoryId) . '.log';
    }

    private function rotate(string $file): void
    {
        if (!is_file($file) || filesize($file) < self::MAX_BYTES) {
            return;
        }

        $kept = array_slice(explode("\n", (string) file_get_contents($file)), -400);
        @file_put_contents($file, implode("\n", $kept), LOCK_EX);
    }
}

<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class CommandResult
{
    public function __construct(
        public readonly array $command,
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly bool $timedOut,
        public readonly float $seconds,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->exitCode === 0 && !$this->timedOut;
    }

    public function output(): string
    {
        return trim($this->stdout . "\n" . $this->stderr);
    }

    public function describe(): string
    {
        if ($this->timedOut) {
            return sprintf('timed out after %.0fs', $this->seconds);
        }

        return sprintf('exit %d in %.2fs', $this->exitCode, $this->seconds);
    }
}

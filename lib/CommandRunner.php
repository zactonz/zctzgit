<?php

declare(strict_types=1);

namespace Zactonz\Git;

use RuntimeException;

final class CommandRunner
{
    public function __construct(
        private readonly int $timeoutSeconds = 300,
        private readonly int $maxOutputBytes = 262144,
    ) {
    }

    public function run(array $command, ?string $workingDirectory = null, array $environment = []): CommandResult
    {
        if ($command === []) {
            throw new RuntimeException('A command requires at least a program name.');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $startedAt = microtime(true);

        $process = @proc_open(
            $command,
            $descriptors,
            $pipes,
            $workingDirectory,
            $this->environmentFor($environment),
        );

        if (!is_resource($process)) {
            return new CommandResult($command, 127, '', 'Unable to start ' . $command[0], false, 0.0);
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $captured = [1 => '', 2 => ''];
        $timedOut = false;

        while (true) {
            $read = array_filter([1 => $pipes[1], 2 => $pipes[2]], static fn ($pipe) => !feof($pipe));

            if ($read === []) {
                break;
            }

            if (microtime(true) - $startedAt > $this->timeoutSeconds) {
                $timedOut = true;
                break;
            }

            $write = null;
            $except = null;
            $ready = $read;

            if (@stream_select($ready, $write, $except, 0, 200000) === false) {
                break;
            }

            foreach ($ready as $pipe) {
                $key = $pipe === $pipes[1] ? 1 : 2;
                $chunk = fread($pipe, 8192);
                if ($chunk === false || $chunk === '') {
                    continue;
                }
                if (strlen($captured[$key]) < $this->maxOutputBytes) {
                    $captured[$key] .= $chunk;
                }
            }
        }

        if ($timedOut) {
            $this->terminate($process);
        }

        foreach ([$pipes[1], $pipes[2]] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exitCode = proc_close($process);

        return new CommandResult(
            $command,
            $timedOut ? 124 : $exitCode,
            $this->cap($captured[1]),
            $this->cap($captured[2]),
            $timedOut,
            microtime(true) - $startedAt,
        );
    }

    private function terminate($process): void
    {
        proc_terminate($process, SIGTERM);

        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);
            if (!($status['running'] ?? false)) {
                return;
            }
            usleep(100000);
        }

        proc_terminate($process, SIGKILL);
    }

    private function cap(string $text): string
    {
        if (strlen($text) <= $this->maxOutputBytes) {
            return $text;
        }

        return substr($text, 0, $this->maxOutputBytes) . "\n[output truncated]";
    }

    private function environmentFor(array $overrides): array
    {
        $base = [
            'HOME' => (string) (getenv('HOME') ?: ''),
            'PATH' => '/usr/local/bin:/usr/bin:/bin',
            'LANG' => 'C',
            'GIT_TERMINAL_PROMPT' => '0',
            'GIT_ASKPASS' => '',
            'SSH_ASKPASS' => '',
            'GIT_CONFIG_NOSYSTEM' => '1',
        ];

        return array_filter(
            array_merge($base, $overrides),
            static fn ($value) => $value !== null,
        );
    }
}

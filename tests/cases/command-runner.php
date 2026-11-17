<?php

declare(strict_types=1);

use Zactonz\Git\CommandRunner;

return static function (TestRun $t): void {
    $t->group('CommandRunner');

    $runner = new CommandRunner(timeoutSeconds: 5);

    $ok = $runner->run(['/bin/echo', 'hello']);
    $t->assert('a successful command reports success', $ok->succeeded());
    $t->same('stdout is captured', 'hello', trim($ok->stdout));
    $t->same('exit code is zero', 0, $ok->exitCode);

    $fail = $runner->run(['/bin/sh', '-c', 'echo out; echo err >&2; exit 3']);
    $t->same('exit code is preserved', 3, $fail->exitCode);
    $t->assert('failure is not success', !$fail->succeeded());
    $t->same('stdout and stderr are kept apart', 'out', trim($fail->stdout));
    $t->same('stderr is captured', 'err', trim($fail->stderr));
    $t->assert('combined output holds both', str_contains($fail->output(), 'out') && str_contains($fail->output(), 'err'));

    $missing = $runner->run(['/nonexistent/binary']);
    $t->assert('a missing binary does not throw', !$missing->succeeded());

    $t->assert(
        'arguments never reach a shell',
        trim($runner->run(['/bin/echo', 'a; rm -rf /tmp/nope', '&&', '$(whoami)'])->stdout)
            === 'a; rm -rf /tmp/nope && $(whoami)',
    );

    $slow = (new CommandRunner(timeoutSeconds: 1))->run(['/bin/sh', '-c', 'sleep 30']);
    $t->assert('a hanging command is stopped', $slow->timedOut);
    $t->same('a timeout reports 124', 124, $slow->exitCode);
    $t->assert('a timeout is not success', !$slow->succeeded());
    $t->assert('a timeout gives up quickly', $slow->seconds < 10.0);
    $t->assert('the description names the timeout', str_contains($slow->describe(), 'timed out'));

    $capped = (new CommandRunner(timeoutSeconds: 10, maxOutputBytes: 512))
        ->run(['/bin/sh', '-c', 'head -c 40000 /dev/zero | tr "\\0" "x"']);
    $t->assert('runaway output is capped', strlen($capped->stdout) < 2048);

    $cwd = $runner->run(['/bin/pwd'], '/tmp');
    $t->assert('the working directory is honoured', str_contains(trim($cwd->stdout), 'tmp'));

    $env = $runner->run(['/bin/sh', '-c', 'echo "[$ZCTZ_PROBE][$GIT_TERMINAL_PROMPT]"'], null, ['ZCTZ_PROBE' => 'set']);
    $t->same('environment is injected and prompts are disabled', '[set][0]', trim($env->stdout));
};

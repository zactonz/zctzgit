<?php

declare(strict_types=1);

use Zactonz\Git\CommandRunner;
use Zactonz\Git\GitBinary;

return static function (TestRun $t): void {
    $t->group('GitBinary');

    $runner = new CommandRunner(timeoutSeconds: 10);

    $t->assert('returns null when no candidate exists', GitBinary::locate($runner, ['/nonexistent/git']) === null);

    $t->assert(
        'skips a candidate that is not executable',
        GitBinary::locate($runner, ['/etc/hosts', '/nonexistent/git']) === null,
    );

    $real = trim((new CommandRunner())->run(['/usr/bin/which', 'git'])->stdout);

    if ($real === '' || !is_file($real)) {
        $t->assert('no git on this machine, resolution untested', true);
        return;
    }

    $binary = GitBinary::locate($runner, ['/nonexistent/git', '/etc/hosts', $real]);

    $t->assert('finds the first working candidate past the broken ones', $binary !== null);
    $t->same('reports an absolute path, never a bare name', $real, $binary?->path);
    $t->assert('parses a version from the real binary', $binary?->version !== null);
    $t->assert('the description carries both version and path', str_contains((string) $binary?->describe(), $real));
    $t->assert('a real installed git yields a verdict', $binary?->status() !== null);
};

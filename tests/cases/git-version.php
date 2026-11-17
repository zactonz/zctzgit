<?php

declare(strict_types=1);

use Zactonz\Git\GitVersion;
use Zactonz\Git\GitVersionStatus;

return static function (TestRun $t): void {
    $t->group('GitVersion');

    $t->same('parses the standard git --version line', '2.56.1', (string) GitVersion::parse('git version 2.56.1'));
    $t->same('parses an Apple build suffix', '2.39.5', (string) GitVersion::parse('git version 2.39.5 (Apple Git-154)'));
    $t->same('treats a two-part version as .0', '2.50.0', (string) GitVersion::parse('git version 2.50'));
    $t->assert('rejects unparseable output', GitVersion::parse('command not found') === null);

    $cases = [
        ['2.56.1', GitVersionStatus::Supported,   'the patched release in its series'],
        ['2.56.0', GitVersionStatus::Vulnerable,  'one patch below its fix (the version cPanel 138.0.13 shipped)'],
        ['2.50.2', GitVersionStatus::Supported,   'the oldest serviced patched release'],
        ['2.50.1', GitVersionStatus::Vulnerable,  'inside the oldest serviced series but unpatched'],
        ['2.49.9', GitVersionStatus::Unserviced,  'below the oldest serviced series'],
        ['2.18.0', GitVersionStatus::Unserviced,  'the minimum the old docs advertised'],
        ['2.51.3', GitVersionStatus::Supported,   'a series whose fix is a third patch'],
        ['2.51.2', GitVersionStatus::Vulnerable,  'one below a third-patch fix'],
        ['2.57.0', GitVersionStatus::Supported,   'newer than the table, assumed fixed'],
        ['3.0.0',  GitVersionStatus::Supported,   'a future major, assumed fixed'],
    ];

    foreach ($cases as [$version, $expected, $why]) {
        $parsed = GitVersion::parse('git version ' . $version);
        $t->same(sprintf('%s is %s — %s', $version, $expected->name, $why), $expected, $parsed?->status());
    }

    $t->assert('unserviced is not usable', !GitVersionStatus::Unserviced->isUsable());
    $t->assert('vulnerable is still usable', GitVersionStatus::Vulnerable->isUsable());

    $vulnerable = GitVersion::parse('git version 2.56.0');
    $t->assert(
        'the vulnerable message names the release to upgrade to',
        str_contains($vulnerable->status()->message($vulnerable), '2.56.1'),
    );
};

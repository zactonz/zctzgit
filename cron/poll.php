<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from cron only.\n");
}

require_once dirname(__DIR__) . '/lib/autoload.php';

use Zactonz\Git\App;
use Zactonz\Git\Paths;

$app = App::forAccount(dirname(__DIR__));
$repositories = $app->store->all();

if ($repositories === []) {
    exit(0);
}

$deployed = 0;
$failed = 0;

foreach ($repositories as $repository) {
    if (!$repository->autoSync) {
        continue;
    }

    try {
        $result = $app->deployer()->deploy($repository, onlyIfRemoteMoved: true);
    } catch (Throwable $e) {
        $app->log($repository->id)->append('Poll failed: ' . $e->getMessage());
        $failed++;
        continue;
    }

    if ($result->skipped) {
        continue;
    }

    $result->ok ? $deployed++ : $failed++;

    printf(
        "%s: %s%s",
        $repository->name,
        $result->ok ? $result->summary : 'FAILED — ' . $result->summary,
        PHP_EOL,
    );
}

exit($failed === 0 ? 0 : 1);

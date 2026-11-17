<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/autoload.php';

use Zactonz\Git\App;
use Zactonz\Git\DeliveryLedger;
use Zactonz\Git\Paths;
use Zactonz\Git\WebhookRequest;

header('Content-Type: text/plain');
header('X-Content-Type-Options: nosniff');

function finish(int $status, string $message): never
{
    http_response_code($status);
    echo $message . "\n";
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    finish(405, 'This endpoint accepts POST requests only.');
}

$body = (string) file_get_contents('php://input');

if ($body === '' || strlen($body) > 2097152) {
    finish(400, 'Unusable payload.');
}

$identifier = basename((string) ($_GET['repository'] ?? ''));

if (preg_match('/^[0-9a-f]{32}$/', $identifier) !== 1) {
    finish(404, 'Not found.');
}

try {
    $paths = Paths::fromRequest($_SERVER);
    $app = App::forPaths($paths, __DIR__);
} catch (Throwable) {
    finish(404, 'Not found.');
}

$repository = $app->store->find($identifier);
$request = WebhookRequest::fromGlobals($_SERVER, $body);

if ($repository === null
    || $repository->webhookSecret === null
    || !$request->verify($repository->webhookSecret)) {
    finish(404, 'Not found.');
}

if ($request->isPing()) {
    finish(200, 'Zactonz Git is listening.');
}

if (!$request->isPush()) {
    finish(202, 'Ignored: not a push event.');
}

if (!$request->matchesBranch($repository->branch)) {
    finish(202, 'Ignored: a different branch.');
}

if (!$repository->autoSync) {
    finish(202, 'Ignored: automatic deployment is off.');
}

if ((new DeliveryLedger($paths))->isReplay($request->deliveryId)) {
    finish(200, 'Already handled.');
}

try {
    $result = $app->deployer()->deploy($repository);
} catch (Throwable $e) {
    $app->log($repository->id)->append('Webhook failed: ' . $e->getMessage());
    finish(500, 'Deployment failed.');
}

finish($result->ok ? 200 : 500, $result->ok ? $result->summary : 'Deployment failed.');

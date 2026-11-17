<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/autoload.php';

use Zactonz\Git\App;
use Zactonz\Git\CredentialType;
use Zactonz\Git\DeployMode;
use Zactonz\Git\RemoteUrl;
use Zactonz\Git\Repository;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function respond(bool $ok, string $message, array $extra = []): never
{
    if (!$ok) {
        http_response_code(400);
    }

    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'This endpoint accepts POST requests only.');
}

try {
    $app = App::forAccount(__DIR__);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

$action = (string) ($_POST['action'] ?? '');
$id = (string) ($_POST['id'] ?? '');

try {
    switch ($action) {
        case 'add':
            $url = trim((string) ($_POST['url'] ?? ''));
            $remote = RemoteUrl::parse($url, $app->config);
            $target = $app->paths->resolveTarget((string) ($_POST['path'] ?? ''));
            $token = trim((string) ($_POST['token'] ?? ''));

            $credential = match (true) {
                (string) ($_POST['credential'] ?? '') === 'deploy_key' => CredentialType::DeployKey,
                $token !== '' => CredentialType::Token,
                default => CredentialType::None,
            };

            if ($credential === CredentialType::Token && !$app->config->allowTokens) {
                respond(false, 'This server does not allow access tokens. Use a deploy key instead.');
            }

            $repository = Repository::create(
                trim((string) ($_POST['name'] ?? 'Untitled')) ?: 'Untitled',
                $remote->withoutCredentials(),
                trim((string) ($_POST['branch'] ?? 'main')) ?: 'main',
                $target,
                DeployMode::from_((string) ($_POST['mode'] ?? '')),
                $credential,
                (string) ($_POST['auto_sync'] ?? '') === '1',
                array_values(array_filter(array_map('trim', explode("\n", (string) ($_POST['keep'] ?? ''))))),
            );

            if ($credential === CredentialType::Token) {
                $app->credentials->storeToken($repository->id, $token);
            }

            $app->store->save($repository);

            if ($credential === CredentialType::DeployKey) {
                $public = $app->credentials->generateDeployKey(
                    $app->runner,
                    $repository->id,
                    sprintf('zctzgit-%s-%s', $app->paths->username, $repository->name),
                );

                respond(true, 'Repository added. Add this deploy key to the repository, then deploy.', [
                    'id' => $repository->id,
                    'public_key' => $public,
                ]);
            }

            respond(true, 'Repository added.', ['id' => $repository->id]);

        case 'deploy':
            $repository = $app->store->find($id);

            if ($repository === null) {
                respond(false, 'That repository no longer exists.');
            }

            $result = $app->deployer()->deploy($repository);
            respond($result->ok, $result->summary, ['revision' => $result->revision]);

        case 'toggle':
            $repository = $app->store->find($id);

            if ($repository === null) {
                respond(false, 'That repository no longer exists.');
            }

            $app->store->save($repository->with(['auto_sync' => !$repository->autoSync]));
            respond(true, $repository->autoSync ? 'Automatic deployment is off.' : 'Automatic deployment is on.');

        case 'delete':
            $app->credentials->forget($id);
            $app->log($id)->clear();
            respond($app->store->delete($id), $app->store->find($id) === null
                ? 'Repository removed. The files on the server were left in place.'
                : 'That repository no longer exists.');

        case 'log':
            if ($app->store->find($id) === null) {
                respond(false, 'That repository no longer exists.');
            }

            respond(true, 'ok', ['log' => $app->log($id)->tail()]);

        case 'public_key':
            respond(true, 'ok', ['public_key' => $app->credentials->publicKey($id) ?? '']);

        default:
            respond(false, 'Unknown action.');
    }
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

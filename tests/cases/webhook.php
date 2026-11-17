<?php

declare(strict_types=1);

use Zactonz\Git\DeliveryLedger;
use Zactonz\Git\Paths;
use Zactonz\Git\WebhookRequest;

return static function (TestRun $t): void {
    $t->group('WebhookRequest');

    $secret = bin2hex(random_bytes(32));
    $body = json_encode(['ref' => 'refs/heads/main', 'after' => 'abc']);
    $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

    $github = WebhookRequest::fromGlobals([
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_GITHUB_DELIVERY' => 'delivery-1',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);

    $t->same('identifies github', 'github', $github->provider);
    $t->same('reads the event', 'push', $github->event);
    $t->assert('recognises a push', $github->isPush());
    $t->assert('accepts a correct signature', $github->verify($secret));
    $t->assert('rejects the wrong secret', !$github->verify(bin2hex(random_bytes(32))));
    $t->assert('matches the configured branch', $github->matchesBranch('main'));
    $t->assert('rejects a push to another branch', !$github->matchesBranch('develop'));

    $tampered = WebhookRequest::fromGlobals([
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body . ' ');
    $t->assert('rejects a modified body under a valid-looking signature', !$tampered->verify($secret));

    $unsigned = WebhookRequest::fromGlobals(['HTTP_X_GITHUB_EVENT' => 'push'], $body);
    $t->assert('rejects a request carrying no signature at all', !$unsigned->verify($secret));

    $sha1Only = WebhookRequest::fromGlobals([
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_HUB_SIGNATURE' => 'sha1=' . hash_hmac('sha1', $body, $secret),
    ], $body);
    $t->assert('ignores the legacy sha1 header', !$sha1Only->verify($secret));

    $ping = WebhookRequest::fromGlobals([
        'HTTP_X_GITHUB_EVENT' => 'ping',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', '{}', $secret),
    ], '{}');
    $t->assert('recognises a ping', $ping->isPing());
    $t->assert('a ping is not a push', !$ping->isPush());
    $t->assert('a correctly signed ping verifies', $ping->verify($secret));

    $gitlab = WebhookRequest::fromGlobals([
        'HTTP_X_GITLAB_EVENT' => 'Push Hook',
        'HTTP_X_GITLAB_TOKEN' => $secret,
    ], $body);
    $t->same('identifies gitlab', 'gitlab', $gitlab->provider);
    $t->assert('accepts a matching gitlab token', $gitlab->verify($secret));
    $t->assert('rejects a wrong gitlab token', !$gitlab->verify('nope'));
    $t->assert('recognises a gitlab push', $gitlab->isPush());

    $gitea = WebhookRequest::fromGlobals([
        'HTTP_X_GITEA_EVENT' => 'push',
        'HTTP_X_GITEA_SIGNATURE' => hash_hmac('sha256', $body, $secret),
    ], $body);
    $t->same('identifies gitea', 'gitea', $gitea->provider);
    $t->assert('accepts a bare gitea signature', $gitea->verify($secret));

    $noRef = WebhookRequest::fromGlobals([
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', 'not json', $secret),
    ], 'not json');
    $t->assert('a body that is not json still verifies on bytes', $noRef->verify($secret));
    $t->assert('an absent ref does not block the branch check', $noRef->matchesBranch('anything'));

    $t->group('DeliveryLedger');

    $home = sys_get_temp_dir() . '/zctz-ledger-' . bin2hex(random_bytes(6));
    mkdir($home, 0700, true);
    $ledger = new DeliveryLedger(new Paths('u', $home));

    $t->assert('a new delivery is not a replay', !$ledger->isReplay('abc'));
    $t->assert('the same delivery again is a replay', $ledger->isReplay('abc'));
    $t->assert('a different delivery is not', !$ledger->isReplay('def'));
    $t->assert('a null delivery id is never a replay', !$ledger->isReplay(null));
    $t->assert('an empty delivery id is never a replay', !$ledger->isReplay(''));
    $t->assert('the ledger survives a reload', (new DeliveryLedger(new Paths('u', $home)))->isReplay('abc'));
};

<?php

declare(strict_types=1);

namespace Zactonz\Git;

final class WebhookRequest
{
    private function __construct(
        public readonly string $provider,
        public readonly string $event,
        public readonly ?string $deliveryId,
        public readonly ?string $ref,
        public readonly string $body,
        public readonly array $headers,
    ) {
    }

    public static function fromGlobals(array $server, string $body): self
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = (string) $value;
            }
        }

        $payload = json_decode($body, true);
        $payload = is_array($payload) ? $payload : [];

        $provider = match (true) {
            isset($headers['x-hub-signature-256']), isset($headers['x-github-event']) => 'github',
            isset($headers['x-gitlab-token']), isset($headers['x-gitlab-event']) => 'gitlab',
            isset($headers['x-gitea-signature']), isset($headers['x-gitea-event']) => 'gitea',
            default => 'unknown',
        };

        $event = $headers['x-github-event']
            ?? $headers['x-gitlab-event']
            ?? $headers['x-gitea-event']
            ?? 'unknown';

        return new self(
            $provider,
            strtolower($event),
            $headers['x-github-delivery'] ?? $headers['x-gitea-delivery'] ?? null,
            isset($payload['ref']) ? (string) $payload['ref'] : null,
            $body,
            $headers,
        );
    }

    public function isPing(): bool
    {
        return $this->event === 'ping' || $this->event === 'system hook';
    }

    public function isPush(): bool
    {
        return str_contains($this->event, 'push');
    }

    public function matchesBranch(string $branch): bool
    {
        if ($this->ref === null) {
            return true;
        }

        return $this->ref === 'refs/heads/' . $branch;
    }

    public function verify(string $secret): bool
    {
        $signature = $this->headers['x-hub-signature-256'] ?? $this->headers['x-gitea-signature'] ?? null;

        if ($signature !== null) {
            $expected = hash_hmac('sha256', $this->body, $secret);
            $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

            return strlen($provided) === strlen($expected) && hash_equals($expected, $provided);
        }

        $token = $this->headers['x-gitlab-token'] ?? null;

        if ($token !== null) {
            return strlen($token) === strlen($secret) && hash_equals($secret, $token);
        }

        return false;
    }
}

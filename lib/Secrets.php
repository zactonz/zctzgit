<?php

declare(strict_types=1);

namespace Zactonz\Git;

use RuntimeException;

final class Secrets
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_BYTES = 16;

    public function __construct(private readonly Paths $paths)
    {
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->key();
        $ivLength = (int) openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt the credential.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }

        $raw = base64_decode($encoded, true);
        $ivLength = (int) openssl_cipher_iv_length(self::CIPHER);

        if ($raw === false || strlen($raw) <= $ivLength + self::TAG_BYTES) {
            return null;
        }

        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, self::TAG_BYTES);
        $ciphertext = substr($raw, $ivLength + self::TAG_BYTES);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : $plaintext;
    }

    private function key(): string
    {
        $file = $this->paths->configDir() . '/secret.key';

        if (is_file($file)) {
            $key = (string) file_get_contents($file);
            if (strlen($key) === 32) {
                return $key;
            }
        }

        $dir = $this->paths->configDir();

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create ' . $dir);
        }

        $key = random_bytes(32);
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $key) === false) {
            throw new RuntimeException('Unable to create the credential key.');
        }

        chmod($temporary, 0600);
        rename($temporary, $file);

        return $key;
    }
}

<?php

declare(strict_types=1);

namespace Krw\HubClient\Support;

final class Pkce
{
    /**
     * @return array{verifier: string, challenge: string, method: string}
     */
    public static function generate(int $length = 64): array
    {
        $verifier = self::randomString($length);

        return [
            'verifier' => $verifier,
            'challenge' => self::challenge($verifier),
            'method' => 'S256',
        ];
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function randomString(int $length = 64): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
        $max = strlen($alphabet) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, $max)];
        }

        return $result;
    }
}

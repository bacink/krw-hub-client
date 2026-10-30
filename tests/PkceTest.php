<?php

namespace Krw\HubClient\Tests;

use Krw\HubClient\Support\Pkce;
use PHPUnit\Framework\TestCase;

class PkceTest extends TestCase
{
    public function test_challenge_is_base64url_sha256(): void
    {
        $verifier = str_repeat('a', 64);

        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $this->assertSame($expected, Pkce::challenge($verifier));
    }

    public function test_generate_returns_a_consistent_pair(): void
    {
        $pair = Pkce::generate();

        $this->assertSame(64, strlen($pair['verifier']));
        $this->assertSame('S256', $pair['method']);
        $this->assertSame(Pkce::challenge($pair['verifier']), $pair['challenge']);
    }

    public function test_random_string_uses_pkce_alphabet(): void
    {
        $value = Pkce::randomString(128);

        $this->assertSame(128, strlen($value));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]+$/', $value);
    }
}

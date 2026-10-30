<?php

namespace Krw\HubClient\Tests;

use Illuminate\Http\Client\Factory;
use Krw\HubClient\Exceptions\HubAuthenticationException;
use Krw\HubClient\Exceptions\HubRequestException;
use Krw\HubClient\HubClient;
use PHPUnit\Framework\TestCase;

class HubClientTest extends TestCase
{
    private function client(Factory $http): HubClient
    {
        return new HubClient($http, [
            'base_url' => 'https://hub.test',
            'client_id' => 'hub_test',
            'client_secret' => 'secret',
            'redirect_uri' => 'https://app.test/auth/hub/callback',
            'timeout' => 5,
        ]);
    }

    public function test_it_obtains_and_caches_the_app_token(): void
    {
        $http = new Factory;
        $http->fake([
            'https://hub.test/api/v1/apps/token' => $http->response([
                'data' => [
                    'access_token' => 'tok-1',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                    'scope' => 'si-asn:read',
                ],
            ]),
        ]);

        $client = $this->client($http);

        $this->assertSame('tok-1', $client->appToken()['access_token']);
        $this->assertSame('tok-1', $client->appToken()['access_token']);

        $http->assertSentCount(1);
    }

    public function test_it_calls_a_gateway_endpoint_with_the_bearer_token(): void
    {
        $http = new Factory;
        $http->fake([
            'https://hub.test/api/v1/apps/token' => $http->response([
                'data' => ['access_token' => 'tok-1', 'expires_in' => 3600, 'scope' => 'si-asn:read'],
            ]),
            'https://hub.test/api/v1/si-asn/pegawai/*' => $http->response([
                'data' => ['nama' => 'Budi'],
                'meta' => ['nip' => '1987654321'],
            ]),
        ]);

        $result = $this->client($http)->siAsnPegawai('1987654321');

        $this->assertSame('Budi', $result['data']['nama']);

        $http->assertSent(function ($request): bool {
            return $request->url() === 'https://hub.test/api/v1/si-asn/pegawai/1987654321'
                && $request->hasHeader('Authorization', 'Bearer tok-1');
        });
    }

    public function test_it_throws_on_authentication_failure(): void
    {
        $http = new Factory;
        $http->fake([
            'https://hub.test/api/v1/apps/token' => $http->response(['detail' => 'Kredensial salah'], 401),
        ]);

        $this->expectException(HubAuthenticationException::class);

        $this->client($http)->appToken();
    }

    public function test_it_throws_on_gateway_failure(): void
    {
        $http = new Factory;
        $http->fake([
            'https://hub.test/api/v1/apps/token' => $http->response([
                'data' => ['access_token' => 'tok', 'expires_in' => 3600],
            ]),
            'https://hub.test/api/v1/si-asn/pegawai/*' => $http->response(['detail' => 'tidak ada'], 404),
        ]);

        $this->expectException(HubRequestException::class);

        $this->client($http)->siAsnPegawai('1');
    }

    public function test_it_builds_the_authorization_url(): void
    {
        $url = $this->client(new Factory)->authorizationUrl('state-1', 'challenge-1', 'si-asn:read');

        $this->assertStringStartsWith('https://hub.test/api/v1/oauth/authorize?', $url);
        $this->assertStringContainsString('client_id=hub_test', $url);
        $this->assertStringContainsString('code_challenge=challenge-1', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fapp.test%2Fauth%2Fhub%2Fcallback', $url);
    }

    public function test_it_exchanges_the_authorization_code(): void
    {
        $http = new Factory;
        $http->fake([
            'https://hub.test/api/v1/oauth/token' => $http->response([
                'data' => [
                    'access_token' => 'user-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 900,
                    'scope' => 'si-asn:read',
                ],
            ]),
        ]);

        $token = $this->client($http)->exchangeCode('code-1', 'verifier-1');

        $this->assertSame('user-token', $token['access_token']);

        $http->assertSent(function ($request): bool {
            return $request->url() === 'https://hub.test/api/v1/oauth/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['code_verifier'] === 'verifier-1';
        });
    }
}

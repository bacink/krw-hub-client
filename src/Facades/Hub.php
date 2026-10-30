<?php

declare(strict_types=1);

namespace Krw\HubClient\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array appToken(bool $force = false)
 * @method static array request(string $method, string $path, array $options = [])
 * @method static array me()
 * @method static array siAsnPegawai(string $nip)
 * @method static array simAsnPegawai(string $id)
 * @method static array tteSign(string $pdfPath, string $fileName, string $nip, string $passphrase, string $nomorDokumen, string $jenisDokumen)
 * @method static string authorizationUrl(string $state, string $codeChallenge, string $scope, ?string $redirectUri = null)
 * @method static array exchangeCode(string $code, string $codeVerifier, ?string $redirectUri = null)
 * @method static void forgetToken()
 *
 * @see \Krw\HubClient\HubClient
 */
class Hub extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Krw\HubClient\HubClient::class;
    }
}

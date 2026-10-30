<?php

declare(strict_types=1);

namespace Krw\HubClient;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Krw\HubClient\Exceptions\HubAuthenticationException;
use Krw\HubClient\Exceptions\HubException;
use Krw\HubClient\Exceptions\HubRequestException;

class HubClient
{
    /** @var array{access_token: string, token_type: string, expires_in: int, scope: string, expires_at: int}|null */
    private ?array $cachedToken = null;

    /**
     * @param  array{base_url?: string, client_id?: string, client_secret?: string, redirect_uri?: string, timeout?: int}  $config
     */
    public function __construct(
        private readonly Factory $http,
        private readonly array $config,
    ) {
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, scope: string, expires_at: int}
     */
    public function appToken(bool $force = false): array
    {
        if (! $force && $this->cachedToken !== null && $this->cachedToken['expires_at'] > time() + 30) {
            return $this->cachedToken;
        }

        $response = $this->http
            ->asJson()
            ->timeout($this->timeout(30))
            ->post($this->url('/api/v1/apps/token'), [
                'client_id' => $this->config['client_id'] ?? null,
                'client_secret' => $this->config['client_secret'] ?? null,
            ]);

        if ($response->failed()) {
            throw new HubAuthenticationException(
                'Gagal memperoleh token aplikasi: '.$this->detail($response),
                $response->status(),
            );
        }

        $data = (array) $response->json('data');

        $this->cachedToken = [
            'access_token' => (string) ($data['access_token'] ?? ''),
            'token_type' => (string) ($data['token_type'] ?? 'Bearer'),
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
            'scope' => (string) ($data['scope'] ?? ''),
            'expires_at' => time() + (int) ($data['expires_in'] ?? 3600),
        ];

        return $this->cachedToken;
    }

    /**
     * @param  array{data?: array<string, mixed>, multipart?: array<string, array{0: string, 1?: string|null}>}  $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $token = $this->appToken();

        $request = $this->http
            ->withToken($token['access_token'])
            ->acceptJson()
            ->timeout($this->timeout(30));

        if (isset($options['multipart'])) {
            foreach ($options['multipart'] as $name => $value) {
                $request = $request->attach($name, $value[0], $value[1] ?? null);
            }

            $response = $request->{strtolower($method)}($this->url($path));
        } else {
            $response = $request->{strtolower($method)}($this->url($path), $options['data'] ?? []);
        }

        if ($response->failed()) {
            throw new HubRequestException($this->detail($response), $response->status(), (array) $response->json());
        }

        return (array) $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function me(): array
    {
        return (array) ($this->request('GET', '/api/v1/apps/me')['data'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function siAsnPegawai(string $nip): array
    {
        return $this->request('GET', '/api/v1/si-asn/pegawai/'.rawurlencode($nip));
    }

    /**
     * @return array<string, mixed>
     */
    public function simAsnPegawai(string $id): array
    {
        return $this->request('GET', '/api/v1/sim-asn/pegawai/'.rawurlencode($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function tteSign(
        string $pdfPath,
        string $fileName,
        string $nip,
        string $passphrase,
        string $nomorDokumen,
        string $jenisDokumen,
    ): array {
        $token = $this->appToken();

        $contents = file_get_contents($pdfPath);

        if ($contents === false) {
            throw new HubException('Berkas PDF tidak dapat dibaca: '.$pdfPath);
        }

        $response = $this->http
            ->withToken($token['access_token'])
            ->acceptJson()
            ->timeout($this->timeout(120))
            ->attach('document', $contents, $fileName)
            ->post($this->url('/api/v1/tte/sign'), [
                'nip' => $nip,
                'passphrase' => $passphrase,
                'nomor_dokumen' => $nomorDokumen,
                'jenis_dokumen' => $jenisDokumen,
            ]);

        if ($response->failed()) {
            throw new HubRequestException($this->detail($response), $response->status(), (array) $response->json());
        }

        return (array) ($response->json('data') ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function storageList(?string $folderId = null, string $area = 'own'): array
    {
        $query = http_build_query(array_filter([
            'folder' => $folderId,
            'area' => $area === 'public' ? 'public' : null,
        ], static fn ($value): bool => $value !== null));

        $path = '/api/v1/storage'.($query !== '' ? '?'.$query : '');

        return (array) ($this->request('GET', $path)['data'] ?? []);
    }

    /**
     * @return array{usedBytes: int, quotaBytes: int|null, publicBytes: int}
     */
    public function storageUsage(): array
    {
        return (array) ($this->request('GET', '/api/v1/storage/usage')['data'] ?? []);
    }

    /**
     * @return array{data: array<string, mixed>, url: string}
     */
    public function storageFile(string $id): array
    {
        return $this->request('GET', '/api/v1/storage/files/'.rawurlencode($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function storageUpload(string $filePath, ?string $name = null, ?string $folderId = null): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new HubException('Berkas tidak dapat dibaca: '.$filePath);
        }

        $token = $this->appToken();
        $fileName = $name ?? basename($filePath);

        $response = $this->http
            ->withToken($token['access_token'])
            ->acceptJson()
            ->timeout($this->timeout(120))
            ->attach('file', $contents, $fileName)
            ->post($this->url('/api/v1/storage/files'), array_filter([
                'folder_id' => $folderId,
                'name' => $name,
            ], static fn ($value): bool => $value !== null));

        if ($response->failed()) {
            throw new HubRequestException($this->detail($response), $response->status(), (array) $response->json());
        }

        return (array) ($response->json('data') ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function storageRenameFile(string $id, string $name): array
    {
        return (array) ($this->request('PATCH', '/api/v1/storage/files/'.rawurlencode($id), [
            'data' => ['name' => $name],
        ])['data'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function storageMoveFile(string $id, ?string $folderId): array
    {
        return (array) ($this->request('PATCH', '/api/v1/storage/files/'.rawurlencode($id), [
            'data' => ['folder_id' => $folderId],
        ])['data'] ?? []);
    }

    public function storageDeleteFile(string $id): void
    {
        $this->request('DELETE', '/api/v1/storage/files/'.rawurlencode($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function storageCreateFolder(string $name, ?string $parentId = null): array
    {
        return (array) ($this->request('POST', '/api/v1/storage/folders', [
            'data' => array_filter([
                'name' => $name,
                'parent_id' => $parentId,
            ], static fn ($value): bool => $value !== null),
        ])['data'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function storageRenameFolder(string $id, string $name): array
    {
        return (array) ($this->request('PATCH', '/api/v1/storage/folders/'.rawurlencode($id), [
            'data' => ['name' => $name],
        ])['data'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function storageMoveFolder(string $id, ?string $parentId): array
    {
        return (array) ($this->request('PATCH', '/api/v1/storage/folders/'.rawurlencode($id), [
            'data' => ['parent_id' => $parentId],
        ])['data'] ?? []);
    }

    public function storageDeleteFolder(string $id): void
    {
        $this->request('DELETE', '/api/v1/storage/folders/'.rawurlencode($id));
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $scope, ?string $redirectUri = null): string
    {
        return $this->url('/api/v1/oauth/authorize?'.http_build_query([
            'client_id' => $this->config['client_id'] ?? null,
            'redirect_uri' => $redirectUri ?? ($this->config['redirect_uri'] ?? null),
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code, string $codeVerifier, ?string $redirectUri = null): array
    {
        $response = $this->http
            ->asJson()
            ->timeout($this->timeout(30))
            ->post($this->url('/api/v1/oauth/token'), [
                'grant_type' => 'authorization_code',
                'client_id' => $this->config['client_id'] ?? null,
                'client_secret' => $this->config['client_secret'] ?? null,
                'code' => $code,
                'redirect_uri' => $redirectUri ?? ($this->config['redirect_uri'] ?? null),
                'code_verifier' => $codeVerifier,
            ]);

        if ($response->failed()) {
            throw new HubAuthenticationException(
                'Gagal menukar authorization code: '.$this->detail($response),
                $response->status(),
            );
        }

        return (array) ($response->json('data') ?? []);
    }

    public function forgetToken(): void
    {
        $this->cachedToken = null;
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/').$path;
    }

    private function timeout(int $default): int
    {
        return (int) ($this->config['timeout'] ?? $default);
    }

    private function detail(Response $response): string
    {
        $detail = $response->json('detail');

        return is_string($detail) && $detail !== '' ? $detail : $response->body();
    }
}

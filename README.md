# KRW Hub Client

Client library untuk **BKPSDM HUB**. Memudahkan aplikasi konsumen memakai layanan hub:
token aplikasi, SI-ASN, TTE, SIM-ASN, storage (file manager), dan delegasi login user (SIM-ASN OAuth + PKCE).

## Instalasi HUB Client

### Dari GitHub (VCS)

```bash
composer require bacink/krw-hub-client
```

Tambahkan repository bila belum terdaftar:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/bacink/krw-hub-client" }
    ]
}
```

### Dari folder lokal (path repository)

```json
{
    "repositories": [
        { "type": "path", "url": "../krw-hub-client", "options": { "symlink": true } }
    ]
}
```

```bash
composer require bacink/krw-hub-client:*
```

Laravel akan mendaftarkan service provider & facade secara otomatis (package discovery).

## Konfigurasi

`.env`:

```env
KRW_HUB_BASE_URL=https://hub.bkpsdm.karawangkab.go.id
KRW_HUB_CLIENT_ID=hub_xxxxxxxx
KRW_HUB_CLIENT_SECRET=xxxxxxxx
KRW_HUB_REDIRECT_URI=https://app.example.com/auth/hub/callback
KRW_HUB_TIMEOUT=30
```

Opsional publish config:

```bash
php artisan vendor:publish --tag=krw-hub-config
```

`client_id`/`client_secret` diperoleh dari superadmin hub di `/portal/applications`.
`redirect_uri` hanya diperlukan untuk delegasi login user dan harus **terdaftar** di hub.

## Pemakaian

### Facade

```php
use Krw\HubClient\Facades\Hub;

$token = Hub::appToken();
$pegawai = Hub::siAsnPegawai('1987654321');
```

### Dependency injection

```php
use Krw\HubClient\HubClient;

final class LayananPegawai
{
    public function __construct(private readonly HubClient $hub)
    {
    }
}
```

### Token aplikasi

```php
$token = $hub->appToken();             // di-cache sampai mendekati exp
$token = $hub->appToken(force: true);  // paksa token baru
$hub->forgetToken();                   // hapus cache
```

### SI-ASN

```php
$data = $hub->siAsnPegawai('1987654321');
// ['data' => [...], 'meta' => ['nip' => '1987654321']]
```

### TTE

```php
$result = $hub->tteSign(
    pdfPath: storage_path('app/draft.pdf'),
    fileName: 'draft.pdf',
    nip: '1987654321',
    passphrase: $passphrase,
    nomorDokumen: '001/2026',
    jenisDokumen: 'Berita Acara',
);
// ['blob_id' => ..., 'url' => ..., 'expires_at' => ..., 'mime' => 'application/pdf', 'size' => ...]
```

PDF hasil tanda tangan **tidak boleh** diregenerasi/diubah. Unduh dari `url` sebelum kedaluwarsa.

### SIM-ASN

```php
$profil = $hub->simAsnPegawai('1987654321');
```

### Storage

```php
// Daftar isi folder (area aplikasi sendiri)
$listing = $hub->storageList();

// Area publik (read-only untuk aplikasi)
$public = $hub->storageList(area: 'public');

// Pemakaian kuota
$usage = $hub->storageUsage(); // ['usedBytes' => ..., 'quotaBytes' => ..., 'publicBytes' => ...]

// Buat folder
$folder = $hub->storageCreateFolder('dokumen');

// Unggah berkas (multipart)
$file = $hub->storageUpload(storage_path('app/surat.pdf'), name: 'surat.pdf', folderId: $folder['id']);

// Metadata + signed URL
$meta = $hub->storageFile($file['id']); // ['data' => [...], 'url' => '...']

// Ubah nama / pindah / hapus berkas
$hub->storageRenameFile($file['id'], 'surat-baru.pdf');
$hub->storageMoveFile($file['id'], null);
$hub->storageDeleteFile($file['id']);

// Folder
$hub->storageRenameFolder($folder['id'], 'arsip');
$hub->storageMoveFolder($folder['id'], null);
$hub->storageDeleteFolder($folder['id']);
```

Aplikasi hanya dapat melihat/mengubah berkas di area miliknya. Area `public` hanya dapat **dibaca** (penulisan oleh superadmin).

### Request generik

```php
$response = $hub->request('GET', '/api/v1/apps/me');
$response = $hub->request('POST', '/api/v1/sesuatu', ['data' => ['foo' => 'bar']]);
```

## Delegasi login user (SIM-ASN OAuth + PKCE)

```php
use Krw\HubClient\Facades\Hub;
use Krw\HubClient\Support\Pkce;

// 1) Arahkan user ke hub
public function redirect()
{
    $pkce = Pkce::generate();
    $state = Pkce::randomString(40);

    session([
        'hub.state' => $state,
        'hub.verifier' => $pkce['verifier'],
    ]);

    return redirect(Hub::authorizationUrl($state, $pkce['challenge'], 'si-asn:read'));
}

// 2) Callback: verifikasi state, lalu tukar code
public function callback(Request $request)
{
    abort_unless($request->query('state') === session('hub.state'), 400);
    abort_if($request->has('error'), 400, $request->query('error_description'));

    $token = Hub::exchangeCode($request->query('code'), session('hub.verifier'));

    session(['hub.user_token' => $token['access_token']]);

    return redirect()->intended('/dashboard');
}
```

`redirect_uri` harus **sama persis** di tiga tempat: nilai terdaftar di hub,
parameter saat `/oauth/authorize`, dan saat `/oauth/token`.

## Scope

| Scope | Layanan |
|-------|---------|
| `tte:sign` | Penandatanganan dokumen TTE |
| `si-asn:read` | Data pegawai SI-ASN |
| `sim-asn:read` | Profil pegawai SIM-ASN |
| `storage:read` | Daftar/unduh berkas storage |
| `storage:write` | Unggah/ubah/hapus berkas storage |

## Error

```php
use Krw\HubClient\Exceptions\HubAuthenticationException;
use Krw\HubClient\Exceptions\HubRequestException;

try {
    $hub->siAsnPegawai('1987654321');
} catch (HubAuthenticationException $e) {
    // 401: kredensial/token aplikasi tidak valid
} catch (HubRequestException $e) {
    // 403/404/422/429/502: $e->statusCode(), $e->payload()
}
```

## Lisensi

GPLv3.

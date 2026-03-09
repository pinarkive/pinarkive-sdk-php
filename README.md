# Pinarkive PHP SDK

Minimal PHP client for the **PinArkive API v3**. Upload files, pin by CID, manage tokens, and check status. See [pinarkive.com/docs.php](https://pinarkive.com/docs.php).

**Version:** 3.1.0

## Installation

```bash
composer require pinarkive/pinarkive-sdk-php
```

## Quick Start

```php
<?php

use Pinarkive\PinarkiveClient;
use Pinarkive\PinarkiveException;

// Auth: token, apiKey, baseUrl (default: https://api.pinarkive.com/api/v3)
$client = new PinarkiveClient(null, 'your-api-key-here');

// Upload a file
$response = $client->uploadFile('document.pdf');
$data = json_decode($response->getBody(), true);
echo $data['cid'];

// Or login first
$login = $client->login('user@example.com', 'password');
$data = json_decode($login->getBody(), true);
$client = new PinarkiveClient($data['token'], null);

// List uploads
$response = $client->listUploads(1, 20);
$data = json_decode($response->getBody(), true);
print_r($data['uploads']);
```

## Authentication

- **Constructor:** `new PinarkiveClient($token = null, $apiKey = null, $baseUrl = '...', $sendRequestSourceWeb = false)`
- **API Key** is sent as `X-API-Key` header; **token** as `Authorization: Bearer <token>`.
- **$sendRequestSourceWeb:** when `true`, sends `X-Request-Source: web` on every Bearer-authenticated request (not when using API Key). Use from web apps so the backend classifies requests as **WEB** in logs instead of **JWT**.

## API Methods (minimal set)

| Method | Description |
|--------|-------------|
| `health()` | GET /health |
| `getPlans()` | GET /plans/ |
| `getPeers()` | GET /peers/ |
| `login($email, $password)` | POST /auth/login |
| `verify2FALogin($temporaryToken, $code)` | POST /auth/2fa/verify-login |
| `uploadFile($filePath, $clusterId = null, $timelock = null)` | POST /files/ |
| `uploadDirectory($dirPath, $clusterId = null, $timelock = null)` | POST /files/directory |
| `uploadDirectoryDAG($files, $dirName = null, $clusterId = null, $timelock = null)` | POST /files/directory-dag |
| `pinCid($cid, $originalName = null, $customName = null, $clusterId = null, $timelock = null)` | POST /files/pin/:cid |
| `removeFile($cid)` | DELETE /files/remove/:cid |
| `getMe()` | GET /users/me |
| `listUploads($page = 1, $limit = 20)` | GET /users/me/uploads |
| `generateToken($name, $label = null, $expiresInDays = null, $scopes = null, $totpCode = null)` | POST /tokens/generate |
| `listTokens()` | GET /tokens/list |
| `revokeToken($name, $totpCode = null)` | DELETE /tokens/revoke/:name |
| `getStatus($cid, $clusterId = null)` | GET /status/:cid |
| `getAllocations($cid, $clusterId = null)` | GET /allocations/:cid |

Optional `$clusterId` and `$timelock` (ISO 8601, premium) follow the API docs.

## Error handling

On HTTP 4xx/5xx the client throws **`PinarkiveException`** with:

- `getStatusCode()` — HTTP status
- `getApiError()` — API field `error`
- `getApiMessage()` — API field `message`
- `getApiCode()` — API field `code` (e.g. `email_not_verified`, `missing_scope`)
- `getRequired()` — for 403 `missing_scope`: the required scope
- `getRetryAfter()` — for 429: seconds until retry (from body or `Retry-After` header)
- `getBody()` — full response array

```php
try {
    $client->uploadFile('file.pdf');
} catch (PinarkiveException $e) {
    echo $e->getStatusCode() . ' ' . $e->getApiMessage() . ' ' . $e->getApiCode();
}
```

## Changelog

### 3.1.0

- **Request source:** Constructor 4th param `$sendRequestSourceWeb = true` sends `X-Request-Source: web` on Bearer requests.
- **Scopes & 2FA:** `generateToken(..., $scopes, $totpCode)`; `revokeToken($name, $totpCode)`. `verify2FALogin($temporaryToken, $code)` for login with 2FA.
- **Errors:** `getRequired()` (403 missing_scope), `getRetryAfter()` (429).

### 3.0.0

- **API v3:** Base URL is now `https://api.pinarkive.com/api/v3` (was `/api/v2`). v1/v2 are deprecated (410).
- **Errors:** On 4xx/5xx the client throws `PinarkiveException` with `getStatusCode()`, `getApiError()`, `getApiMessage()`, `getApiCode()`, `getBody()` (no raw Guzzle response on failure).
- **Minimal surface:** Only endpoints documented at [pinarkive.com/docs.php](https://pinarkive.com/docs.php): health, plans, peers, login, files (upload, directory, directory-dag, pin, remove), users/me, uploads, tokens (generate with `name` / `label` / `expiresInDays`), status, allocations. Optional `$clusterId` and `$timelock` (ISO 8601) on upload/pin.
- **Removed:** `renameFile`; token options `permissions`, `ipAllowlist`. Use API `label` and `expiresInDays` only.
- **Constructor:** `PinarkiveClient($token = null, $apiKey = null, $baseUrl = '...')` — third argument is base URL (v2 had only apiKey and baseUrl).
- **Pin:** `pinCid` now accepts `$originalName`, `$customName` (replacing the old single `$filename`).

### Upgrading from 2.x

1. Change base URL to `/api/v3` or use the new default.
2. Add the third constructor argument if you use a custom base URL: `new PinarkiveClient(null, $apiKey, 'https://api.pinarkive.com/api/v3')`.
3. Catch `PinarkiveException` and use `getStatusCode()`, `getApiMessage()`, `getApiCode()` instead of Guzzle’s `RequestException`.
4. Use `pinCid($cid, $originalName, $customName, $clusterId, $timelock)` instead of `pinCid($cid, $filename)`.
5. Use `generateToken($name, $label, $expiresInDays)`; drop `permissions` and `ipAllowlist` from options.
6. Require `pinarkive/pinarkive-sdk-php: ^3.0` for v3.

## Links

- [API docs](https://pinarkive.com/docs.php)
- [Repository](https://github.com/pinarkive/pinarkive-sdk-php)

<?php

namespace Pinarkive;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/** SDK version (API v3). */
const VERSION = '3.0.0';

/**
 * Thrown when the API returns HTTP 4xx or 5xx.
 * API v3 codes: 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found,
 * 409 Conflict, 413 Payload Too Large, 429 Too Many Requests, 500 Internal Server Error, 503 Service Unavailable.
 */
class PinarkiveException extends \Exception
{
    /** @var int */
    private $statusCode;

    /** @var array */
    private $body;

    public function __construct(int $statusCode, string $message, array $body = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getApiError(): string
    {
        return $this->body['error'] ?? '';
    }

    public function getApiMessage(): string
    {
        return $this->body['message'] ?? $this->getMessage();
    }

    public function getApiCode(): string
    {
        return $this->body['code'] ?? '';
    }
}

/**
 * PinarkiveClient – PHP SDK for PinArkive API v3.
 * Minimal client per https://pinarkive.com/docs.php (upload, pin, remove, users/me, uploads, tokens, status, allocations).
 * Auth: Bearer token or X-API-Key. On 4xx/5xx throws PinarkiveException with status code and API body.
 */
class PinarkiveClient
{
    /** @var Client */
    private $client;

    /** @var string|null Bearer token (JWT or API key) */
    private $token;

    /** @var string|null When set, sent as X-API-Key instead of Bearer */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /**
     * @param string|null $token Bearer token (JWT)
     * @param string|null $apiKey API key (sent as X-API-Key header)
     * @param string $baseUrl Base URL (default https://api.pinarkive.com/api/v3)
     */
    public function __construct($token = null, $apiKey = null, $baseUrl = 'https://api.pinarkive.com/api/v3')
    {
        $this->token = $token;
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new Client();
    }

    private function headers(bool $auth = true): array
    {
        $h = [];
        if ($auth) {
            if ($this->apiKey !== null && $this->apiKey !== '') {
                $h['X-API-Key'] = $this->apiKey;
            } elseif ($this->token !== null && $this->token !== '') {
                $h['Authorization'] = 'Bearer ' . $this->token;
            }
        }
        return $h;
    }

    /**
     * @param array $options Guzzle options (json, multipart, query, etc.); headers are merged with auth.
     * @throws PinarkiveException on HTTP 4xx/5xx with status code and API body (error, message, code).
     */
    private function request(string $method, string $path, array $options = [], bool $auth = true): \Psr\Http\Message\ResponseInterface
    {
        $opts = array_merge($options, [
            'headers' => array_merge($this->headers($auth), $options['headers'] ?? []),
            'http_errors' => false,
        ]);
        try {
            $response = $this->client->request($method, $this->baseUrl . $path, $opts);
        } catch (\Throwable $e) {
            if ($e instanceof RequestException && $e->hasResponse()) {
                $r = $e->getResponse();
                $code = $r->getStatusCode();
                $body = [];
                $ct = $r->getHeaderLine('Content-Type');
                if (strpos($ct, 'application/json') !== false) {
                    $decoded = json_decode((string) $r->getBody(), true);
                    if (is_array($decoded)) {
                        $body = $decoded;
                    }
                }
                $msg = $body['message'] ?? $body['error'] ?? $r->getReasonPhrase();
                throw new PinarkiveException($code, $msg, $body);
            }
            throw $e;
        }
        if ($response->getStatusCode() >= 400) {
            $body = [];
            $ct = $response->getHeaderLine('Content-Type');
            if (strpos($ct, 'application/json') !== false) {
                $decoded = json_decode((string) $response->getBody(), true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
            $msg = $body['message'] ?? $body['error'] ?? $response->getReasonPhrase();
            throw new PinarkiveException($response->getStatusCode(), $msg, $body);
        }
        return $response;
    }

    // --- Public (no auth) ---

    /** GET /health */
    public function health(): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('GET', '/health', [], false);
    }

    /** GET /plans/ */
    public function getPlans(): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('GET', '/plans/', [], false);
    }

    /** GET /peers/ */
    public function getPeers(): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('GET', '/peers/', [], false);
    }

    /** POST /auth/login – returns JSON with token, user? */
    public function login(string $email, string $password): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('POST', '/auth/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => $email, 'password' => $password],
        ], false);
    }

    // --- Files ---

    /** POST /files/ – multipart file, optional cl, timelock (ISO 8601, premium) */
    public function uploadFile(string $filePath, ?string $clusterId = null, ?string $timelock = null): \Psr\Http\Message\ResponseInterface
    {
        $multipart = [['name' => 'file', 'contents' => fopen($filePath, 'r')]];
        if ($clusterId !== null) {
            $multipart[] = ['name' => 'cl', 'contents' => $clusterId];
        }
        if ($timelock !== null) {
            $multipart[] = ['name' => 'timelock', 'contents' => $timelock];
        }
        return $this->request('POST', '/files/', ['multipart' => $multipart]);
    }

    /** POST /files/directory – body dirPath, optional cl, timelock */
    public function uploadDirectory(string $dirPath, ?string $clusterId = null, ?string $timelock = null): \Psr\Http\Message\ResponseInterface
    {
        $body = ['dirPath' => $dirPath];
        if ($clusterId !== null) {
            $body['cl'] = $clusterId;
        }
        if ($timelock !== null) {
            $body['timelock'] = $timelock;
        }
        return $this->request('POST', '/files/directory', ['json' => $body]);
    }

    /**
     * POST /files/directory-dag – multipart files[i][path], files[i][content]; optional cl, timelock
     * @param array $files array of [ 'path' => string, 'content' => string|resource ]
     */
    public function uploadDirectoryDAG(array $files, ?string $dirName = null, ?string $clusterId = null, ?string $timelock = null): \Psr\Http\Message\ResponseInterface
    {
        $multipart = [];
        if ($dirName !== null) {
            $multipart[] = ['name' => 'dirName', 'contents' => $dirName];
        }
        if ($clusterId !== null) {
            $multipart[] = ['name' => 'cl', 'contents' => $clusterId];
        }
        if ($timelock !== null) {
            $multipart[] = ['name' => 'timelock', 'contents' => $timelock];
        }
        foreach ($files as $i => $file) {
            $path = is_array($file) ? $file['path'] : $file;
            $content = is_array($file) ? $file['content'] : file_get_contents($file);
            $multipart[] = ['name' => "files[{$i}][path]", 'contents' => $path];
            $multipart[] = ['name' => "files[{$i}][content]", 'contents' => $content];
        }
        return $this->request('POST', '/files/directory-dag', ['multipart' => $multipart]);
    }

    /** POST /files/pin/:cid – optional originalName, customName, cl, timelock */
    public function pinCid(string $cid, ?string $originalName = null, ?string $customName = null, ?string $clusterId = null, ?string $timelock = null): \Psr\Http\Message\ResponseInterface
    {
        $body = [];
        if ($originalName !== null) {
            $body['originalName'] = $originalName;
        }
        if ($customName !== null) {
            $body['customName'] = $customName;
        }
        if ($clusterId !== null) {
            $body['cl'] = $clusterId;
        }
        if ($timelock !== null) {
            $body['timelock'] = $timelock;
        }
        return $this->request('POST', "/files/pin/{$cid}", ['json' => $body]);
    }

    /** DELETE /files/remove/:cid */
    public function removeFile(string $cid): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('DELETE', "/files/remove/{$cid}");
    }

    // --- Users ---

    /** GET /users/me */
    public function getMe(): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('GET', '/users/me');
    }

    /** GET /users/me/uploads?page=&limit= */
    public function listUploads(int $page = 1, int $limit = 20): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('GET', '/users/me/uploads', ['query' => ['page' => $page, 'limit' => $limit]]);
    }

    // --- Tokens (name required; label default cli-access; expiresInDays optional) ---

    /** POST /tokens/generate – name required, optional label (default cli-access), expiresInDays */
    public function generateToken(string $name, ?string $label = null, ?int $expiresInDays = null): \Psr\Http\Message\ResponseInterface
    {
        $body = ['name' => $name];
        if ($label !== null) {
            $body['label'] = $label;
        }
        if ($expiresInDays !== null) {
            $body['expiresInDays'] = $expiresInDays;
        }
        return $this->request('POST', '/tokens/generate', ['json' => $body]);
    }

    /** GET /tokens/list */
    public function listTokens(): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('GET', '/tokens/list');
    }

    /** DELETE /tokens/revoke/:name */
    public function revokeToken(string $name): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('DELETE', '/tokens/revoke/' . rawurlencode($name));
    }

    // --- Status ---

    /** GET /status/:cid?cl= */
    public function getStatus(string $cid, ?string $clusterId = null): \Psr\Http\Message\ResponseInterface
    {
        $query = $clusterId !== null ? ['cl' => $clusterId] : [];
        return $this->request('GET', "/status/{$cid}", ['query' => $query]);
    }

    /** GET /allocations/:cid?cl= */
    public function getAllocations(string $cid, ?string $clusterId = null): \Psr\Http\Message\ResponseInterface
    {
        $query = $clusterId !== null ? ['cl' => $clusterId] : [];
        return $this->request('GET', "/allocations/{$cid}", ['query' => $query]);
    }
}

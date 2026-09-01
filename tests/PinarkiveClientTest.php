<?php

declare(strict_types=1);

namespace Pinarkive\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Pinarkive\PinarkiveClient;
use Pinarkive\PinarkiveException;

final class PinarkiveClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    private function clientWithMock(MockHandler $mock, ?string $token = 'tok', ?string $apiKey = null): PinarkiveClient
    {
        $this->history = [];
        $history = Middleware::history($this->history);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $http = new Client(['handler' => $stack, 'http_errors' => false]);
        return new PinarkiveClient($token, $apiKey, 'https://api.example.com/api/v3', false, 30.0, $http);
    }

    public function testClientInitDefaultsBaseUrl(): void
    {
        $client = new PinarkiveClient('t', null, 'https://api.pinarkive.com/api/v3/');
        $this->assertInstanceOf(PinarkiveClient::class, $client);
    }

    public function testBearerAuthHeader(): void
    {
        $mock = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')]);
        $client = $this->clientWithMock($mock, 'jwt-token', null);
        $client->getMe();

        $this->assertCount(1, $this->history);
        /** @var Request $req */
        $req = $this->history[0]['request'];
        $this->assertSame('Bearer jwt-token', $req->getHeaderLine('Authorization'));
        $this->assertSame('', $req->getHeaderLine('X-API-Key'));
        $this->assertSame('https://api.example.com/api/v3/users/me', (string) $req->getUri());
    }

    public function testApiKeyAuthHeader(): void
    {
        $mock = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')]);
        $client = $this->clientWithMock($mock, null, 'pk_test');
        $client->getMe();

        /** @var Request $req */
        $req = $this->history[0]['request'];
        $this->assertSame('pk_test', $req->getHeaderLine('X-API-Key'));
        $this->assertSame('', $req->getHeaderLine('Authorization'));
    }

    public function testRequestSuccessPath(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'),
        ]);
        $client = $this->clientWithMock($mock, null, null);
        $res = $client->health();
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('GET', $this->history[0]['request']->getMethod());
        $this->assertStringEndsWith('/health', (string) $this->history[0]['request']->getUri());
    }

    public function testErrorsThrowPinarkiveException(): void
    {
        $body = json_encode([
            'error' => 'Unauthorized',
            'message' => 'Invalid token',
            'code' => 'unauthorized',
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([
            new Response(401, ['Content-Type' => 'application/json'], $body),
        ]);
        $client = $this->clientWithMock($mock, 'bad');

        try {
            $client->getMe();
            $this->fail('Expected PinarkiveException');
        } catch (PinarkiveException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame('Invalid token', $e->getApiMessage());
            $this->assertSame('unauthorized', $e->getApiCode());
            $this->assertSame('Unauthorized', $e->getApiError());
        }
    }

    public function testTimeoutSurfacesConnectException(): void
    {
        $mock = new MockHandler([
            new ConnectException(
                'cURL error 28: Connection timed out',
                new Request('GET', 'https://api.example.com/api/v3/health')
            ),
        ]);
        $stack = HandlerStack::create($mock);
        $http = new Client(['handler' => $stack, 'timeout' => 0.001, 'connect_timeout' => 0.001]);
        $client = new PinarkiveClient(null, null, 'https://api.example.com/api/v3', false, 0.001, $http);

        $this->expectException(ConnectException::class);
        $client->health();
    }

    public function testUploadFileMultipartParse(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pin');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, "hello-pinarkive");

        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], '{"cid":"bafytest","status":"queued"}'),
        ]);
        $client = $this->clientWithMock($mock, 'tok');
        $res = $client->uploadFile($tmp, 'cl0');
        $this->assertSame(201, $res->getStatusCode());

        /** @var Request $req */
        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/files/', (string) $req->getUri());
        $ct = $req->getHeaderLine('Content-Type');
        $this->assertStringContainsString('multipart/form-data', $ct);
        $body = (string) $req->getBody();
        $this->assertStringContainsString('name="file"', $body);
        $this->assertStringContainsString('name="cl"', $body);
        $this->assertStringContainsString('cl0', $body);
        $this->assertStringContainsString('hello-pinarkive', $body);

        @unlink($tmp);
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AkauntingClient
{
    /**
     * @param  string  $authHeader  The full "Basic <base64(email:password)>" header forwarded from the MCP client.
     * @param  int  $companyId  The Akaunting company to operate on.
     */
    public function __construct(
        private readonly string $authHeader,
        private readonly int $companyId,
    ) {}

    public function get(string $path, array $params = []): mixed
    {
        return $this->request('get', $path, ['query' => $params]);
    }

    public function post(string $path, array $data): mixed
    {
        return $this->request('post', $path, ['json' => $data]);
    }

    public function put(string $path, array $data): mixed
    {
        return $this->request('put', $path, ['json' => $data]);
    }

    public function delete(string $path): void
    {
        $this->request('delete', $path);
    }

    private function request(string $method, string $path, array $options = []): mixed
    {
        // Short id so the request and response log lines can be correlated.
        $requestId = (string) Str::uuid();

        // Akaunting requires company_id as a query parameter on every request,
        // regardless of HTTP method.
        $query = array_merge(['company_id' => $this->companyId], $options['query'] ?? []);
        $url = config('akaunting.base_url').'/api/'.ltrim($path, '/').'?'.http_build_query($query);

        $http = Http::withHeaders(['Authorization' => $this->authHeader])->acceptJson();

        // For write methods Akaunting also reads company_id from the payload.
        $payload = $options['json'] ?? [];
        if (in_array($method, ['post', 'put'], true)) {
            $payload = array_merge(['company_id' => $this->companyId], $payload);
        }

        $this->logRequest($requestId, $method, $url, $payload);

        $startedAt = microtime(true);

        try {
            $response = match ($method) {
                'get' => $http->get($url),
                'post' => $http->post($url, $payload),
                'put' => $http->put($url, $payload),
                'delete' => $http->delete($url),
            };
        } catch (ConnectionException $e) {
            // Could not reach Akaunting at all (DNS, TLS, timeout, refused).
            Log::error('Akaunting API request failed to connect', [
                'request_id' => $requestId,
                'method' => strtoupper($method),
                'url' => $url,
                'company_id' => $this->companyId,
                'message' => $e->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            throw new RuntimeException(
                "Could not reach Akaunting at ".config('akaunting.base_url').": {$e->getMessage()}",
                previous: $e,
            );
        }

        $durationMs = $this->elapsedMs($startedAt);

        if ($response->failed()) {
            // Always log failures at error level so they land in the log
            // regardless of LOG_LEVEL. The body usually contains the exact
            // Akaunting validation message (e.g. a 422 field error).
            Log::error('Akaunting API request failed', [
                'request_id' => $requestId,
                'method' => strtoupper($method),
                'url' => $url,
                'company_id' => $this->companyId,
                'status' => $response->status(),
                'reason' => $response->reason(),
                'request_payload' => $this->redactPayload($payload),
                'response_body' => $this->truncate($response->body()),
                'duration_ms' => $durationMs,
            ]);

            throw new RuntimeException(
                "Akaunting API error {$response->status()}: {$response->body()}"
            );
        }

        $this->logResponse($requestId, $method, $url, $response->status(), $response->body(), $durationMs);

        return $response->status() === 204 ? null : $response->json();
    }

    private function logRequest(string $requestId, string $method, string $url, array $payload): void
    {
        if (! config('akaunting.debug')) {
            return;
        }

        // Logged at debug level — requires LOG_LEVEL=debug in .env to appear.
        Log::debug('Akaunting API request', [
            'request_id' => $requestId,
            'method' => strtoupper($method),
            'url' => $url,
            'company_id' => $this->companyId,
            'payload' => $this->redactPayload($payload),
        ]);
    }

    private function logResponse(string $requestId, string $method, string $url, int $status, string $body, float $durationMs): void
    {
        if (! config('akaunting.debug')) {
            return;
        }

        Log::debug('Akaunting API response', [
            'request_id' => $requestId,
            'method' => strtoupper($method),
            'url' => $url,
            'status' => $status,
            'body' => $this->truncate($body),
            'duration_ms' => $durationMs,
        ]);
    }

    private function elapsedMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 1);
    }

    /**
     * Remove anything sensitive before a payload reaches the log.
     */
    private function redactPayload(array $payload): array
    {
        foreach (['password', 'password_confirmation'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '***redacted***';
            }
        }

        return $payload;
    }

    /**
     * Keep response bodies from flooding the log while preserving the useful head.
     */
    private function truncate(string $body, int $limit = 4000): string
    {
        return strlen($body) > $limit
            ? substr($body, 0, $limit).'… ['.(strlen($body) - $limit).' more chars]'
            : $body;
    }
}

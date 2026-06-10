<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
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

        $response = match ($method) {
            'get' => $http->get($url),
            'post' => $http->post($url, $payload),
            'put' => $http->put($url, $payload),
            'delete' => $http->delete($url),
        };

        if ($response->failed()) {
            throw new RuntimeException("Akaunting API error {$response->status()}: {$response->body()}");
        }

        return $response->status() === 204 ? null : $response->json();
    }
}

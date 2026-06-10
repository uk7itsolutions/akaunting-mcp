<?php

namespace App\Http\Middleware;

use App\Services\AkauntingClient;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateAkauntingKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseUrl = config('akaunting.base_url');

        if (empty($baseUrl)) {
            Log::error('AKAUNTING_BASE_URL is not set. Configure it in .env and run: php artisan config:clear');

            return response()->json([
                'error' => 'Server misconfigured: AKAUNTING_BASE_URL is not set. Edit .env on the server and run "php artisan config:clear".',
            ], 500);
        }

        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Basic ')) {
            return response()->json([
                'error' => 'Missing or malformed Authorization header. Expected: Authorization: Basic <base64(email:password)>',
            ], 401);
        }

        // Akaunting is multi-company. Allow the client to pick a company per
        // connection, otherwise fall back to the configured default.
        $companyId = (int) ($request->header('X-Company-ID') ?: config('akaunting.company_id'));

        try {
            $check = Http::withHeaders(['Authorization' => $header])
                ->acceptJson()
                ->timeout(5)
                ->get($baseUrl.'/api/ping');
        } catch (ConnectionException $e) {
            Log::error('Could not reach Akaunting API', [
                'base_url' => $baseUrl,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => "Could not reach Akaunting at {$baseUrl}. Verify AKAUNTING_BASE_URL and that the host is reachable from this server.",
            ], 502);
        }

        if ($check->status() === 401) {
            return response()->json([
                'error' => 'Akaunting rejected the credentials. Use the email and password of a user with the "read-api" permission (admin role by default).',
            ], 401);
        }

        if ($check->failed()) {
            Log::error('Akaunting API returned unexpected status', [
                'status' => $check->status(),
                'body' => $check->body(),
            ]);

            return response()->json([
                'error' => "Akaunting API returned HTTP {$check->status()}",
            ], 502);
        }

        app()->instance(AkauntingClient::class, new AkauntingClient($header, $companyId));

        return $next($request);
    }
}

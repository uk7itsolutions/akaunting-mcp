<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when Akaunting was reached successfully but returned an error status
 * (4xx/5xx). This is explicitly NOT a connection problem.
 */
class AkauntingApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $responseBody,
        public readonly string $method,
        public readonly string $endpoint,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Akaunting API error {$statusCode} on {$method} {$endpoint}: {$responseBody}",
            $statusCode,
            $previous,
        );
    }

    /**
     * A structured, AI-readable explanation. Makes clear this is an error the
     * Akaunting application reported (the server was reached), not a network
     * failure, and gives a status-specific hint on how to proceed.
     */
    public function forAi(): string
    {
        $hint = match (true) {
            $this->statusCode === 401 => 'Akaunting rejected the credentials (401). The email/password for this connection is wrong or lacks API access. Retrying without fixing the credentials will not help.',
            $this->statusCode === 403 => 'Akaunting denied this action due to permissions (403). The API user\'s role is missing a required permission — the "read-api" gate and/or the per-feature read/create/update permission for this resource. This is a server-side configuration problem; retrying will not help.',
            $this->statusCode === 404 => 'Akaunting could not find the requested record (404). Check that the id exists for the selected company.',
            $this->statusCode === 422 => 'Akaunting rejected the submitted data as invalid (422 validation error). Read the field errors in the response body below, correct the input, then try again.',
            $this->statusCode >= 500 => 'Akaunting returned an internal server error (5xx). The problem is inside the Akaunting application, not the MCP connection.',
            default => "Akaunting rejected the request with HTTP {$this->statusCode}.",
        };

        return implode("\n", [
            "Akaunting API error: HTTP {$this->statusCode} on {$this->method} {$this->endpoint}.",
            'The MCP server reached Akaunting and received this error, so this is NOT a network/connection problem — it is an error reported by the Akaunting application itself.',
            $hint,
            'Akaunting response body: '.$this->responseBody,
        ]);
    }
}

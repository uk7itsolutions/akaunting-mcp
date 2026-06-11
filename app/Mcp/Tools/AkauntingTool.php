<?php

namespace App\Mcp\Tools;

use App\Exceptions\AkauntingApiException;
use App\Exceptions\AkauntingConnectionException;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Base class for all Akaunting MCP tools.
 *
 * Concrete tools implement execute(); this wrapper turns any failure into a
 * structured Response::error() (isError: true) instead of letting it bubble up
 * as an opaque JSON-RPC "internal error". The message tells the calling AI
 * whether the problem is an Akaunting API/permission error (the server was
 * reached) or a connection error (it was not) — and whether retrying helps.
 */
abstract class AkauntingTool extends Tool
{
    abstract protected function execute(Request $request): Response;

    public function handle(Request $request): Response
    {
        try {
            return $this->execute($request);
        } catch (AkauntingApiException $e) {
            return Response::error($e->forAi());
        } catch (AkauntingConnectionException $e) {
            return Response::error($e->forAi());
        } catch (Throwable $e) {
            // An unexpected failure inside the MCP server itself (a bug) — not
            // Akaunting and not the connection. Log it and report it plainly.
            Log::error('Unexpected error in Akaunting MCP tool', [
                'tool'      => static::class,
                'exception' => $e::class,
                'message'   => $e->getMessage(),
                'location'  => $e->getFile().':'.$e->getLine(),
            ]);

            return Response::error(
                'The Akaunting MCP server hit an unexpected internal error while handling this request '
                ."(this is a bug in the MCP server, not a connection problem): {$e->getMessage()}"
            );
        }
    }
}

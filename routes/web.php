<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Akaunting MCP Server',
        'mcp_endpoint' => url('/mcp'),
    ]);
});

// OAuth discovery stubs. We use static HTTP Basic credentials, not OAuth, but
// some MCP clients (e.g. mcp-remote) probe these well-known URLs and crash if
// they get HTML 404s. Returning JSON 404 lets the client parse the response and
// fall back to the static Authorization header.
$oauthNotConfigured = fn () => response()->json([
    'error' => 'oauth_not_supported',
    'error_description' => 'This MCP server uses HTTP Basic auth. Send Authorization: Basic <base64(email:password)>.',
], 404);

Route::get('/.well-known/oauth-protected-resource', $oauthNotConfigured);
Route::get('/.well-known/oauth-protected-resource/{path}', $oauthNotConfigured)->where('path', '.*');
Route::get('/.well-known/oauth-authorization-server', $oauthNotConfigured);
Route::get('/.well-known/oauth-authorization-server/{path}', $oauthNotConfigured)->where('path', '.*');

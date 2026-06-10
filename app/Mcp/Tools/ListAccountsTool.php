<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List financial accounts (bank accounts, cash, etc.). Use these IDs when recording transactions.')]
class ListAccountsTool extends Tool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Max results to return.')->default(50),
        ];
    }

    public function handle(Request $request): Response
    {
        return Response::text(json_encode($this->client->get('accounts', ['limit' => $request->get('limit', 50)])));
    }
}

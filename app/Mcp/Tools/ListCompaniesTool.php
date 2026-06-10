<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the companies this user can access. Use a company "id" as the X-Company-ID header (or AKAUNTING_COMPANY_ID) to choose which company the other tools operate on.')]
class ListCompaniesTool extends Tool
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
        return Response::text(json_encode($this->client->get('companies', ['limit' => $request->get('limit', 50)])));
    }
}

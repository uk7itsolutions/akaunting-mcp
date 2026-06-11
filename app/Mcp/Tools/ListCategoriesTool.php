<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List categories, optionally filtered by type. Use these IDs on items, documents and transactions.')]
class ListCategoriesTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'type'  => $schema->string()->description('Filter by category type: "income", "expense", or "item". Omit for all.'),
            'limit' => $schema->integer()->description('Max results to return.')->default(50),
        ];
    }

    protected function execute(Request $request): Response
    {
        $params = ['limit' => $request->get('limit', 50)];

        if ($request->has('type')) {
            $params['search'] = 'type:'.$request->get('type');
        }

        return Response::text(json_encode($this->client->get('categories', $params)));
    }
}

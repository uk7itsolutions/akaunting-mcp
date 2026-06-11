<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List items (products and services).')]
class ListItemsTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Free-text search, e.g. an item name.'),
            'limit'  => $schema->integer()->description('Max results to return.')->default(25),
        ];
    }

    protected function execute(Request $request): Response
    {
        $params = ['limit' => $request->get('limit', 25)];

        if ($request->has('search')) {
            $params['search'] = $request->get('search');
        }

        return Response::text(json_encode($this->client->get('items', $params)));
    }
}

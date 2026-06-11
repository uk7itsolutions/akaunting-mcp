<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List transactions (income and expense payments), optionally filtered by type or search.')]
class ListTransactionsTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'type'   => $schema->string()->description('Filter by type: "income" or "expense". Omit for all.'),
            'search' => $schema->string()->description('Free-text search, e.g. a description or reference.'),
            'limit'  => $schema->integer()->description('Max results to return.')->default(25),
        ];
    }

    protected function execute(Request $request): Response
    {
        $terms = [];

        if ($request->has('type')) {
            $terms[] = 'type:'.$request->get('type');
        }

        if ($request->has('search')) {
            $terms[] = $request->get('search');
        }

        $params = ['limit' => $request->get('limit', 25)];

        if ($terms) {
            $params['search'] = implode(' ', $terms);
        }

        return Response::text(json_encode($this->client->get('transactions', $params)));
    }
}

<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List documents such as invoices and bills, optionally filtered by type or search.')]
class ListDocumentsTool extends Tool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'type'   => $schema->string()->description('Filter by document type: "invoice" or "bill". Omit for all.'),
            'search' => $schema->string()->description('Free-text search, e.g. a document number or contact name.'),
            'limit'  => $schema->integer()->description('Max results to return.')->default(25),
        ];
    }

    public function handle(Request $request): Response
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

        return Response::text(json_encode($this->client->get('documents', $params)));
    }
}

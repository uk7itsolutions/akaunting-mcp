<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List documents such as invoices and bills, optionally filtered by type or search.')]
class ListDocumentsTool extends AkauntingTool
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

    protected function execute(Request $request): Response
    {
        $limit = $request->get('limit', 25);
        $search = $request->has('search') ? $request->get('search') : null;

        // A type is mandatory: Akaunting derives the API permission from
        // type:... in the search string, so a typeless request is always 403.
        if ($request->has('type')) {
            return Response::text(json_encode($this->fetch($request->get('type'), $search, $limit)));
        }

        // No type given: query both kinds and merge so "all documents" works.
        $all = [];
        foreach (['invoice', 'bill'] as $type) {
            $result = $this->fetch($type, $search, $limit);
            $all = array_merge($all, (is_array($result) && isset($result['data'])) ? $result['data'] : []);
        }

        return Response::text(json_encode(['data' => $all]));
    }

    private function fetch(string $type, ?string $search, int $limit): mixed
    {
        $terms = ['type:'.$type];

        if ($search !== null && $search !== '') {
            $terms[] = $search;
        }

        return $this->client->get('documents', [
            'limit'  => $limit,
            'search' => implode(' ', $terms),
        ]);
    }
}

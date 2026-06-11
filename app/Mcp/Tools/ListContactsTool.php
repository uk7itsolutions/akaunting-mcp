<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List contacts (customers and vendors), optionally filtered by type or a free-text search.')]
class ListContactsTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'type'   => $schema->string()->description('Filter by contact type: "customer" or "vendor". Omit for all.'),
            'search' => $schema->string()->description('Free-text search, e.g. a name or email.'),
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

        // No type given: query both kinds and merge so "all contacts" works.
        $all = [];
        foreach (['customer', 'vendor'] as $type) {
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

        return $this->client->get('contacts', [
            'limit'  => $limit,
            'search' => implode(' ', $terms),
        ]);
    }
}

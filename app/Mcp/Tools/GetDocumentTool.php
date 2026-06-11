<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get full details for a single document (invoice or bill) by ID, including its line items.')]
class GetDocumentTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->description('Document ID.')->required(),
            'type'        => $schema->string()->description('Document type: "invoice" or "bill". Akaunting needs this to resolve API permissions; defaults to "invoice".'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $id = $request->get('document_id');

        // Akaunting derives the read permission from type:... in the query
        // string; omitting it yields a malformed permission and a 403.
        $query = ['search' => 'type:'.$request->get('type', 'invoice')];

        return Response::text(json_encode($this->client->get("documents/{$id}", $query)));
    }
}

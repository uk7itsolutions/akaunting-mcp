<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a document (invoice or bill) by ID. Permanently removes the document and its line items.')]
class DeleteDocumentTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->description('Document ID to delete.')->required(),
            'type'        => $schema->string()->description('Document type: "invoice" or "bill". Akaunting needs this to resolve API permissions; defaults to "invoice".'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $id = $request->get('document_id');
        $type = $request->get('type', 'invoice');

        // Akaunting derives the delete permission from type:... in the query
        // string (type:invoice -> delete-sales-invoices); omitting it 403s.
        $this->client->delete("documents/{$id}", ['search' => 'type:'.$type]);

        return Response::text(json_encode(['deleted' => true, 'document_id' => $id]));
    }
}

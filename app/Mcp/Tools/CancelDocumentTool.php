<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\InteractsWithDocuments;
use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Cancel a document (invoice or bill) by setting its status to "cancelled", the same status as Akaunting\'s Cancel button. Note: the REST API has no dedicated cancel action, so unlike the UI button this does not remove any linked payment transactions or recurring templates.')]
class CancelDocumentTool extends AkauntingTool
{
    use InteractsWithDocuments;

    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->description('Document ID to cancel.')->required(),
            'type'        => $schema->string()->description('Document type: "invoice" or "bill". Defaults to the document\'s current type; needed to resolve API permissions.'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $id = $request->get('document_id');
        $typeHint = $request->get('type', 'invoice');

        // Akaunting's update is a full replace, so load the document and PUT it
        // back unchanged except for the status. (The REST API exposes no cancel
        // endpoint; setting status:cancelled mirrors the UI Cancel button.)
        $existing = $this->client->get("documents/{$id}", ['search' => 'type:'.$typeHint]);
        $doc = is_array($existing) && isset($existing['data']) ? $existing['data'] : (array) $existing;

        $type = $doc['type'] ?? $typeHint;

        $items = $this->rebuildItems($doc);

        if ($items === []) {
            return Response::error('Could not read the document line items, so it cannot be safely cancelled via the update endpoint.');
        }

        $data = [
            'type'            => $type,
            'contact_id'      => $doc['contact_id'] ?? null,
            'contact_name'    => $doc['contact_name'] ?? 'Unknown',
            'contact_email'   => $doc['contact_email'] ?? null,
            'currency_code'   => $doc['currency_code'] ?? null,
            'currency_rate'   => $doc['currency_rate'] ?? 1,
            'category_id'     => $doc['category_id'] ?? null,
            'issued_at'       => $this->normalizeDate($this->dateOnly($doc['issued_at'] ?? '')),
            'due_at'          => $this->normalizeDate($this->dateOnly($doc['due_at'] ?? '')),
            'status'          => 'cancelled',
            'document_number' => $doc['document_number'] ?? null,
            // See CreateDocumentTool: Akaunting adds the line-item total to this,
            // so it must be 0 or the document total is doubled.
            'amount'          => 0,
            'items'           => $items,
            'notes'           => $doc['notes'] ?? null,
        ];

        return Response::text(json_encode($this->client->put("documents/{$id}", $data, ['search' => 'type:'.$type])));
    }
}

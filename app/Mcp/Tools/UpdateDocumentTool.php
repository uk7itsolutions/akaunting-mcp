<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\InteractsWithDocuments;
use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update an existing document (invoice or bill). Akaunting replaces the whole document, so this tool loads the current values and overlays only the fields you pass. Omit "items" to keep the existing line items.')]
class UpdateDocumentTool extends AkauntingTool
{
    use InteractsWithDocuments;

    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id'     => $schema->integer()->description('Document ID to update.')->required(),
            'type'            => $schema->string()->description('Document type: "invoice" or "bill". Defaults to the document\'s current type; needed to resolve API permissions.'),
            'contact_id'      => $schema->integer()->description('Customer ID (invoices) or vendor ID (bills). Defaults to the current contact.'),
            'currency_code'   => $schema->string()->description('Currency code, e.g. "USD". Defaults to the current value.'),
            'category_id'     => $schema->integer()->description('Category ID (see list_categories). Defaults to the current value.'),
            'issued_at'       => $schema->string()->description('Issue date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS). Defaults to the current value.'),
            'due_at'          => $schema->string()->description('Due date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS). Defaults to the current value.'),
            'items'           => $schema->string()->description('JSON array of line items to REPLACE the current ones. Each: {"name": string, "quantity": number, "price": number, "item_id"?: int, "tax_ids"?: [int], "description"?: string}. Omit to keep the existing line items.'),
            'document_number' => $schema->string()->description('Document number. Defaults to the current value.'),
            'status'          => $schema->string()->description('Status, e.g. "draft" or "sent". Defaults to the current value.'),
            'notes'           => $schema->string()->description('Notes shown on the document. Defaults to the current value.'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $id = $request->get('document_id');

        // The type is needed for the permission query on both the read and the
        // write. Use the caller's hint, then fall back to the document's type.
        $typeHint = $request->get('type', 'invoice');

        $existing = $this->client->get("documents/{$id}", ['search' => 'type:'.$typeHint]);
        $doc = is_array($existing) && isset($existing['data']) ? $existing['data'] : (array) $existing;

        $type = $request->get('type') ?: ($doc['type'] ?? $typeHint);

        // Akaunting's update is a full replace of the line items. Use the
        // caller's items if provided, otherwise rebuild from the existing doc.
        if ($request->has('items')) {
            $items = json_decode($request->get('items'), true);

            if (! is_array($items) || $items === []) {
                return Response::error('Invalid input (not an Akaunting or connection error): "items" must be a non-empty JSON array of line items.');
            }
        } else {
            $items = $this->rebuildItems($doc);

            if ($items === []) {
                return Response::error('Could not read the existing line items to preserve them. Pass "items" explicitly to update this document.');
            }
        }

        // contact_name is required. Re-resolve it when the contact changes,
        // otherwise reuse the document's stored values.
        if ($request->has('contact_id')) {
            $contactType = $type === 'bill' ? 'vendor' : 'customer';
            $contact = $this->client->get('contacts/'.$request->get('contact_id'), ['search' => 'type:'.$contactType]);
            $contactData = is_array($contact) && isset($contact['data']) ? $contact['data'] : (array) $contact;
            $contactName = $contactData['name'] ?? ($doc['contact_name'] ?? 'Unknown');
            $contactEmail = $contactData['email'] ?? null;
        } else {
            $contactName = $doc['contact_name'] ?? 'Unknown';
            $contactEmail = $doc['contact_email'] ?? null;
        }

        $issuedAt = $request->has('issued_at') ? $request->get('issued_at') : $this->dateOnly($doc['issued_at'] ?? '');
        $dueAt = $request->has('due_at') ? $request->get('due_at') : $this->dateOnly($doc['due_at'] ?? '');

        $data = [
            'type'            => $type,
            'contact_id'      => $request->get('contact_id', $doc['contact_id'] ?? null),
            'contact_name'    => $contactName,
            'contact_email'   => $contactEmail,
            'currency_code'   => $request->get('currency_code', $doc['currency_code'] ?? null),
            'currency_rate'   => $doc['currency_rate'] ?? 1,
            'category_id'     => $request->get('category_id', $doc['category_id'] ?? null),
            'issued_at'       => $this->normalizeDate($issuedAt),
            'due_at'          => $this->normalizeDate($dueAt),
            'status'          => $request->get('status', $doc['status'] ?? 'draft'),
            'document_number' => $request->get('document_number', $doc['document_number'] ?? null),
            // See CreateDocumentTool: Akaunting adds the line-item total to this
            // value, so it must be 0 or the document total is doubled.
            'amount'          => 0,
            'items'           => $items,
        ];

        $data['notes'] = $request->has('notes') ? $request->get('notes') : ($doc['notes'] ?? null);

        return Response::text(json_encode($this->client->put("documents/{$id}", $data, ['search' => 'type:'.$type])));
    }
}

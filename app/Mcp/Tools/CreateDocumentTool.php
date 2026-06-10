<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a document (invoice or bill) from line items. Akaunting recalculates the final totals from the items; this tool fills in the contact name and an estimated amount automatically.')]
class CreateDocumentTool extends Tool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'type'            => $schema->string()->description('Document type: "invoice" or "bill".')->required(),
            'contact_id'      => $schema->integer()->description('Customer ID (for invoices) or vendor ID (for bills).')->required(),
            'currency_code'   => $schema->string()->description('Currency code, e.g. "USD".')->required(),
            'category_id'     => $schema->integer()->description('Category ID (see list_categories).')->required(),
            'issued_at'       => $schema->string()->description('Issue date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS). Must be on or before due date.')->required(),
            'due_at'          => $schema->string()->description('Due date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS). Must be on or after issue date.')->required(),
            'items'           => $schema->string()->description('JSON array of line items. Each: {"name": string, "quantity": number, "price": number, "item_id"?: int, "tax_ids"?: [int], "description"?: string}.')->required(),
            'document_number' => $schema->string()->description('Document number, e.g. "INV-0001". Must be unique. Auto-generated if omitted.'),
            'status'          => $schema->string()->description('Status, e.g. "draft" or "sent".')->default('draft'),
            'notes'           => $schema->string()->description('Notes shown on the document.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $items = json_decode($request->get('items'), true);

        if (! is_array($items) || $items === []) {
            return Response::text('Error: "items" must be a non-empty JSON array of line items.');
        }

        // Akaunting requires an amount; it recalculates the real totals from the
        // line items on save, so the line subtotal is sufficient for validation.
        $amount = 0;
        foreach ($items as $item) {
            $amount += (float) ($item['quantity'] ?? 0) * (float) ($item['price'] ?? 0);
        }

        // contact_name is required by the API; resolve it from the contact.
        $contact = $this->client->get('contacts/'.$request->get('contact_id'));
        $contactData = is_array($contact) && isset($contact['data']) ? $contact['data'] : (array) $contact;

        $type = $request->get('type');

        $data = [
            'type'            => $type,
            'contact_id'      => $request->get('contact_id'),
            'contact_name'    => $contactData['name'] ?? 'Unknown',
            'contact_email'   => $contactData['email'] ?? null,
            'currency_code'   => $request->get('currency_code'),
            'currency_rate'   => 1,
            'category_id'     => $request->get('category_id'),
            'issued_at'       => $this->normalizeDate($request->get('issued_at')),
            'due_at'          => $this->normalizeDate($request->get('due_at')),
            'status'          => $request->get('status', 'draft'),
            'document_number' => $request->get('document_number') ?: $this->generateNumber($type),
            'amount'          => $amount,
            'items'           => $items,
        ];

        if ($request->has('notes')) {
            $data['notes'] = $request->get('notes');
        }

        return Response::text(json_encode($this->client->post('documents', $data)));
    }

    private function normalizeDate(string $value): string
    {
        // Akaunting validates date_format:Y-m-d H:i:s exactly.
        return strlen(trim($value)) === 10 ? trim($value).' 00:00:00' : trim($value);
    }

    private function generateNumber(string $type): string
    {
        $prefix = match ($type) {
            'invoice' => 'INV',
            'bill' => 'BILL',
            default => 'DOC',
        };

        return $prefix.'-'.date('YmdHis');
    }
}

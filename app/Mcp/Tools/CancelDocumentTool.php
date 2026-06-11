<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Cancel a document (invoice or bill). NOTE: cancelling is not supported through the Akaunting API, so this tool cannot perform it — it returns an explanation and the supported alternatives. Call it when asked to cancel so the reason and next steps are clear; do not try to cancel via update_document.')]
class CancelDocumentTool extends AkauntingTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->description('ID of the document the user asked to cancel (optional; for context only).'),
        ];
    }

    protected function execute(Request $request): Response
    {
        return Response::error(implode("\n", [
            'Cancelling a document is not supported through the Akaunting REST API, so the MCP cannot cancel it. This is a known limitation, not a transient or connection error.',
            '',
            'Why: Akaunting has no API endpoint that cancels a document. The web UI "Cancel" button fires an internal event that both sets the status to "cancelled" AND writes a "cancelled" history record. The API can only set fields (via update), which does not create that history record — and without it Akaunting\'s web page for the document crashes. So cancelling through the API would leave the invoice/bill in a broken state.',
            '',
            'What to do instead:',
            '1. Cancel it in the Akaunting web UI using the "Cancel" button on the invoice/bill (the supported way), or',
            '2. If the document is no longer needed at all, delete it with the delete_document tool.',
            '',
            'Do NOT attempt to cancel by calling update_document with status "cancelled" — that produces the broken state described above.',
        ]));
    }
}

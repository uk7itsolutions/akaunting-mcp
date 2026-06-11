<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get full details for a single contact (customer or vendor) by ID.')]
class GetContactTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()->description('Contact ID.')->required(),
            'type'       => $schema->string()->description('Contact type: "customer" or "vendor". Akaunting needs this to resolve API permissions; defaults to "customer".'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $id = $request->get('contact_id');

        // Akaunting derives the read permission from type:... in the query
        // string; omitting it yields a malformed permission and a 403.
        $query = ['search' => 'type:'.$request->get('type', 'customer')];

        return Response::text(json_encode($this->client->get("contacts/{$id}", $query)));
    }
}

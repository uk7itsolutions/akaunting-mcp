<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get full details for a single contact (customer or vendor) by ID.')]
class GetContactTool extends Tool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()->description('Contact ID.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('contact_id');

        return Response::text(json_encode($this->client->get("contacts/{$id}")));
    }
}

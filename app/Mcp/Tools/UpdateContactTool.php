<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update fields on an existing contact. Only the fields you pass are changed.')]
class UpdateContactTool extends Tool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id'    => $schema->integer()->description('Contact ID to update.')->required(),
            'name'          => $schema->string()->description('Contact name.'),
            'type'          => $schema->string()->description('Contact type: "customer" or "vendor".'),
            'email'         => $schema->string()->description('Email address.'),
            'currency_code' => $schema->string()->description('Currency code, e.g. "USD".'),
            'phone'         => $schema->string()->description('Phone number.'),
            'tax_number'    => $schema->string()->description('Tax / VAT number.'),
            'website'       => $schema->string()->description('Website URL.'),
            'address'       => $schema->string()->description('Postal address.'),
            'reference'     => $schema->string()->description('Internal reference.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('contact_id');

        // Akaunting PUT replaces the record, so start from the current values and
        // overlay the provided changes.
        $current = $this->client->get("contacts/{$id}");
        $data = is_array($current) && isset($current['data']) ? $current['data'] : (array) $current;

        foreach (['name', 'type', 'email', 'currency_code', 'phone', 'tax_number', 'website', 'address', 'reference'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->get($field);
            }
        }

        return Response::text(json_encode($this->client->put("contacts/{$id}", $data)));
    }
}

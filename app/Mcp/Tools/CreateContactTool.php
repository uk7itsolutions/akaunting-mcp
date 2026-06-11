<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a contact (customer or vendor).')]
class CreateContactTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name'          => $schema->string()->description('Contact name.')->required(),
            'type'          => $schema->string()->description('Contact type: "customer" or "vendor".')->required(),
            'currency_code' => $schema->string()->description('Currency code, e.g. "USD" (required by Akaunting). See list_currencies.')->required(),
            'email'         => $schema->string()->description('Email address (must be a deliverable address; Akaunting validates the domain).'),
            'phone'         => $schema->string()->description('Phone number.'),
            'tax_number'    => $schema->string()->description('Tax / VAT number.'),
            'website'       => $schema->string()->description('Website URL.'),
            'address'       => $schema->string()->description('Postal address.'),
            'reference'     => $schema->string()->description('Internal reference.'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $data = ['enabled' => 1];

        foreach (['name', 'type', 'email', 'currency_code', 'phone', 'tax_number', 'website', 'address', 'reference'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->get($field);
            }
        }

        // Akaunting resolves the create permission from the type in the query
        // string (type:customer -> create-sales-customers). Without it the
        // permission check is malformed and returns 403.
        $query = ['search' => 'type:'.$request->get('type')];

        return Response::text(json_encode($this->client->post('contacts', $data, $query)));
    }
}

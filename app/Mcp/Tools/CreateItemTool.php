<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create an item (product or service) with sale and/or purchase prices.')]
class CreateItemTool extends Tool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name'           => $schema->string()->description('Item name.')->required(),
            'type'           => $schema->string()->description('Item type: "product" or "service".')->required(),
            'sale_price'     => $schema->number()->description('Price charged to customers.')->required(),
            'purchase_price' => $schema->number()->description('Price paid to vendors.')->default(0),
            'description'    => $schema->string()->description('Item description.'),
            'category_id'    => $schema->integer()->description('Category ID (see list_categories, type "item").'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = [
            'name'           => $request->get('name'),
            'type'           => $request->get('type'),
            'sale_price'     => $request->get('sale_price'),
            'purchase_price' => $request->get('purchase_price', 0),
            'enabled'        => 1,
        ];

        foreach (['description', 'category_id'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->get($field);
            }
        }

        return Response::text(json_encode($this->client->post('items', $data)));
    }
}

<?php

namespace App\Mcp\Tools;

use App\Services\AkauntingClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Record a transaction: an income payment (money received) or an expense payment (money paid out).')]
class CreateTransactionTool extends AkauntingTool
{
    public function __construct(private readonly AkauntingClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'type'           => $schema->string()->description('Transaction type: "income" or "expense".')->required(),
            'account_id'     => $schema->integer()->description('Account the money moves through (see list_accounts).')->required(),
            'amount'         => $schema->number()->description('Amount of money.')->required(),
            'paid_at'        => $schema->string()->description('Payment date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).')->required(),
            'currency_code'  => $schema->string()->description('Currency code, e.g. "USD".')->required(),
            'category_id'    => $schema->integer()->description('Category ID matching the type (see list_categories).')->required(),
            'payment_method' => $schema->string()->description('Payment method code. Default "offline-payments.cash.1" (the seeded Cash method). Find others in Akaunting Settings → Offline Payments.')->default('offline-payments.cash.1'),
            'number'         => $schema->string()->description('Unique transaction number. Auto-generated if omitted.'),
            'contact_id'     => $schema->integer()->description('Related customer (income) or vendor (expense) ID.'),
            'document_id'    => $schema->integer()->description('Invoice/bill ID this payment settles, if any.'),
            'description'    => $schema->string()->description('Description of the transaction.'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $data = [
            'type'           => $request->get('type'),
            'number'         => $request->get('number') ?: 'TXN-'.date('YmdHis'),
            'account_id'     => $request->get('account_id'),
            'amount'         => $request->get('amount'),
            'paid_at'        => $this->normalizeDate($request->get('paid_at')),
            'currency_code'  => $request->get('currency_code'),
            'currency_rate'  => 1,
            'category_id'    => $request->get('category_id'),
            'payment_method' => $request->get('payment_method', 'offline-payments.cash.1'),
        ];

        foreach (['contact_id', 'document_id', 'description'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->get($field);
            }
        }

        return Response::text(json_encode($this->client->post('transactions', $data)));
    }

    private function normalizeDate(string $value): string
    {
        // Akaunting validates date_format:Y-m-d H:i:s exactly.
        return strlen(trim($value)) === 10 ? trim($value).' 00:00:00' : trim($value);
    }
}

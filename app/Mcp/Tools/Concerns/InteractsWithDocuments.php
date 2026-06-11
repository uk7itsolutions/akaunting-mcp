<?php

namespace App\Mcp\Tools\Concerns;

/**
 * Shared helpers for tools that PUT a full document back to Akaunting
 * (update, cancel). Akaunting's update is a full replace, so the existing
 * line items and dates have to be reconstructed from the document response.
 */
trait InteractsWithDocuments
{
    /**
     * Rebuild the create-style items array from an existing document response.
     * The API resource does not expose quantity, so derive it from total/price.
     *
     * @param  array<string, mixed>  $doc
     * @return array<int, array<string, mixed>>
     */
    protected function rebuildItems(array $doc): array
    {
        $rawItems = $doc['items']['data'] ?? ($doc['items'] ?? []);

        $rebuilt = [];

        foreach ((array) $rawItems as $it) {
            $price = (float) ($it['price'] ?? 0);
            $total = (float) ($it['total'] ?? 0);

            $line = [
                'name'     => $it['name'] ?? '',
                'quantity' => $price != 0.0 ? round($total / $price, 4) : 1,
                'price'    => $price,
            ];

            if (! empty($it['item_id'])) {
                $line['item_id'] = $it['item_id'];
            }

            if (! empty($it['description'])) {
                $line['description'] = $it['description'];
            }

            $taxIds = [];
            foreach ((array) ($it['taxes']['data'] ?? $it['taxes'] ?? []) as $tax) {
                if (! empty($tax['tax_id'])) {
                    $taxIds[] = $tax['tax_id'];
                }
            }

            if ($taxIds !== []) {
                $line['tax_ids'] = $taxIds;
            }

            $rebuilt[] = $line;
        }

        return $rebuilt;
    }

    protected function dateOnly(string $value): string
    {
        // Existing dates come back ISO-8601 (e.g. 2026-06-11T00:00:00+00:00);
        // keep just the date part for normalizeDate.
        return substr(trim($value), 0, 10);
    }

    protected function normalizeDate(string $value): string
    {
        // Akaunting validates date_format:Y-m-d H:i:s exactly.
        return strlen(trim($value)) === 10 ? trim($value).' 00:00:00' : trim($value);
    }
}

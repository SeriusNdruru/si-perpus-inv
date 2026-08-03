<?php

namespace App\Services\Inventory;

use App\Models\Item;
use InvalidArgumentException;

class ItemCodeGenerator
{
    /**
     * @var array<string, string>
     */
    private const PREFIXES = [
        'book' => 'BK',
        'equipment' => 'PRL',
        'electronic' => 'ELK',
        'furniture' => 'FUR',
        'consumable' => 'BHP',
        'other' => 'LLN',
    ];

    public function next(string $itemType, bool $lockForUpdate = false): string
    {
        $prefix = $this->prefix($itemType);
        $query = Item::query()->where('item_code', 'like', $prefix.'-%');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $lastSequence = 0;

        foreach ($query->pluck('item_code') as $itemCode) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', (string) $itemCode, $matches) === 1) {
                $lastSequence = max($lastSequence, (int) $matches[1]);
            }
        }

        return $prefix.'-'.str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, string>  $itemTypes
     * @return array<string, string>
     */
    public function nextForTypes(array $itemTypes): array
    {
        $codes = [];

        foreach ($itemTypes as $itemType) {
            $codes[$itemType] = $this->next($itemType);
        }

        return $codes;
    }

    private function prefix(string $itemType): string
    {
        if (! array_key_exists($itemType, self::PREFIXES)) {
            throw new InvalidArgumentException('Jenis barang tidak dikenali.');
        }

        return self::PREFIXES[$itemType];
    }
}

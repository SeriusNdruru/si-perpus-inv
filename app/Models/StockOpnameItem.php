<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $table = 'stock_opname_items';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:2',
            'actual_quantity' => 'decimal:2',
            'difference_quantity' => 'decimal:2',
            'checked_at' => 'datetime',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function findingLabel(): string
    {
        return match ($this->finding_status) {
            'matched' => 'Sesuai',
            'surplus' => 'Lebih',
            'shortage' => 'Kurang',
            'damaged' => 'Rusak',
            'missing' => 'Tidak ditemukan',
            default => ucfirst((string) $this->finding_status),
        };
    }
}

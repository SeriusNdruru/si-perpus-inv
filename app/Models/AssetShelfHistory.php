<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetShelfHistory extends Model
{
    protected $table = 'asset_shelf_history';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function oldShelf(): BelongsTo
    {
        return $this->belongsTo(LibraryShelf::class, 'old_shelf_id');
    }

    public function newShelf(): BelongsTo
    {
        return $this->belongsTo(LibraryShelf::class, 'new_shelf_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryShelf extends Model
{
    protected $fillable = [
        'shelf_code',
        'shelf_name',
        'location_id',
        'classification_range',
        'capacity',
        'description',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_shelf_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

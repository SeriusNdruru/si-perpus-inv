<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_price' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(LibraryShelf::class, 'current_shelf_id');
    }

    public function shelfHistory(): HasMany
    {
        return $this->hasMany(AssetShelfHistory::class)->orderByDesc('changed_at');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class)->orderByDesc('reported_at');
    }

    public function disposal(): HasOne
    {
        return $this->hasOne(Disposal::class);
    }

    public function loanRequestItems(): HasMany
    {
        return $this->hasMany(LoanRequestItem::class);
    }

    public function publicDamageReports(): HasMany
    {
        return $this->hasMany(PublicDamageReport::class);
    }

}

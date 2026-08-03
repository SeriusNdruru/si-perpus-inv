<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    protected $fillable = [
        'item_code',
        'item_name',
        'item_type',
        'tracking_type',
        'category_id',
        'unit_id',
        'description',
        'image_path',
        'contract_number',
        'contract_date',
        'contract_start_date',
        'contract_end_date',
        'asset_type_code',
        'skpd_name',
        'minimum_stock',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_date' => 'date',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'minimum_stock' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function bookDetail(): HasOne
    {
        return $this->hasOne(BookDetail::class);
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'book_authors', 'item_id', 'author_id')
            ->withPivot(['author_role', 'author_order'])
            ->orderByPivot('author_order');
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function loanRequestItems(): HasMany
    {
        return $this->hasMany(LoanRequestItem::class);
    }

    public function publicDamageReports(): HasMany
    {
        return $this->hasMany(PublicDamageReport::class);
    }


    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

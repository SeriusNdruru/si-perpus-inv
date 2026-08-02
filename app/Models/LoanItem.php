<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanItem extends Model
{
    protected $fillable = [
        'loan_id',
        'asset_id',
        'borrowed_at',
        'due_date',
        'condition_out',
        'returned_at',
        'condition_in',
        'return_status',
        'fine_amount',
        'returned_by',
        'return_notes',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_date' => 'date',
            'returned_at' => 'datetime',
            'fine_amount' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function finePayments(): HasMany
    {
        return $this->hasMany(FinePayment::class);
    }

    public function paidFineAmount(): float
    {
        if ($this->relationLoaded('finePayments')) {
            return (float) $this->finePayments->sum('amount');
        }

        return (float) $this->finePayments()->sum('amount');
    }

    public function remainingFineAmount(): float
    {
        return max((float) $this->fine_amount - $this->paidFineAmount(), 0);
    }

    public function finePaymentStatusLabel(): string
    {
        $fine = (float) $this->fine_amount;
        $paid = $this->paidFineAmount();

        if ($fine <= 0 || $paid >= $fine) {
            return 'Lunas';
        }

        return $paid > 0 ? 'Sebagian' : 'Belum dibayar';
    }

    public function conditionOutLabel(): string
    {
        return match ($this->condition_out) {
            'good' => 'Baik',
            'fair' => 'Cukup',
            'damaged' => 'Rusak',
            default => ucfirst((string) $this->condition_out),
        };
    }

    public function returnStatusLabel(): string
    {
        return match ($this->return_status) {
            'borrowed' => 'Dipinjam',
            'returned' => 'Dikembalikan',
            'damaged' => 'Rusak',
            'lost' => 'Hilang',
            default => ucfirst((string) $this->return_status),
        };
    }
}

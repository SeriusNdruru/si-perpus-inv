<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'reservation_code',
        'member_id',
        'item_id',
        'reservation_date',
        'expires_at',
        'queue_number',
        'status',
        'processed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'datetime',
            'expires_at' => 'datetime',
            'queue_number' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'waiting' => 'Menunggu',
            'ready' => 'Siap diambil',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'ready' => 'badge-success',
            'waiting' => 'badge-warning',
            'completed' => 'badge-neutral',
            'cancelled', 'expired' => 'badge-muted',
            default => 'badge-muted',
        };
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['waiting', 'ready'], true);
    }
}

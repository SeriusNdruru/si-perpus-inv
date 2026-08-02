<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanRequest extends Model
{
    protected $fillable = [
        'request_code',
        'member_id',
        'status',
        'requested_at',
        'approved_at',
        'ready_at',
        'pickup_expires_at',
        'collected_at',
        'processed_by',
        'member_notes',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'ready_at' => 'datetime',
            'pickup_expires_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanRequestItem::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Menunggu persetujuan',
            'approved' => 'Disetujui, sedang disiapkan',
            'ready' => 'Siap diambil',
            'collected' => 'Sudah diambil',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'submitted', 'approved' => 'badge-warning',
            'ready', 'collected' => 'badge-success',
            'rejected' => 'badge-danger',
            'cancelled', 'expired' => 'badge-muted',
            default => 'badge-neutral',
        };
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['submitted', 'approved', 'ready'], true);
    }
}

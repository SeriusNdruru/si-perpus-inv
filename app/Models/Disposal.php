<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disposal extends Model
{
    protected $table = 'disposals';

    protected $fillable = [
        'disposal_code',
        'asset_id',
        'reason',
        'proposed_at',
        'approved_at',
        'disposed_at',
        'disposal_method',
        'status',
        'proposed_by',
        'approved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'proposed_at' => 'datetime',
            'approved_at' => 'datetime',
            'disposed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'proposed' => 'Menunggu persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'completed' => 'Selesai dihapuskan',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'proposed' => 'badge-warning',
            'approved' => 'badge-neutral',
            'rejected' => 'badge-danger',
            'completed' => 'badge-success',
            default => 'badge-muted',
        };
    }

    public function methodLabel(): string
    {
        return match ($this->disposal_method) {
            'destroyed' => 'Dimusnahkan',
            'sold' => 'Dijual',
            'donated' => 'Disumbangkan',
            'returned' => 'Dikembalikan ke pemasok',
            'other' => 'Metode lainnya',
            default => '-',
        };
    }
}

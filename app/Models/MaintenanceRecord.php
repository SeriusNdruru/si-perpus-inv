<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    protected $table = 'maintenance_records';

    protected $fillable = [
        'maintenance_code',
        'asset_id',
        'reported_at',
        'started_at',
        'completed_at',
        'issue_description',
        'action_taken',
        'cost',
        'vendor_name',
        'status',
        'reported_by',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'reported' => 'Dilaporkan',
            'in_progress' => 'Sedang diperbaiki',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'reported' => 'badge-warning',
            'in_progress' => 'badge-neutral',
            'completed' => 'badge-success',
            'cancelled' => 'badge-muted',
            default => 'badge-muted',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicDamageReport extends Model
{
    protected $fillable = [
        'report_code',
        'item_id',
        'asset_id',
        'location_id',
        'reporter_name',
        'reporter_contact',
        'issue_description',
        'photo_path',
        'status',
        'handled_by',
        'admin_notes',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Baru dilaporkan',
            'reviewed' => 'Sudah diperiksa',
            'in_progress' => 'Sedang ditangani',
            'resolved' => 'Selesai',
            'rejected' => 'Ditolak',
            default => ucfirst((string) $this->status),
        };
    }
}

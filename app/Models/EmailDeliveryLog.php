<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDeliveryLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'member_id',
        'recipient_email',
        'mail_type',
        'subject',
        'delivery_status',
        'reference_type',
        'reference_id',
        'error_message',
        'sent_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function statusLabel(): string
    {
        return $this->delivery_status === 'sent' ? 'Terkirim' : 'Gagal';
    }
}

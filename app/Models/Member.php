<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'member_code',
        'user_id',
        'member_name',
        'member_type',
        'identity_number',
        'department',
        'phone',
        'email',
        'address',
        'join_date',
        'expiry_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(MemberNotification::class);
    }


    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            'student' => 'Siswa',
            'teacher' => 'Guru',
            'staff' => 'Staf Sekolah',
            'public' => 'Tamu Sekolah',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->member_type]
            ?? ucfirst((string) $this->member_type);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Aktif',
            'suspended' => 'Ditangguhkan',
            'inactive' => 'Tidak aktif',
            'expired' => 'Kedaluwarsa',
            default => ucfirst((string) $this->status),
        };
    }
}

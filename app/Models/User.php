<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_SUPER_ADMIN = 'SUPER_ADMIN';
    public const ROLE_INVENTORY_ADMIN = 'INVENTORY_ADMIN';
    public const ROLE_LIBRARY_ADMIN = 'LIBRARY_ADMIN';
    public const ROLE_LIBRARY_OFFICER_LEGACY = 'LIBRARY_OFFICER';
    public const ROLE_MANAGER = 'MANAGER';
    public const ROLE_MEMBER = 'MEMBER';

    protected $table = 'users';

    protected $fillable = [
        'full_name',
        'username',
        'email',
        'email_verified_at',
        'password_hash',
        'password_changed_at',
        'phone',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_hash' => 'hashed',
            'password_changed_at' => 'datetime',
        ];
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('assigned_at');
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }


    public function hasRole(string $roleCode): bool
    {
        return $this->roleCodes()->contains(strtoupper($roleCode));
    }

    /**
     * @param array<int, string> $roleCodes
     */
    public function hasAnyRole(array $roleCodes): bool
    {
        $normalized = collect($roleCodes)->map(
            static fn (string $roleCode): string => strtoupper($roleCode)
        );

        return $this->roleCodes()->intersect($normalized)->isNotEmpty();
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function roleCodes(): \Illuminate\Support\Collection
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->roles
            ->pluck('role_code')
            ->map(static fn (string $roleCode): string => strtoupper($roleCode))
            ->values();
    }

    public function dashboardRouteName(): string
    {
        return match (true) {
            $this->hasRole(self::ROLE_SUPER_ADMIN) => 'dashboard.super-admin',
            $this->hasRole(self::ROLE_INVENTORY_ADMIN) => 'dashboard.inventory',
            $this->hasAnyRole([self::ROLE_LIBRARY_ADMIN, self::ROLE_LIBRARY_OFFICER_LEGACY]) => 'dashboard.library',
            $this->hasRole(self::ROLE_MANAGER) => 'dashboard.manager',
            default => 'dashboard.member',
        };
    }

    public function primaryRoleLabel(): string
    {
        return match ($this->dashboardRouteName()) {
            'dashboard.super-admin' => 'Super Admin',
            'dashboard.inventory' => 'Admin Inventaris',
            'dashboard.library' => 'Admin Perpustakaan',
            'dashboard.manager' => 'Pimpinan',
            default => 'Anggota',
        };
    }
}

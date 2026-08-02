<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()
            ->where('email', (string) $this->input('email'))
            ->with(['roles', 'member'])
            ->first();

        if (
            $user === null
            || ! Hash::check((string) $this->input('password'), (string) $user->password_hash)
        ) {
            $this->failAuthentication();
        }

        if (! $user->hasRole(User::ROLE_MEMBER)) {
            $this->failAuthentication(
                'Email tersebut bukan akun siswa. Gunakan halaman login pengguna untuk akun admin atau staf.'
            );
        }

        if (! $user->hasVerifiedEmail()) {
            $this->failAuthentication(
                'Email siswa belum diverifikasi. Buka tautan pada email pendaftaran atau kirim ulang verifikasi.'
            );
        }

        $memberIsActive = $user->member !== null
            && $user->member->status === 'active'
            && (
                $user->member->expiry_date === null
                || ! $user->member->expiry_date->isBefore(today())
            );

        if ($user->status !== 'active' || ! $memberIsActive) {
            $this->failAuthentication(
                'Akun siswa belum aktif atau masa keanggotaannya sudah berakhir. Hubungi petugas perpustakaan.'
            );
        }

        Auth::login($user);
        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('email')).'|student|'.$this->ip()
        );
    }

    private function failAuthentication(
        string $message = 'Email atau password siswa tidak benar.'
    ): never {
        RateLimiter::hit($this->throttleKey(), 60);

        throw ValidationException::withMessages([
            'email' => $message,
        ]);
    }
}

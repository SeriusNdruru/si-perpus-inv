<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwords,
    ) {
    }

    public function requestForm(): View
    {
        return view('auth.forgot-password', [
            'portal' => 'internal',
            'pageTitle' => 'Lupa Password Pengguna',
            'heading' => 'Atur ulang password pengguna',
            'description' => 'Masukkan email akun admin, guru, kepala sekolah, atau staf yang aktif.',
            'submitRoute' => route('admin.password.email'),
            'backRoute' => route('login'),
            'backLabel' => 'Kembali ke login pengguna',
        ]);
    }

    public function sendLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwords->sendResetLink(
            email: $request->validated('email'),
            target: PasswordResetService::TARGET_INTERNAL,
            request: $request,
        );

        return back()
            ->withInput(['email' => $request->validated('email')])
            ->with(
                'status',
                'Apabila email terdaftar sebagai akun pengguna aktif, tautan reset password akan dikirim. Periksa kotak masuk dan folder spam.'
            );
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'portal' => 'internal',
            'pageTitle' => 'Password Baru Pengguna',
            'heading' => 'Buat password baru',
            'description' => 'Gunakan minimal 8 karakter yang memuat huruf dan angka.',
            'submitRoute' => route('admin.password.update'),
            'backRoute' => route('login'),
            'backLabel' => 'Kembali ke login pengguna',
            'token' => $token,
            'email' => strtolower(trim((string) $request->query('email'))),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $success = $this->passwords->resetPassword(
            email: $request->validated('email'),
            token: $request->validated('token'),
            password: $request->validated('password'),
            target: PasswordResetService::TARGET_INTERNAL,
            request: $request,
        );

        if (! $success) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Tautan reset tidak valid, sudah digunakan, atau telah kedaluwarsa.',
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Password pengguna berhasil diperbarui. Silakan login menggunakan password baru.');
    }
}

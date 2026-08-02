<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentPasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwords,
    ) {
    }

    public function requestForm(): View
    {
        return view('auth.forgot-password', [
            'portal' => 'student',
            'pageTitle' => 'Lupa Password Siswa',
            'heading' => 'Atur ulang password siswa',
            'description' => 'Masukkan email siswa yang sudah terverifikasi. Tautan reset akan dikirim ke email tersebut.',
            'submitRoute' => route('student.password.email'),
            'backRoute' => route('student.login'),
            'backLabel' => 'Kembali ke login siswa',
        ]);
    }

    public function sendLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwords->sendResetLink(
            email: $request->validated('email'),
            target: PasswordResetService::TARGET_STUDENT,
            request: $request,
        );

        return back()
            ->withInput(['email' => $request->validated('email')])
            ->with(
                'status',
                'Apabila email terdaftar sebagai akun siswa aktif, tautan reset password akan dikirim. Periksa kotak masuk dan folder spam.'
            );
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'portal' => 'student',
            'pageTitle' => 'Password Baru Siswa',
            'heading' => 'Buat password baru',
            'description' => 'Gunakan minimal 8 karakter yang memuat huruf dan angka.',
            'submitRoute' => route('student.password.update'),
            'backRoute' => route('student.login'),
            'backLabel' => 'Kembali ke login siswa',
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
            target: PasswordResetService::TARGET_STUDENT,
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
            ->route('student.login')
            ->with('status', 'Password siswa berhasil diperbarui. Silakan login menggunakan password baru.');
    }
}

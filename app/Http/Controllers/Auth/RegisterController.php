<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function create(Request $request): RedirectResponse
    {
        return redirect()
            ->route('student.login')
            ->with('status', 'Pendaftaran mandiri dinonaktifkan. Akun siswa dibuat oleh admin perpustakaan.');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()
            ->route('student.login')
            ->with('error', 'Pendaftaran mandiri tidak tersedia. Hubungi admin perpustakaan untuk pembuatan akun siswa.');
    }
}

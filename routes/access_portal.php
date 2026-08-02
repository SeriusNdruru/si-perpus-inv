<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Auth\StudentPasswordResetController;
use App\Http\Controllers\PublicInventoryController;
use App\Http\Controllers\PublicPortalController;
use Illuminate\Support\Facades\Route;

if (! Route::has('public.home')) {
    Route::get('/perpustakaan', [PublicPortalController::class, 'home'])
        ->name('public.home');
}

if (! Route::has('public.about')) {
    Route::get('/perpustakaan/tentang', [PublicPortalController::class, 'about'])
        ->name('public.about');
}

if (! Route::has('public.contact')) {
    Route::get('/perpustakaan/kontak', [PublicPortalController::class, 'contact'])
        ->name('public.contact');
}

if (! Route::has('public.contact.store')) {
    Route::post('/perpustakaan/kontak', [PublicPortalController::class, 'storeContact'])
        ->middleware('throttle:5,1')
        ->name('public.contact.store');
}

if (! Route::has('public.catalog')) {
    Route::get('/perpustakaan/katalog', [PublicPortalController::class, 'catalog'])
        ->name('public.catalog');
}

if (! Route::has('public.inventory.general')) {
    Route::get('/inventaris/umum', [PublicInventoryController::class, 'general'])
        ->name('public.inventory.general');
}

if (! Route::has('public.inventory.audit')) {
    Route::get('/inventaris/audit', [PublicInventoryController::class, 'audit'])
        ->name('public.inventory.audit');
}

if (! Route::has('public.inventory.report-damage')) {
    Route::get('/inventaris/lapor-kerusakan', [PublicInventoryController::class, 'createDamageReport'])
        ->name('public.inventory.report-damage');
}

if (! Route::has('public.inventory.report-damage.store')) {
    Route::post('/inventaris/lapor-kerusakan', [PublicInventoryController::class, 'storeDamageReport'])
        ->middleware('throttle:5,1')
        ->name('public.inventory.report-damage.store');
}

if (! Route::has('login')) {
    Route::get('/admin/login', [LoginController::class, 'create'])
        ->name('login');
}

if (! Route::has('login.store')) {
    Route::post('/admin/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
}

if (! Route::has('student.login')) {
    Route::get('/siswa/login', [StudentLoginController::class, 'create'])
        ->name('student.login');
}

if (! Route::has('student.login.store')) {
    Route::post('/siswa/login', [StudentLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('student.login.store');
}

if (! Route::has('register')) {
    Route::get('/siswa/daftar', [RegisterController::class, 'create'])
        ->name('register');
}

if (! Route::has('register.store')) {
    Route::post('/siswa/daftar', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
}
if (! Route::has('admin.password.request')) {
    Route::get('/admin/lupa-password', [AdminPasswordResetController::class, 'requestForm'])
        ->name('admin.password.request');
}

if (! Route::has('admin.password.email')) {
    Route::post('/admin/lupa-password', [AdminPasswordResetController::class, 'sendLink'])
        ->middleware('throttle:3,10')
        ->name('admin.password.email');
}

if (! Route::has('admin.password.reset')) {
    Route::get('/admin/reset-password/{token}', [AdminPasswordResetController::class, 'resetForm'])
        ->name('admin.password.reset');
}

if (! Route::has('admin.password.update')) {
    Route::post('/admin/reset-password', [AdminPasswordResetController::class, 'reset'])
        ->middleware('throttle:5,10')
        ->name('admin.password.update');
}

if (! Route::has('student.password.request')) {
    Route::get('/siswa/lupa-password', [StudentPasswordResetController::class, 'requestForm'])
        ->name('student.password.request');
}

if (! Route::has('student.password.email')) {
    Route::post('/siswa/lupa-password', [StudentPasswordResetController::class, 'sendLink'])
        ->middleware('throttle:3,10')
        ->name('student.password.email');
}

if (! Route::has('student.password.reset')) {
    Route::get('/siswa/reset-password/{token}', [StudentPasswordResetController::class, 'resetForm'])
        ->name('student.password.reset');
}

if (! Route::has('student.password.update')) {
    Route::post('/siswa/reset-password', [StudentPasswordResetController::class, 'reset'])
        ->middleware('throttle:5,10')
        ->name('student.password.update');
}

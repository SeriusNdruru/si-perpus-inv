<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Author;
use App\Models\BookDetail;
use App\Models\Category;
use App\Models\Disposal;
use App\Models\Item;
use App\Models\LibraryShelf;
use App\Models\LibraryVisit;
use App\Models\Location;
use App\Models\LoanRequest;
use App\Models\MaintenanceRecord;
use App\Models\Member;
use App\Models\Publisher;
use App\Models\PublicContactMessage;
use App\Models\PublicDamageReport;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\Unit;
use App\Observers\GenericAuditObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ([
            Category::class,
            Unit::class,
            Supplier::class,
            Location::class,
            Item::class,
            Asset::class,
            BookDetail::class,
            Publisher::class,
            Author::class,
            LibraryShelf::class,
            LibraryVisit::class,
            Member::class,
            StockOpname::class,
            MaintenanceRecord::class,
            Disposal::class,
            LoanRequest::class,
            PublicContactMessage::class,
            PublicDamageReport::class,
        ] as $modelClass) {
            $modelClass::observe(GenericAuditObserver::class);
        }

        View::composer([
            'layouts.app',
            'layouts.public',
            'layouts.inventory-public',
            'layouts.member',
            'public.*',
            'member.*',
            'auth.login',
            'auth.register',
        ], function ($view): void {
            $defaults = [
                'application.name' => config('app.name', 'Sistem Inventaris dan Perpustakaan'),
                'application.short_name' => 'IP',
                'institution.name' => 'Rius Library',
                'institution.address' => 'Alamat instansi belum diatur.',
                'institution.phone' => '',
                'institution.email' => '',
                'portal.hero_title' => 'Perpustakaan yang dekat dengan siswa',
                'portal.hero_subtitle' => 'Temukan koleksi, ajukan peminjaman, dan pantau pengembalian dari satu tempat.',
                'portal.about_title' => 'Tentang Perpustakaan',
                'portal.about_content' => 'Perpustakaan menyediakan layanan koleksi, sirkulasi, dan informasi inventaris.',
                'portal.about_video_url' => '',
                'portal.contact_intro' => 'Hubungi pengelola perpustakaan untuk pertanyaan layanan dan akun anggota.',
                'portal.opening_hours' => 'Senin–Jumat, 07.30–15.30',
            ];

            try {
                $stored = Cache::remember('system.settings.public', 300, function () use ($defaults): array {
                    return DB::table('system_settings')
                        ->whereIn('setting_key', array_keys($defaults))
                        ->pluck('setting_value', 'setting_key')
                        ->all();
                });
            } catch (Throwable) {
                $stored = [];
            }

            $view->with('systemBrand', array_replace($defaults, $stored));
        });

        View::composer('layouts.app', function ($view): void {
            $newPublicDamageReportCount = 0;
            $newOnlineLoanRequestCount = 0;

            try {
                $user = auth()->user();

                if (
                    $user !== null
                    && $user->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN'])
                ) {
                    $newPublicDamageReportCount = PublicDamageReport::query()
                        ->where('status', 'submitted')
                        ->count();
                }

                if (
                    $user !== null
                    && $user->hasAnyRole([
                        'SUPER_ADMIN',
                        'LIBRARY_ADMIN',
                        'LIBRARY_OFFICER',
                    ])
                ) {
                    $newOnlineLoanRequestCount = LoanRequest::query()
                        ->where('status', 'submitted')
                        ->count();
                }
            } catch (Throwable) {
                $newPublicDamageReportCount = 0;
                $newOnlineLoanRequestCount = 0;
            }

            $view->with([
                'newPublicDamageReportCount' => $newPublicDamageReportCount,
                'newOnlineLoanRequestCount' => $newOnlineLoanRequestCount,
            ]);
        });
    }
}

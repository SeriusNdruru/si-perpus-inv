<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AcceptanceTestController;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Http\Controllers\Admin\EmailNotificationController;
use App\Http\Controllers\Admin\SystemReadinessController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Auth\StudentPasswordResetController;
use App\Http\Controllers\Auth\StudentEmailVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PublicInventoryController;
use App\Http\Controllers\PublicPortalController;
use App\Http\Controllers\Inventory\DisposalController;
use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\MaintenanceController;
use App\Http\Controllers\Inventory\PublicDamageReportAdminController;
use App\Http\Controllers\Inventory\StockOpnameController;
use App\Http\Controllers\Library\BookCatalogController;
use App\Http\Controllers\Library\ShelfController;
use App\Http\Controllers\Library\ShelfAssignmentController;
use App\Http\Controllers\Library\MemberController;
use App\Http\Controllers\Library\LoanController;
use App\Http\Controllers\Library\LoanRequestAdminController;
use App\Http\Controllers\Library\LibraryActivityReportController;
use App\Http\Controllers\Library\LibraryVisitController;
use App\Http\Controllers\Library\ContactMessageAdminController;
use App\Http\Controllers\Library\ReturnController;
use App\Http\Controllers\Library\FineController;
use App\Http\Controllers\Library\ReservationController;
use App\Http\Controllers\Master\CategoryController;
use App\Http\Controllers\Master\LocationController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\UnitController;
use App\Http\Controllers\Member\MemberBookController;
use App\Http\Controllers\Member\MemberDashboardController;
use App\Http\Controllers\Member\MemberHistoryController;
use App\Http\Controllers\Member\MemberLoanRequestController;
use App\Http\Controllers\Member\MemberLibraryActivityController;
use App\Http\Controllers\Member\MemberNotificationController;
use App\Http\Controllers\Member\MemberProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/perpustakaan');

Route::get('/media/gambar', [MediaController::class, 'image'])
    ->name('media.image');
Route::get('/media/thumbnail', [MediaController::class, 'thumbnail'])
    ->name('media.thumbnail');

Route::get('/perpustakaan', [PublicPortalController::class, 'home'])
    ->name('public.home');
Route::get('/perpustakaan/tentang', [PublicPortalController::class, 'about'])
    ->name('public.about');
Route::get('/perpustakaan/kontak', [PublicPortalController::class, 'contact'])
    ->name('public.contact');
Route::post('/perpustakaan/kontak', [PublicPortalController::class, 'storeContact'])
    ->middleware('throttle:5,1')
    ->name('public.contact.store');
Route::get('/perpustakaan/katalog', [PublicPortalController::class, 'catalog'])
    ->name('public.catalog');

Route::get('/inventaris/umum', [PublicInventoryController::class, 'general'])
    ->name('public.inventory.general');
Route::get('/inventaris/audit', [PublicInventoryController::class, 'audit'])
    ->name('public.inventory.audit');
Route::get('/inventaris/lapor-kerusakan', [PublicInventoryController::class, 'createDamageReport'])
    ->name('public.inventory.report-damage');
Route::post('/inventaris/lapor-kerusakan', [PublicInventoryController::class, 'storeDamageReport'])
    ->middleware('throttle:5,1')
    ->name('public.inventory.report-damage.store');

Route::get('/admin/login', [LoginController::class, 'create'])
    ->name('login');
Route::post('/admin/login', [LoginController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('login.store');

Route::get('/admin/lupa-password', [AdminPasswordResetController::class, 'requestForm'])
    ->name('admin.password.request');
Route::post('/admin/lupa-password', [AdminPasswordResetController::class, 'sendLink'])
    ->middleware('throttle:3,10')
    ->name('admin.password.email');
Route::get('/admin/reset-password/{token}', [AdminPasswordResetController::class, 'resetForm'])
    ->name('admin.password.reset');
Route::post('/admin/reset-password', [AdminPasswordResetController::class, 'reset'])
    ->middleware('throttle:5,10')
    ->name('admin.password.update');

Route::get('/siswa/login', [StudentLoginController::class, 'create'])
    ->name('student.login');
Route::post('/siswa/login', [StudentLoginController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('student.login.store');

Route::get('/siswa/lupa-password', [StudentPasswordResetController::class, 'requestForm'])
    ->name('student.password.request');
Route::post('/siswa/lupa-password', [StudentPasswordResetController::class, 'sendLink'])
    ->middleware('throttle:3,10')
    ->name('student.password.email');
Route::get('/siswa/reset-password/{token}', [StudentPasswordResetController::class, 'resetForm'])
    ->name('student.password.reset');
Route::post('/siswa/reset-password', [StudentPasswordResetController::class, 'reset'])
    ->middleware('throttle:5,10')
    ->name('student.password.update');

Route::get('/siswa/daftar', [RegisterController::class, 'create'])
    ->name('register');
Route::post('/siswa/daftar', [RegisterController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('register.store');

Route::get('/siswa/verifikasi-email', [StudentEmailVerificationController::class, 'notice'])
    ->name('student.verification.notice');
Route::get('/siswa/verifikasi-email/{user}/{hash}', [StudentEmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('student.verification.verify');
Route::post('/siswa/verifikasi-email/kirim-ulang', [StudentEmailVerificationController::class, 'resend'])
    ->middleware('throttle:3,10')
    ->name('student.verification.resend');

Route::redirect('/login', '/admin/login');
Route::redirect('/daftar', '/siswa/daftar');
Route::redirect('/katalog-buku', '/perpustakaan/katalog');
Route::redirect('/tentang', '/perpustakaan/tentang');
Route::redirect('/kontak', '/perpustakaan/kontak');
Route::redirect('/inventaris-publik', '/inventaris/umum');
Route::redirect('/audit-inventaris', '/inventaris/audit');
Route::redirect('/lapor-kerusakan', '/inventaris/lapor-kerusakan');

Route::middleware(['auth', 'password.session'])->group(function (): void {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:SUPER_ADMIN,INVENTORY_ADMIN,LIBRARY_ADMIN,LIBRARY_OFFICER,MANAGER')
        ->name('dashboard');

    Route::get('/super-admin/dashboard', [DashboardController::class, 'superAdmin'])
        ->middleware('role:SUPER_ADMIN')
        ->name('dashboard.super-admin');

    Route::get('/inventaris/dashboard', [DashboardController::class, 'inventory'])
        ->middleware('role:SUPER_ADMIN,INVENTORY_ADMIN')
        ->name('dashboard.inventory');

    Route::get('/perpustakaan/dashboard', [DashboardController::class, 'library'])
        ->middleware('role:SUPER_ADMIN,LIBRARY_ADMIN,LIBRARY_OFFICER')
        ->name('dashboard.library');

    Route::get('/pimpinan/dashboard', [DashboardController::class, 'manager'])
        ->middleware('role:SUPER_ADMIN,MANAGER')
        ->name('dashboard.manager');

    Route::prefix('/siswa')
        ->middleware('role:MEMBER')
        ->group(function (): void {
            Route::get('/dashboard', [MemberDashboardController::class, 'index'])
                ->name('dashboard.member');

            Route::get('/profil', [MemberProfileController::class, 'show'])
                ->name('member.profile.show');

            Route::get('/aktivitas-perpustakaan', [MemberLibraryActivityController::class, 'index'])
                ->name('member.activity.index');

            Route::get('/katalog', [MemberBookController::class, 'index'])
                ->name('member.books.index');
            Route::post('/katalog/{book}/keranjang', [MemberBookController::class, 'addToCart'])
                ->name('member.books.cart.add');
            Route::delete('/katalog/{book}/keranjang', [MemberBookController::class, 'removeFromCart'])
                ->name('member.books.cart.remove');
            Route::get('/keranjang-pengajuan', [MemberBookController::class, 'cart'])
                ->name('member.books.cart');

            Route::post('/pengajuan-peminjaman', [MemberLoanRequestController::class, 'store'])
                ->name('member.loan-requests.store');
            Route::get('/pengajuan-peminjaman', [MemberLoanRequestController::class, 'index'])
                ->name('member.loan-requests.index');
            Route::get('/pengajuan-peminjaman/{loanRequest}', [MemberLoanRequestController::class, 'show'])
                ->name('member.loan-requests.show');
            Route::patch('/pengajuan-peminjaman/{loanRequest}/batal', [MemberLoanRequestController::class, 'cancel'])
                ->name('member.loan-requests.cancel');

            Route::get('/riwayat-peminjaman', [MemberHistoryController::class, 'loans'])
                ->name('member.history.loans');
            Route::get('/riwayat-peminjaman/{loan}', [MemberHistoryController::class, 'loanDetail'])
                ->whereNumber('loan')
                ->name('member.history.loan-detail');
            Route::get('/denda', [MemberHistoryController::class, 'fines'])
                ->name('member.history.fines');

            Route::get('/notifikasi', [MemberNotificationController::class, 'index'])
                ->name('member.notifications.index');
            Route::patch('/notifikasi/baca-semua', [MemberNotificationController::class, 'readAll'])
                ->name('member.notifications.read-all');
            Route::patch('/notifikasi/{notification}/baca', [MemberNotificationController::class, 'read'])
                ->name('member.notifications.read');
        });

    Route::prefix('/super-admin')
        ->name('admin.')
        ->middleware('role:SUPER_ADMIN')
        ->group(function (): void {
            Route::resource('/pengguna', UserManagementController::class)
                ->parameters(['pengguna' => 'user'])
                ->except(['show', 'destroy'])
                ->names('users');

            Route::patch('/pengguna/{user}/status', [UserManagementController::class, 'updateStatus'])
                ->name('users.status');
            Route::get('/pengguna/{user}/password', [UserManagementController::class, 'editPassword'])
                ->name('users.password.edit');
            Route::patch('/pengguna/{user}/password', [UserManagementController::class, 'updatePassword'])
                ->name('users.password.update');


            Route::get('/pengaturan', [SystemSettingController::class, 'edit'])
                ->name('settings.edit');
            Route::put('/pengaturan', [SystemSettingController::class, 'update'])
                ->name('settings.update');

            Route::get('/email-notifikasi', [EmailNotificationController::class, 'index'])
                ->name('email-notifications.index');
            Route::post('/email-notifikasi/uji-kirim', [EmailNotificationController::class, 'sendTest'])
                ->middleware('throttle:3,10')
                ->name('email-notifications.test');

            Route::get('/riwayat-aktivitas/csv', [AuditLogController::class, 'csv'])
                ->name('audit-logs.csv');
            Route::get('/riwayat-aktivitas', [AuditLogController::class, 'index'])
                ->name('audit-logs.index');
            Route::get('/riwayat-aktivitas/{auditLog}', [AuditLogController::class, 'show'])
                ->name('audit-logs.show');


            Route::get('/backup-database', [DatabaseBackupController::class, 'index'])
                ->name('database-backups.index');
            Route::post('/backup-database', [DatabaseBackupController::class, 'store'])
                ->name('database-backups.store');
            Route::get('/backup-database/{filename}/unduh', [DatabaseBackupController::class, 'download'])
                ->where('filename', '[A-Za-z0-9_.-]+')
                ->name('database-backups.download');
            Route::delete('/backup-database/{filename}', [DatabaseBackupController::class, 'destroy'])
                ->where('filename', '[A-Za-z0-9_.-]+')
                ->name('database-backups.destroy');
            Route::post('/backup-database/restore', [DatabaseBackupController::class, 'restore'])
                ->name('database-backups.restore');


            Route::get('/kesiapan-sistem/laporan', [SystemReadinessController::class, 'download'])
                ->name('system-readiness.download');
            Route::get('/kesiapan-sistem', [SystemReadinessController::class, 'index'])
                ->name('system-readiness.index');

            Route::get('/uji-akses-alur/laporan', [AcceptanceTestController::class, 'download'])
                ->name('acceptance-tests.download');
            Route::get('/uji-akses-alur', [AcceptanceTestController::class, 'index'])
                ->name('acceptance-tests.index');
        });

    Route::middleware('role:SUPER_ADMIN,INVENTORY_ADMIN')->group(function (): void {
        Route::get('/master/kategori/daftar-hapus', [CategoryController::class, 'deleted'])->name('categories.deleted.index');
        Route::patch('/master/kategori/daftar-hapus/{category}/pulihkan', [CategoryController::class, 'restore'])->name('categories.deleted.restore');

        Route::resource('/master/kategori', CategoryController::class)
            ->parameters(['kategori' => 'category'])
            ->except(['show', 'destroy'])
            ->names('categories');

        Route::patch('/master/kategori/{category}/status', [CategoryController::class, 'toggleStatus'])
            ->name('categories.toggle-status');

        Route::get('/master/satuan/daftar-hapus', [UnitController::class, 'deleted'])->name('units.deleted.index');
        Route::patch('/master/satuan/daftar-hapus/{unit}/pulihkan', [UnitController::class, 'restore'])->name('units.deleted.restore');

        Route::resource('/master/satuan', UnitController::class)
            ->parameters(['satuan' => 'unit'])
            ->except(['show', 'destroy'])
            ->names('units');

        Route::patch('/master/satuan/{unit}/status', [UnitController::class, 'toggleStatus'])
            ->name('units.toggle-status');

        Route::get('/master/supplier/daftar-hapus', [SupplierController::class, 'deleted'])->name('suppliers.deleted.index');
        Route::patch('/master/supplier/daftar-hapus/{supplier}/pulihkan', [SupplierController::class, 'restore'])->name('suppliers.deleted.restore');

        Route::resource('/master/supplier', SupplierController::class)
            ->except(['show', 'destroy'])
            ->names('suppliers');

        Route::patch('/master/supplier/{supplier}/status', [SupplierController::class, 'toggleStatus'])
            ->name('suppliers.toggle-status');

        Route::get('/master/lokasi/daftar-hapus', [LocationController::class, 'deleted'])->name('locations.deleted.index');
        Route::patch('/master/lokasi/daftar-hapus/{location}/pulihkan', [LocationController::class, 'restore'])->name('locations.deleted.restore');

        Route::resource('/master/lokasi', LocationController::class)
            ->parameters(['lokasi' => 'location'])
            ->except(['show', 'destroy'])
            ->names('locations');

        Route::patch('/master/lokasi/{location}/status', [LocationController::class, 'toggleStatus'])
            ->name('locations.toggle-status');

        Route::get('/inventaris', fn () => redirect()->route('inventory.items.index'))
            ->name('inventory.index');

        Route::get('/inventaris/daftar-hapus', [ItemController::class, 'deleted'])
            ->name('inventory.deleted-items.index');
        Route::patch('/inventaris/daftar-hapus/{item}/pulihkan', [ItemController::class, 'restore'])
            ->name('inventory.deleted-items.restore');

        Route::resource('/inventaris/barang', ItemController::class)
            ->parameters(['barang' => 'item'])
            ->except(['destroy'])
            ->names('inventory.items');

        Route::patch('/inventaris/barang/{item}/status', [ItemController::class, 'toggleStatus'])
            ->name('inventory.items.toggle-status');


        Route::get('/inventaris/stock-opname', [StockOpnameController::class, 'index'])
            ->name('inventory.stock-opnames.index');
        Route::get('/inventaris/stock-opname/buat', [StockOpnameController::class, 'create'])
            ->name('inventory.stock-opnames.create');
        Route::post('/inventaris/stock-opname', [StockOpnameController::class, 'store'])
            ->name('inventory.stock-opnames.store');
        Route::get('/inventaris/stock-opname/{stockOpname}/pemeriksaan', [StockOpnameController::class, 'edit'])
            ->name('inventory.stock-opnames.edit');
        Route::put('/inventaris/stock-opname/{stockOpname}/pemeriksaan', [StockOpnameController::class, 'update'])
            ->name('inventory.stock-opnames.update');
        Route::patch('/inventaris/stock-opname/{stockOpname}/mulai', [StockOpnameController::class, 'start'])
            ->name('inventory.stock-opnames.start');
        Route::patch('/inventaris/stock-opname/{stockOpname}/selesai', [StockOpnameController::class, 'complete'])
            ->name('inventory.stock-opnames.complete');
        Route::patch('/inventaris/stock-opname/{stockOpname}/batal', [StockOpnameController::class, 'cancel'])
            ->name('inventory.stock-opnames.cancel');
        Route::get('/inventaris/stock-opname/{stockOpname}', [StockOpnameController::class, 'show'])
            ->name('inventory.stock-opnames.show');


        Route::get('/inventaris/pemeliharaan', [MaintenanceController::class, 'index'])
            ->name('inventory.maintenance-records.index');
        Route::get('/inventaris/pemeliharaan/buat', [MaintenanceController::class, 'create'])
            ->name('inventory.maintenance-records.create');
        Route::post('/inventaris/pemeliharaan', [MaintenanceController::class, 'store'])
            ->name('inventory.maintenance-records.store');
        Route::get('/inventaris/pemeliharaan/{maintenanceRecord}/edit', [MaintenanceController::class, 'edit'])
            ->name('inventory.maintenance-records.edit');
        Route::put('/inventaris/pemeliharaan/{maintenanceRecord}', [MaintenanceController::class, 'update'])
            ->name('inventory.maintenance-records.update');
        Route::patch('/inventaris/pemeliharaan/{maintenanceRecord}/mulai', [MaintenanceController::class, 'start'])
            ->name('inventory.maintenance-records.start');
        Route::get('/inventaris/pemeliharaan/{maintenanceRecord}/selesai', [MaintenanceController::class, 'completeForm'])
            ->name('inventory.maintenance-records.complete-form');
        Route::patch('/inventaris/pemeliharaan/{maintenanceRecord}/selesai', [MaintenanceController::class, 'complete'])
            ->name('inventory.maintenance-records.complete');
        Route::patch('/inventaris/pemeliharaan/{maintenanceRecord}/batal', [MaintenanceController::class, 'cancel'])
            ->name('inventory.maintenance-records.cancel');
        Route::get('/inventaris/pemeliharaan/{maintenanceRecord}', [MaintenanceController::class, 'show'])
            ->name('inventory.maintenance-records.show');


        Route::get('/inventaris/penghapusan', [DisposalController::class, 'index'])
            ->name('inventory.disposals.index');
        Route::get('/inventaris/penghapusan/buat', [DisposalController::class, 'create'])
            ->name('inventory.disposals.create');
        Route::post('/inventaris/penghapusan', [DisposalController::class, 'store'])
            ->name('inventory.disposals.store');
        Route::get('/inventaris/penghapusan/{disposal}/edit', [DisposalController::class, 'edit'])
            ->name('inventory.disposals.edit');
        Route::put('/inventaris/penghapusan/{disposal}', [DisposalController::class, 'update'])
            ->name('inventory.disposals.update');
        Route::patch('/inventaris/penghapusan/{disposal}/setujui', [DisposalController::class, 'approve'])
            ->middleware('role:SUPER_ADMIN')
            ->name('inventory.disposals.approve');
        Route::patch('/inventaris/penghapusan/{disposal}/tolak', [DisposalController::class, 'reject'])
            ->middleware('role:SUPER_ADMIN')
            ->name('inventory.disposals.reject');
        Route::get('/inventaris/penghapusan/{disposal}/pelaksanaan', [DisposalController::class, 'completeForm'])
            ->name('inventory.disposals.complete-form');
        Route::patch('/inventaris/penghapusan/{disposal}/pelaksanaan', [DisposalController::class, 'complete'])
            ->name('inventory.disposals.complete');
        Route::get('/inventaris/penghapusan/{disposal}', [DisposalController::class, 'show'])
            ->name('inventory.disposals.show');


        Route::get('/inventaris/laporan-kerusakan-publik', [PublicDamageReportAdminController::class, 'index'])
            ->name('inventory.public-damage-reports.index');
        Route::get('/inventaris/laporan-kerusakan-publik/{publicDamageReport}', [PublicDamageReportAdminController::class, 'show'])
            ->name('inventory.public-damage-reports.show');
        Route::patch('/inventaris/laporan-kerusakan-publik/{publicDamageReport}', [PublicDamageReportAdminController::class, 'update'])
            ->name('inventory.public-damage-reports.update');
    });

    Route::middleware('role:SUPER_ADMIN,LIBRARY_ADMIN,LIBRARY_OFFICER')->group(function (): void {
        Route::get('/perpustakaan', fn () => redirect()->route('library.books.index'))
            ->name('library.index');

        Route::get('/perpustakaan/buku-baru', [BookCatalogController::class, 'index'])
            ->name('library.books.index');
        Route::get('/perpustakaan/buku/{book}', [BookCatalogController::class, 'show'])
            ->name('library.books.show');
        Route::get('/perpustakaan/buku/{book}/katalog', [BookCatalogController::class, 'edit'])
            ->name('library.books.edit');
        Route::put('/perpustakaan/buku/{book}/katalog', [BookCatalogController::class, 'update'])
            ->name('library.books.update');


        Route::get('/perpustakaan/rak/daftar-hapus', [ShelfController::class, 'deleted'])->name('library.shelves.deleted.index');
        Route::patch('/perpustakaan/rak/daftar-hapus/{shelf}/pulihkan', [ShelfController::class, 'restore'])->name('library.shelves.deleted.restore');

        Route::resource('/perpustakaan/rak', ShelfController::class)
            ->parameters(['rak' => 'shelf'])
            ->except(['show', 'destroy'])
            ->names('library.shelves');

        Route::patch('/perpustakaan/rak/{shelf}/status', [ShelfController::class, 'toggleStatus'])
            ->name('library.shelves.toggle-status');


        Route::get('/perpustakaan/penempatan-rak', [ShelfAssignmentController::class, 'index'])
            ->name('library.shelf-assignments.index');
        Route::post('/perpustakaan/penempatan-rak/massal', [ShelfAssignmentController::class, 'bulkUpdate'])
            ->name('library.shelf-assignments.bulk-update');
        Route::get('/perpustakaan/penempatan-rak/{asset}/edit', [ShelfAssignmentController::class, 'edit'])
            ->name('library.shelf-assignments.edit');
        Route::put('/perpustakaan/penempatan-rak/{asset}', [ShelfAssignmentController::class, 'update'])
            ->name('library.shelf-assignments.update');
        Route::delete('/perpustakaan/penempatan-rak/{asset}', [ShelfAssignmentController::class, 'remove'])
            ->name('library.shelf-assignments.remove');


        Route::resource('/perpustakaan/anggota', MemberController::class)
            ->parameters(['anggota' => 'member'])
            ->except(['destroy'])
            ->names('library.members');

        Route::patch('/perpustakaan/anggota/{member}/status', [MemberController::class, 'updateStatus'])
            ->name('library.members.status');

        Route::resource('/perpustakaan/kunjungan', LibraryVisitController::class)
            ->parameters(['kunjungan' => 'visit'])
            ->except(['show'])
            ->names('library.visits');


        Route::resource('/perpustakaan/peminjaman', LoanController::class)
            ->parameters(['peminjaman' => 'loan'])
            ->only(['index', 'create', 'store', 'show'])
            ->names('library.loans');


        Route::get('/perpustakaan/pengembalian', [ReturnController::class, 'index'])
            ->name('library.returns.index');
        Route::get('/perpustakaan/pengembalian/{loanItem}', [ReturnController::class, 'edit'])
            ->name('library.returns.edit');
        Route::put('/perpustakaan/pengembalian/{loanItem}', [ReturnController::class, 'update'])
            ->name('library.returns.update');


        Route::get('/perpustakaan/denda', [FineController::class, 'index'])
            ->name('library.fines.index');
        Route::get('/perpustakaan/denda/pembayaran/{finePayment}/kuitansi', [FineController::class, 'receipt'])
            ->name('library.fines.receipt');
        Route::get('/perpustakaan/denda/{loanItem}', [FineController::class, 'show'])
            ->name('library.fines.show');
        Route::post('/perpustakaan/denda/{loanItem}/pembayaran', [FineController::class, 'store'])
            ->name('library.fines.store');


        Route::get('/perpustakaan/reservasi', [ReservationController::class, 'index'])
            ->name('library.reservations.index');
        Route::get('/perpustakaan/reservasi/buat', [ReservationController::class, 'create'])
            ->name('library.reservations.create');
        Route::post('/perpustakaan/reservasi', [ReservationController::class, 'store'])
            ->name('library.reservations.store');
        Route::get('/perpustakaan/reservasi/{reservation}', [ReservationController::class, 'show'])
            ->name('library.reservations.show');
        Route::patch('/perpustakaan/reservasi/{reservation}/batal', [ReservationController::class, 'cancel'])
            ->name('library.reservations.cancel');


        Route::get('/perpustakaan/pengajuan-peminjaman', [LoanRequestAdminController::class, 'index'])
            ->name('library.loan-requests.index');
        Route::get('/perpustakaan/pengajuan-peminjaman/{loanRequest}', [LoanRequestAdminController::class, 'show'])
            ->name('library.loan-requests.show');
        Route::patch('/perpustakaan/pengajuan-peminjaman/{loanRequest}/setujui', [LoanRequestAdminController::class, 'approve'])
            ->name('library.loan-requests.approve');
        Route::patch('/perpustakaan/pengajuan-peminjaman/{loanRequest}/siap', [LoanRequestAdminController::class, 'ready'])
            ->name('library.loan-requests.ready');
        Route::patch('/perpustakaan/pengajuan-peminjaman/{loanRequest}/ambil', [LoanRequestAdminController::class, 'collect'])
            ->name('library.loan-requests.collect');
        Route::patch('/perpustakaan/pengajuan-peminjaman/{loanRequest}/tolak', [LoanRequestAdminController::class, 'reject'])
            ->name('library.loan-requests.reject');

        Route::get('/perpustakaan/pesan-kontak', [ContactMessageAdminController::class, 'index'])
            ->name('library.contact-messages.index');
        Route::get('/perpustakaan/pesan-kontak/{contactMessage}', [ContactMessageAdminController::class, 'show'])
            ->name('library.contact-messages.show');
        Route::patch('/perpustakaan/pesan-kontak/{contactMessage}', [ContactMessageAdminController::class, 'update'])
            ->name('library.contact-messages.update');
    });

    Route::prefix('/laporan')
        ->name('reports.')
        ->middleware('role:SUPER_ADMIN,INVENTORY_ADMIN,LIBRARY_ADMIN,LIBRARY_OFFICER,MANAGER')
        ->group(function (): void {
            Route::get('/', [ReportController::class, 'index'])->name('index');
        });

    Route::prefix('/laporan')
        ->name('reports.')
        ->middleware('role:SUPER_ADMIN,INVENTORY_ADMIN,MANAGER')
        ->group(function (): void {
            Route::get('/inventaris', [ReportController::class, 'inventory'])->name('inventory');
            Route::get('/inventaris/csv', [ReportController::class, 'inventoryCsv'])->name('inventory.csv');
        });

    Route::prefix('/laporan')
        ->name('reports.')
        ->middleware('role:SUPER_ADMIN,LIBRARY_ADMIN,LIBRARY_OFFICER,MANAGER')
        ->group(function (): void {
            Route::get('/koleksi-buku', [ReportController::class, 'collection'])->name('collection');
            Route::get('/koleksi-buku/csv', [ReportController::class, 'collectionCsv'])->name('collection.csv');
            Route::get('/peminjaman', [ReportController::class, 'loans'])->name('loans');
            Route::get('/peminjaman/csv', [ReportController::class, 'loansCsv'])->name('loans.csv');
            Route::get('/denda', [ReportController::class, 'fines'])->name('fines');
            Route::get('/denda/csv', [ReportController::class, 'finesCsv'])->name('fines.csv');
            Route::get('/anggota', [ReportController::class, 'members'])->name('members');
            Route::get('/anggota/csv', [ReportController::class, 'membersCsv'])->name('members.csv');
            Route::get('/reservasi', [ReportController::class, 'reservations'])->name('reservations');
            Route::get('/reservasi/csv', [ReportController::class, 'reservationsCsv'])->name('reservations.csv');
            Route::get('/kunjungan-siswa', [LibraryActivityReportController::class, 'visits'])->name('library-visits');
            Route::get('/kunjungan-siswa/pdf', [LibraryActivityReportController::class, 'visitsPdf'])->name('library-visits.pdf');
            Route::get('/siswa-sering-berkunjung', [LibraryActivityReportController::class, 'frequentVisitors'])->name('frequent-visitors');
            Route::get('/siswa-sering-berkunjung/pdf', [LibraryActivityReportController::class, 'frequentVisitorsPdf'])->name('frequent-visitors.pdf');
            Route::get('/catatan-peminjaman-siswa', [LibraryActivityReportController::class, 'loanRecords'])->name('loan-records');
            Route::get('/catatan-peminjaman-siswa/pdf', [LibraryActivityReportController::class, 'loanRecordsPdf'])->name('loan-records.pdf');
            Route::redirect('/record-peminjaman', '/laporan/catatan-peminjaman-siswa', 301);
            Route::redirect('/record-peminjaman/pdf', '/laporan/catatan-peminjaman-siswa/pdf', 301);
        });

    Route::post('/admin/logout', [LoginController::class, 'destroy'])
        ->middleware('role:SUPER_ADMIN,INVENTORY_ADMIN,LIBRARY_ADMIN,LIBRARY_OFFICER,MANAGER')
        ->name('logout');

    Route::post('/siswa/logout', [StudentLoginController::class, 'destroy'])
        ->middleware('role:MEMBER')
        ->name('student.logout');
});

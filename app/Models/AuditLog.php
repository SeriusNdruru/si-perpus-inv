<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_data' => 'array',
            'new_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'login' => 'Login',
            'logout' => 'Logout',
            'insert' => 'Tambah data',
            'update' => 'Ubah data',
            'delete' => 'Hapus data',
            'approve' => 'Persetujuan',
            'export' => 'Ekspor data',
            default => 'Aktivitas lainnya',
        };
    }

    public function actionBadgeClass(): string
    {
        return match ($this->action) {
            'login', 'insert', 'approve' => 'badge-success',
            'logout', 'export' => 'badge-neutral',
            'delete' => 'badge-danger',
            'update' => 'badge-warning',
            default => 'badge-muted',
        };
    }

    public function moduleLabel(): string
    {
        return match ($this->module_name) {
            'authentication' => 'Autentikasi',
            'user_management' => 'Pengguna sistem',
            'system_settings' => 'Pengaturan sistem',
            'master_categories' => 'Master kategori',
            'master_units' => 'Master satuan',
            'master_suppliers' => 'Master supplier',
            'master_locations' => 'Master lokasi',
            'inventory_item', 'inventory_items' => 'Data barang',
            'inventory_assets' => 'Unit aset',
            'book_catalog', 'library_catalog' => 'Katalog buku',
            'library_publishers' => 'Penerbit',
            'library_authors' => 'Penulis',
            'library_shelf', 'library_shelves' => 'Rak perpustakaan',
            'shelf_assignment' => 'Penempatan buku',
            'library_member', 'library_members' => 'Anggota perpustakaan',
            'library_loan', 'library_loans' => 'Peminjaman',
            'library_return', 'library_returns' => 'Pengembalian',
            'fine_payment', 'library_fines' => 'Pembayaran denda',
            'reservation', 'library_reservations' => 'Reservasi',
            'report' => 'Laporan',
            'audit_log' => 'Riwayat aktivitas',
            'inventory_maintenance' => 'Pemeliharaan aset',
            'inventory_disposal' => 'Penghapusan aset',
            'database_backup' => 'Backup database',
            'member_self_registration' => 'Pendaftaran anggota mandiri',
            'library_loan_requests' => 'Pengajuan peminjaman anggota',
            'public_damage_reports' => 'Laporan kerusakan publik',
            'public_contact_messages' => 'Pesan kontak publik',
            default => str($this->module_name)->replace('_', ' ')->title()->toString(),
        };
    }

    public function actorLabel(): string
    {
        if ($this->user) {
            return $this->user->full_name;
        }

        return $this->user_id ? 'Pengguna tidak tersedia' : 'Sistem';
    }
}

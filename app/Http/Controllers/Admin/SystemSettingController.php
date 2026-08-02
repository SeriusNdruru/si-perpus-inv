<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    /** @var array<string, array{key:string,type:string,description:string,default:string}> */
    private const DEFINITIONS = [
        'application_name' => ['key' => 'application.name', 'type' => 'string', 'description' => 'Nama aplikasi yang tampil pada halaman login dan judul halaman.', 'default' => 'Sistem Inventaris dan Perpustakaan'],
        'application_short_name' => ['key' => 'application.short_name', 'type' => 'string', 'description' => 'Inisial singkat aplikasi yang tampil pada logo sidebar.', 'default' => 'IP'],
        'institution_name' => ['key' => 'institution.name', 'type' => 'string', 'description' => 'Nama instansi pemilik sistem.', 'default' => 'Rius Library'],
        'institution_address' => ['key' => 'institution.address', 'type' => 'string', 'description' => 'Alamat instansi untuk identitas aplikasi dan dokumen.', 'default' => 'Alamat instansi belum diatur.'],
        'institution_phone' => ['key' => 'institution.phone', 'type' => 'string', 'description' => 'Nomor telepon resmi instansi.', 'default' => ''],
        'institution_email' => ['key' => 'institution.email', 'type' => 'string', 'description' => 'Email resmi instansi.', 'default' => ''],
        'default_loan_days' => ['key' => 'library.default_loan_days', 'type' => 'integer', 'description' => 'Lama peminjaman standar dalam hari.', 'default' => '7'],
        'max_active_loans' => ['key' => 'library.max_active_loans', 'type' => 'integer', 'description' => 'Jumlah maksimal eksemplar yang boleh dipinjam aktif oleh satu anggota.', 'default' => '3'],
        'fine_per_day' => ['key' => 'library.fine_per_day', 'type' => 'decimal', 'description' => 'Nominal denda keterlambatan per hari untuk setiap eksemplar.', 'default' => '1000'],
        'reservation_hold_days' => ['key' => 'library.reservation_hold_days', 'type' => 'integer', 'description' => 'Jumlah hari buku berstatus siap diambil sebelum reservasi kedaluwarsa.', 'default' => '2'],
        'max_active_reservations' => ['key' => 'library.max_active_reservations', 'type' => 'integer', 'description' => 'Jumlah maksimal reservasi aktif untuk satu anggota.', 'default' => '3'],
        'asset_code_separator' => ['key' => 'inventory.asset_code_separator', 'type' => 'string', 'description' => 'Pemisah antara kode barang dan nomor urut unit aset baru.', 'default' => '-'],
        'portal_hero_title' => ['key' => 'portal.hero_title', 'type' => 'string', 'description' => 'Judul utama portal publik.', 'default' => 'Perpustakaan yang dekat dengan siswa'],
        'portal_hero_subtitle' => ['key' => 'portal.hero_subtitle', 'type' => 'string', 'description' => 'Subjudul portal publik.', 'default' => 'Temukan koleksi, ajukan peminjaman, dan pantau pengembalian dari satu tempat.'],
        'portal_about_title' => ['key' => 'portal.about_title', 'type' => 'string', 'description' => 'Judul halaman tentang.', 'default' => 'Tentang Perpustakaan'],
        'portal_about_content' => ['key' => 'portal.about_content', 'type' => 'string', 'description' => 'Isi halaman tentang portal publik.', 'default' => 'Perpustakaan menyediakan layanan koleksi, sirkulasi, dan informasi inventaris.'],
        'portal_about_video_url' => ['key' => 'portal.about_video_url', 'type' => 'string', 'description' => 'Tautan video YouTube atau Vimeo.', 'default' => ''],
        'portal_contact_intro' => ['key' => 'portal.contact_intro', 'type' => 'string', 'description' => 'Pengantar halaman kontak.', 'default' => 'Hubungi pengelola perpustakaan untuk pertanyaan layanan dan akun anggota.'],
        'portal_opening_hours' => ['key' => 'portal.opening_hours', 'type' => 'string', 'description' => 'Jam layanan perpustakaan.', 'default' => 'Senin–Jumat, 07.30–15.30'],
        'loan_request_hold_days' => ['key' => 'library.loan_request_hold_days', 'type' => 'integer', 'description' => 'Masa pengambilan buku setelah pengajuan siap.', 'default' => '2'],
    ];

    public function edit(): View
    {
        $keys = collect(self::DEFINITIONS)->pluck('key')->all();
        $stored = DB::table('system_settings')
            ->whereIn('setting_key', $keys)
            ->pluck('setting_value', 'setting_key');

        $settings = [];
        foreach (self::DEFINITIONS as $field => $definition) {
            $settings[$field] = (string) ($stored[$definition['key']] ?? $definition['default']);
        }

        $latestUpdate = DB::table('system_settings')
            ->leftJoin('users', 'users.id', '=', 'system_settings.updated_by')
            ->whereIn('system_settings.setting_key', $keys)
            ->orderByDesc('system_settings.updated_at')
            ->first(['system_settings.updated_at', 'users.full_name as updater_name']);

        return view('admin.settings.edit', compact('settings', 'latestUpdate'));
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $keys = collect(self::DEFINITIONS)->pluck('key')->all();
        $oldValues = DB::table('system_settings')
            ->whereIn('setting_key', $keys)
            ->pluck('setting_value', 'setting_key')
            ->all();
        $newValues = [];

        DB::transaction(function () use ($request, $validated, $oldValues, &$newValues): void {
            foreach (self::DEFINITIONS as $field => $definition) {
                $value = $this->normalizeValue($field, $validated[$field]);
                $newValues[$definition['key']] = $value;

                DB::table('system_settings')->updateOrInsert(
                    ['setting_key' => $definition['key']],
                    [
                        'setting_value' => $value,
                        'value_type' => $definition['type'],
                        'description' => $definition['description'],
                        'updated_by' => $request->user()?->id,
                        'updated_at' => now(),
                    ]
                );
            }

            DB::table('audit_logs')->insert([
                'user_id' => $request->user()?->id,
                'action' => 'update',
                'module_name' => 'system_settings',
                'table_name' => 'system_settings',
                'record_id' => null,
                'old_data' => json_encode($oldValues, JSON_UNESCAPED_UNICODE),
                'new_data' => json_encode($newValues, JSON_UNESCAPED_UNICODE),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        }, 3);

        Cache::forget('system.settings.public');

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Pengaturan sistem berhasil diperbarui dan langsung diterapkan.');
    }

    private function normalizeValue(string $field, mixed $value): string
    {
        if ($field === 'fine_per_day') {
            return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        }

        return (string) $value;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ModulePlaceholderController extends Controller
{
    public function inventory(): View
    {
        return view('modules.placeholder', [
            'title' => 'Modul Inventaris',
            'description' => 'Tahap berikutnya: input barang, pembuatan aset, stok, dan opname.',
        ]);
    }

    public function library(): View
    {
        return view('modules.placeholder', [
            'title' => 'Modul Perpustakaan',
            'description' => 'Tahap berikutnya: katalog buku, rak, anggota, peminjaman, dan pengembalian.',
        ]);
    }

    public function reports(): View
    {
        return view('modules.placeholder', [
            'title' => 'Laporan',
            'description' => 'Tahap berikutnya: filter dan ekspor laporan inventaris serta perpustakaan.',
        ]);
    }
}

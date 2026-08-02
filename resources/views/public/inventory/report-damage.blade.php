@extends('layouts.inventory-public')

@section('title', 'Lapor Kerusakan')

@section('content')
<section class="portal-page-hero portal-page-hero-report">
    <div class="portal-container">
        <span class="portal-kicker">Laporan publik tanpa login</span>
        <h1>Laporkan barang atau buku yang rusak</h1>
        <p>Isi informasi yang diketahui. Nama dan kontak pelapor boleh dikosongkan.</p>
    </div>
</section>

<section class="portal-section">
    <div class="portal-container portal-form-shell">
        <form method="POST" action="{{ route('public.inventory.report-damage.store') }}" enctype="multipart/form-data" class="portal-form">
            @csrf
            <div class="portal-form-grid">
                <label>
                    <span>Barang atau buku</span>
                    <select name="item_id">
                        <option value="">Pilih barang</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected((int) old('item_id') === $item->id)>{{ $item->item_code }} · {{ $item->item_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Kode aset, bila diketahui</span>
                    <select name="asset_id">
                        <option value="">Pilih kode aset</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}" @selected((int) old('asset_id') === $asset->id)>{{ $asset->asset_code }} · {{ $asset->item_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Lokasi kejadian</span>
                    <select name="location_id">
                        <option value="">Pilih lokasi</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) old('location_id') === $location->id)>{{ $location->location_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Nama pelapor</span>
                    <input name="reporter_name" type="text" maxlength="180" value="{{ old('reporter_name') }}">
                </label>
                <label>
                    <span>Kontak pelapor</span>
                    <input name="reporter_contact" type="text" maxlength="150" value="{{ old('reporter_contact') }}" placeholder="Nomor telepon atau email">
                </label>
                <label>
                    <span>Foto bukti</span>
                    <input name="photo" type="file" accept=".jpg,.jpeg,.png,.webp">
                </label>
                <label class="portal-field-full">
                    <span>Penjelasan kerusakan *</span>
                    <textarea name="issue_description" rows="7" maxlength="5000" required placeholder="Jelaskan kerusakan, posisi barang, dan kondisi terakhir.">{{ old('issue_description') }}</textarea>
                </label>
                <input name="website" type="text" tabindex="-1" autocomplete="off" class="portal-honeypot">
            </div>
            <div class="portal-form-note">Pilih minimal barang, kode aset, atau lokasi. File foto maksimal 3 MB.</div>
            <button type="submit" class="portal-button portal-button-primary">Kirim laporan kerusakan</button>
        </form>
    </div>
</section>
@endsection

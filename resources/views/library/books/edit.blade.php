@extends('layouts.app')

@section('title', 'Lengkapi Katalog')
@section('page-title', 'Lengkapi Katalog Buku')

@section('content')
    <section class="panel form-panel form-panel-wide">
        <div class="panel-header panel-header-wrap">
            <div>
                <p class="eyebrow">Perpustakaan</p>
                <h2>{{ $book->item_name }}</h2>
            </div>
            <a href="{{ route('library.books.show', $book) }}" class="button-secondary">Kembali ke detail</a>
        </div>

        <form method="POST" action="{{ route('library.books.update', $book) }}" enctype="multipart/form-data" class="data-form">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="alert alert-danger form-errors">
                    <strong>Data katalog belum dapat disimpan.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="locked-data-grid">
                <div>
                    <span>Kode inventaris</span>
                    <strong>{{ $book->item_code }}</strong>
                </div>
                <div>
                    <span>Judul buku</span>
                    <strong>{{ $book->item_name }}</strong>
                </div>
                <div>
                    <span>Kategori</span>
                    <strong>{{ $book->category?->category_name ?? '-' }}</strong>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-heading">
                    <span>1</span>
                    <div>
                        <h3>Identitas terbitan</h3>
                        <p>ISBN otomatis dibersihkan dari spasi dan tanda hubung saat disimpan.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field">
                        <label for="isbn_10">ISBN-10</label>
                        <input
                            id="isbn_10"
                            name="isbn_10"
                            type="text"
                            maxlength="20"
                            value="{{ old('isbn_10', $book->bookDetail?->isbn_10) }}"
                            placeholder="Contoh: 979123456X"
                        >
                    </div>

                    <div class="form-field">
                        <label for="isbn_13">ISBN-13</label>
                        <input
                            id="isbn_13"
                            name="isbn_13"
                            type="text"
                            maxlength="20"
                            value="{{ old('isbn_13', $book->bookDetail?->isbn_13) }}"
                            placeholder="Contoh: 9786021234567"
                        >
                    </div>

                    <div class="form-field">
                        <label for="publisher_id">Penerbit tersimpan</label>
                        <select id="publisher_id" name="publisher_id">
                            <option value="">Pilih penerbit</option>
                            @foreach ($publishers as $publisher)
                                <option value="{{ $publisher->id }}" @selected((string) old('publisher_id', $book->bookDetail?->publisher_id) === (string) $publisher->id)>
                                    {{ $publisher->publisher_name }}{{ $publisher->city ? ' - '.$publisher->city : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="new_publisher_name">Atau tambah penerbit baru</label>
                        <input
                            id="new_publisher_name"
                            name="new_publisher_name"
                            type="text"
                            maxlength="180"
                            value="{{ old('new_publisher_name') }}"
                            placeholder="Nama penerbit baru"
                        >
                        <small>Jika diisi, penerbit baru akan dipakai menggantikan pilihan di sebelah kiri.</small>
                    </div>

                    <div class="form-field">
                        <label for="publication_year">Tahun terbit</label>
                        <input
                            id="publication_year"
                            name="publication_year"
                            type="number"
                            min="1000"
                            max="2200"
                            value="{{ old('publication_year', $book->bookDetail?->publication_year) }}"
                        >
                    </div>

                    <div class="form-field">
                        <label for="grade_level">Kategori kelas <span>*</span></label>
                        <select id="grade_level" name="grade_level" required>
                            <option value="">Pilih peruntukan kelas</option>
                            @foreach ($gradeLevels as $value => $label)
                                <option value="{{ $value }}" @selected(old('grade_level', $book->bookDetail?->grade_level ?? 'umum') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small>Pilih kelas utama yang sesuai. Gunakan Umum / Semua Kelas untuk buku yang dapat dibaca seluruh siswa.</small>
                    </div>

                    <div class="form-field">
                        <label for="edition">Edisi</label>
                        <input
                            id="edition"
                            name="edition"
                            type="text"
                            maxlength="80"
                            value="{{ old('edition', $book->bookDetail?->edition) }}"
                            placeholder="Contoh: Edisi 2"
                        >
                    </div>

                    <div class="form-field">
                        <label for="language">Bahasa <span>*</span></label>
                        <input
                            id="language"
                            name="language"
                            type="text"
                            maxlength="50"
                            value="{{ old('language', $book->bookDetail?->language ?? 'Indonesia') }}"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="page_count">Jumlah halaman</label>
                        <input
                            id="page_count"
                            name="page_count"
                            type="number"
                            min="1"
                            max="100000"
                            value="{{ old('page_count', $book->bookDetail?->page_count) }}"
                        >
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-heading">
                    <span>2</span>
                    <div>
                        <h3>Penulis dan kode katalog otomatis</h3>
                        <p>Tulis satu nama penulis pada setiap baris. Kode klasifikasi dan nomor panggil dibuat otomatis saat katalog disimpan.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="authors_text">Penulis</label>
                        <textarea
                            id="authors_text"
                            name="authors_text"
                            rows="5"
                            placeholder="Nama Penulis Pertama&#10;Nama Penulis Kedua"
                        >{{ old('authors_text', $book->authors->pluck('author_name')->join("\n")) }}</textarea>
                        <small>Maksimal 20 penulis. Nama yang sama akan digunakan kembali dari data penulis.</small>
                    </div>

                    <div class="form-field">
                        <label for="classification_code_preview">Kode klasifikasi otomatis</label>
                        <input
                            id="classification_code_preview"
                            type="text"
                            value="{{ $automaticCodes['classification_code'] }}"
                            readonly
                            aria-readonly="true"
                        >
                        <small>Dibuat dari kategori, judul, dan deskripsi buku. Jika kode kategori berbentuk DDC, kode tersebut dipakai sebagai acuan utama. Jika topik tidak dikenali, sistem memakai kode 000.</small>
                    </div>

                    <div class="form-field">
                        <label for="call_number_preview">Nomor panggil otomatis</label>
                        <input
                            id="call_number_preview"
                            type="text"
                            value="{{ $automaticCodes['call_number'] }}"
                            readonly
                            aria-readonly="true"
                        >
                        <small>Dibuat dari kode klasifikasi, tiga huruf nama penulis pertama, dan huruf awal judul. Nilai diperbarui saat katalog disimpan.</small>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="catalog_notes">Catatan katalog</label>
                        <textarea id="catalog_notes" name="catalog_notes" rows="4" placeholder="Catatan tambahan tentang buku">{{ old('catalog_notes', $book->bookDetail?->catalog_notes) }}</textarea>
                    </div>
                </div>
            </div>


            <div class="form-section">
                <div class="form-section-heading">
                    <span>3</span>
                    <div>
                        <h3>Cover buku</h3>
                        <p>Cover akan tampil pada portal umum dan dashboard anggota.</p>
                    </div>
                </div>

                <div class="catalog-cover-grid">
                    <div class="admin-cover-preview">
                        @if ($book->bookDetail?->cover_path || $book->image_path)
                            <img src="{{ asset('storage/'.($book->bookDetail?->cover_path ?: $book->image_path)) }}" alt="Cover {{ $book->item_name }}">
                        @else
                            <span>{{ mb_strtoupper(mb_substr($book->item_name, 0, 2)) }}</span>
                        @endif
                    </div>
                    <div>
                        <div class="form-field">
                            <label for="cover_image">{{ $book->bookDetail?->cover_path || $book->image_path ? 'Ganti cover buku' : 'Cover buku' }} @if (! ($book->bookDetail?->cover_path || $book->image_path))<span>*</span>@endif</label>
                            <input id="cover_image" name="cover_image" type="file" accept=".jpg,.jpeg,.png,.webp" @required(! ($book->bookDetail?->cover_path || $book->image_path))>
                            <small>Cover wajib tersedia. Format JPG, PNG, atau WEBP. Maksimal 3 MB.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="inline-notice">
                <strong>Status katalog dihitung otomatis.</strong>
                <p>Katalog menjadi lengkap jika memiliki ISBN, penerbit, tahun terbit, kategori kelas, dan minimal satu penulis. Kode klasifikasi serta nomor panggil dibuat otomatis saat disimpan. Eksemplar tetap belum diproses sampai rak ditentukan.</p>
            </div>

            <div class="form-actions">
                <a href="{{ route('library.books.show', $book) }}" class="button-secondary">Batal</a>
                <button type="submit" class="button-primary">Simpan katalog</button>
            </div>
        </form>
    </section>
@endsection

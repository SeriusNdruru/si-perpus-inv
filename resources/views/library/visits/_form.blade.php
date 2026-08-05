<div class="form-grid form-grid-two">
    <div class="form-field form-field-full">
        <label for="member_id">Siswa <span class="required-marker">*</span></label>
        <select id="member_id" name="member_id" required>
            <option value="">Pilih siswa yang sudah terdaftar</option>
            @foreach ($students as $student)
                <option value="{{ $student->id }}" @selected((string) old('member_id', $visit->member_id ?? '') === (string) $student->id)>
                    {{ $student->member_name }} · {{ $student->member_code }} · {{ $student->department ?: 'Kelas belum diisi' }}
                </option>
            @endforeach
        </select>
        <small>Data nama, NIS/NISN, dan kelas diambil dari data anggota yang sudah terdaftar.</small>
    </div>

    <div class="form-field">
        <label for="visit_date">Tanggal kunjungan <span class="required-marker">*</span></label>
        <input id="visit_date" name="visit_date" type="date" value="{{ old('visit_date', isset($visit) ? $visit->visit_date?->format('Y-m-d') : today()->format('Y-m-d')) }}" required>
    </div>

    <div class="form-field">
        <label for="visit_time">Waktu kunjungan <span class="required-marker">*</span></label>
        <input id="visit_time" name="visit_time" type="time" value="{{ old('visit_time', isset($visit) ? substr((string) $visit->visit_time, 0, 5) : now()->format('H:i')) }}" required>
    </div>

    <div class="form-field form-field-full">
        <label>Kegiatan</label>
        <input type="text" value="Membaca buku" disabled>
        <small>Kegiatan dicatat otomatis sebagai membaca buku di perpustakaan.</small>
    </div>

    <div class="form-field form-field-full">
        <label for="notes">Catatan</label>
        <textarea id="notes" name="notes" rows="4" maxlength="1000" placeholder="Catatan tambahan bila diperlukan">{{ old('notes', $visit->notes ?? '') }}</textarea>
    </div>
</div>

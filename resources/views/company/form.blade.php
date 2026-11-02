{{-- REMARK
 Tujuan       : Layar Master Data > Perusahaan (Single Form kind=master, satu data): nama, nama PT / CV, NPWP, telepon, e-mail, alamat, logo (unggah, pratinjau, Hapus logo) dan tampilan kop (tiga pilihan) dengan PRATINJAU KOP langsung di browser.
                Datanya menjadi satu-satunya sumber kop semua cetakan (App\Support\Company). Aplikasi tetap bernama ADMINKU; hanya kop cetakan yang berubah.
 Sumber hulu  : CompanyController@edit (company = Company::get(), logoUrl).
 Input        : company, logoUrl.
 Output       : PUT company.update (multipart: logo).
 Dipakai oleh : route company.edit (menu Data Master > Perusahaan, hanya Supervisor).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Company'])
@section('body')
    @php
        $v = fn (string $k) => old($k, $company[$k] ?? null);
        $e = fn (string $k) => $errors->first($k) ?: null;
        $styles = ['logo_name_address' => 'Logo + Nama + Alamat', 'logo_name' => 'Logo + Nama', 'name' => 'Nama saja'];
    @endphp
    @include('partials.flash')
    <x-dl::form title="Perusahaan" subtitle="Data ini menjadi kop semua cetakan (dokumen stok, Lembar Hitung, Berita Acara, dan laporan)" panel="top" kind="master" status="active"
        :hide="['activate', 'deactivate', 'new']" :action="route('company.update')" :hidden="['_method' => 'PUT']" enctype="multipart/form-data">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="name" label="Company Name" :value="$v('name')" required maxlength="100" :error="$e('name')" hint="Dicetak tebal di kop." />
            <x-dl::field.text name="legal_name" label="Legal Entity Name" :value="$v('legal_name')" maxlength="150" :error="$e('legal_name')" hint="Nama PT / CV untuk urusan pajak. Dicetak kecil bila berbeda dari nama perusahaan." />
            <x-dl::field.text name="npwp" label="Tax ID (NPWP)" :value="$v('npwp')" maxlength="30" :error="$e('npwp')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="phone" label="Phone" :value="$v('phone')" maxlength="60" :error="$e('phone')" />
            <x-dl::field.text name="email" label="Email" :value="$v('email')" maxlength="100" :error="$e('email')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="address" label="Company Address" :value="$v('address')" multiline maxlength="500" :error="$e('address')" hint="Satu baris alamat per baris. Pendek lebih baik: kop cetakan memuat paling banyak tiga baris alamat dan kontak." />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.upload name="logo" label="Logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" :value="$logoUrl ? ['url' => $logoUrl, 'name' => 'Logo saat ini'] : null" :error="$e('logo')"
                hint="PNG, JPG, atau WebP (bukan SVG), paling besar 1 MB. Otomatis diperkecil selebar paling 600 piksel." />
            @if ($logoUrl)
                <x-dl::field.checkbox name="remove_logo" label="Hapus logo" text="Hapus logo saat ini" :value="false" />
            @endif
        </x-dl::form.row>
        <x-dl::divider label="Cuaca di Beranda" />
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="weather_city" label="Weather City" :value="$v('weather_city')" maxlength="80" :error="$e('weather_city')" hint="Nama yang ditampilkan di kartu cuaca, mis. Malang." />
            <x-dl::field.text name="weather_lat" label="Latitude" :value="$v('weather_lat')" maxlength="12" :error="$e('weather_lat')" hint="-90 sampai 90, mis. -7.98." />
            <x-dl::field.text name="weather_lon" label="Longitude" :value="$v('weather_lon')" maxlength="12" :error="$e('weather_lon')" hint="-180 sampai 180, mis. 112.63." />
        </x-dl::form.row>
        <p class="dl-note">Cuaca di Beranda mengikuti posisi perangkat. Isian ini hanya CADANGAN bila lokasi perangkat tidak bisa dipakai (izin ditolak atau situs belum memakai HTTPS). Dikosongkan = tanpa cadangan: kartu cuaca tidak tampil.</p>
        <x-dl::form.row>
            <x-dl::field.radio name="kop_style" label="Print Header Style" :options="$styles" :value="$v('kop_style')" required :error="$e('kop_style')" />
        </x-dl::form.row>

        <x-dl::divider label="Pratinjau kop" />
        <style>
            .sstock-kop { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border: 1px solid var(--dl-border); border-radius: 8px; background: #fff; color: #111; max-width: 520px; }
            .sstock-kop img { max-height: 48px; max-width: 120px; object-fit: contain; }
            .sstock-kop b { display: block; font-size: 15px; }
            .sstock-kop small { display: block; font-size: 11px; color: #444; white-space: pre-line; }
            .sstock-kop__badge { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; background: #222; color: #fff; font-weight: 700; }
        </style>
        <div class="sstock-kop" data-kop>
            <span class="sstock-kop__badge" data-kop-badge hidden></span>
            <img data-kop-logo alt="Logo" hidden>
            <div><b data-kop-name></b><small data-kop-legal></small><small data-kop-addr></small></div>
        </div>
        <p class="dl-note" data-kop-note hidden>Logo belum ada: kop akan memakai &quot;Nama saja&quot; sampai logo diunggah. Pilihan Anda tetap disimpan.</p>
        <p class="dl-note">Pratinjau memberi gambaran. Hasil cetak mengikuti tata letak kertas dan memotong teks yang terlalu panjang.</p>
    </x-dl::form>
    <script>
        (() => {
            const q = s => document.querySelector(s), field = n => q(`[name="${n}"]`);
            const box = q('[data-kop]'), logo = q('[data-kop-logo]'), badge = q('[data-kop-badge]'), nameEl = q('[data-kop-name]'), legalEl = q('[data-kop-legal]'), addrEl = q('[data-kop-addr]'), note = q('[data-kop-note]');
            const saved = @json($logoUrl), fallback = @json(config('devlib.company.name'));
            let chosen = null;
            const norm = t => (t || '').toLowerCase().replace(/\s+/g, '');
            const render = () => {
                const style = (q('input[name="kop_style"]:checked') || {}).value || 'name';
                const removed = field('remove_logo') && field('remove_logo').checked;
                const url = chosen || (removed ? null : saved);
                const name = (field('name').value || '').trim(), legal = (field('legal_name').value || '').trim();
                const showLogo = !!url && style !== 'name';
                logo.hidden = !showLogo;
                if (showLogo) logo.src = url;
                badge.hidden = showLogo || name !== '';
                nameEl.textContent = name || fallback;
                legalEl.textContent = legal && norm(legal) !== norm(name) ? legal : '';
                const lines = (field('address').value || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
                const contact = [(field('phone').value || '').trim() ? 'Telp. ' + field('phone').value.trim() : '', (field('email').value || '').trim()].filter(Boolean).join(' · ');
                addrEl.textContent = style === 'logo_name_address' && url ? lines.concat(contact ? [contact] : []).slice(0, 3).join('\n') : '';
                note.hidden = !(style !== 'name' && !url);
            };
            document.addEventListener('input', render);
            document.addEventListener('change', e => {
                if (e.target && e.target.name === 'logo' && e.target.files && e.target.files[0]) { chosen = URL.createObjectURL(e.target.files[0]); }
                render();
            });
            render();
        })();
    </script>
@endsection

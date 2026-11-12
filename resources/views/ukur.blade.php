{{-- REMARK
 Tujuan       : Halaman ukur layar publik (GET /ukur): lebar x tinggi CSS, DPR, orientasi, tinggi nyata, area aman, view yang aktif. Dibuka dari HP untuk mengukur layar sungguhan, hasilnya disalin ke chat.
 Sumber hulu  : DevLib x-dl2::screen-info (sisi klien) + tampilan yang dilayani server (Devlib2\Device: view, sumbernya, OS); tanpa data pengguna, tanpa login.
 Input        : tidak ada (view, view-source, os dibaca dari permintaan).
 Dipakai oleh : rute ukur (routes/web.php).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Screen info'])
@section('body')
    <x-dl2::screen-info id="sinfo" :data-view="\Devlib2\Device::view(request())" :data-view-source="\Devlib2\Device::viewSource(request())" :data-os="\Devlib2\Device::os(request())" />
    <p><button type="button" class="dl2-btn dl2-btn-sm" data-sinfo-copy>Salin hasil ukur</button> <span id="sinfo-ok" hidden>Tersalin.</span></p>
    <script>
        // Salin ukuran layar + tampilan yang dilayani server ke clipboard (untuk ditempel ke chat).
        document.querySelector('[data-sinfo-copy]').addEventListener('click', async () => {
            const el = document.getElementById('sinfo'), text = el.textContent.trim() + ' | view ' + el.dataset.view + ' (' + el.dataset.viewSource + ') | os ' + el.dataset.os;
            try { await navigator.clipboard.writeText(text); } catch (e) { const t = document.createElement('textarea'); t.value = text; document.body.append(t); t.select(); document.execCommand('copy'); t.remove(); }
            document.getElementById('sinfo-ok').hidden = false;
        });
    </script>
@endsection

{{-- REMARK
 Tujuan       : Pengingat Stok Opname di Beranda untuk pengguna yang punya modul SO: terakhir, berikutnya, status (biasa / kuning H-3 dan hari H / merah terlambat), atau opname yang sedang berjalan (terisi N dari M).
                Tombol Mulai Opname hanya untuk Supervisor; Data Entry melihat pengingat dan tautan ke layar opname.
 Sumber hulu  : HomeController (countReminder = App\Services\CountSchedule::state, canStartCount).
 Input        : countReminder (null = tidak tampil), canStartCount.
 Output       : x-dl2::alert + tombol (data-open-tab SO membuka tab Stok Opname).
 Dipakai oleh : home.blade.php (dashboard).
 Status       : Aktif.
--}}
@if ($countReminder)
    @php
        $r = $countReminder;
        $tone = ['neutral' => 'info', 'warn' => 'warn', 'danger' => 'danger'][$r['tone']];
    @endphp
    @if ($r['running'])
        <x-alert-bar tone="info" icon="fa-clipboard-check" :message="('Stok Opname '.$r['running']->doc_no.' sedang berjalan ('.$r['running']->statusLabel().', terisi '.$r['running_progress'].').')">
            <x-dl2::button size="sm" variant="secondary" icon="fa-clipboard-check" label="Open Stok Opname" href="#" data-open-tab="SO" />
        </x-alert-bar>
    @else
        <x-alert-bar :tone="$tone" icon="fa-clipboard-check" :message="($r['headline'].' '.$r['detail'].'.')">
            <x-dl2::button size="sm" variant="secondary" icon="fa-clipboard-check" label="Open Stok Opname" href="#" data-open-tab="SO" />
            @if ($canStartCount)
                <x-dl2::button size="sm" icon="fa-play" label="Start Count" :href="route('stock-counts.create')" target="_blank" />
            @endif
        </x-alert-bar>
    @endif
@endif

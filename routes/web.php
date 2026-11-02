<?php

/* REMARK
 * Tujuan       : Rute web Simple Stock: login, layar utama, master (item, supplier), dokumen stok, laporan.
 * Hak akses    : semua di grup auth; Item create/ubah, master Satuan, Opening / Adjustment / laporan penyesuaian hanya Supervisor.
 * Dipakai oleh : bootstrap/app.php.
 * Status       : Aktif.
 */

use App\Http\Controllers\AcAreaController;
use App\Http\Controllers\AcPlanController;
use App\Http\Controllers\AcRoomController;
use App\Http\Controllers\AcScheduleController;
use App\Http\Controllers\AcUnitController;
use App\Http\Controllers\AcVendorController;
use App\Http\Controllers\AcVisitController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HpController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipientController;
use App\Http\Controllers\ReorderOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SampleDataController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockDocumentController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleDocController;
use App\Http\Controllers\VehicleOdometerController;
use App\Http\Controllers\VehicleServiceController;
use App\Http\Controllers\VehicleTypeController;
use Illuminate\Support\Facades\Route;

// Pilihan tampilan HP / Desktop (cookie ui): juga dipakai tamu (popup dan halaman login HP); tanpa login karena hanya menyimpan cookie.
Route::post('ui', [HpController::class, 'setDevice'])->name('ui.set');
// Dibuka lewat GET (muat ulang / ganti mode "situs desktop" peramban / markah setelah ganti tampilan) tidak lagi 405: kembali ke Beranda tanpa mengubah pilihan.
Route::get('ui', fn () => redirect()->route('home'));

// Ukur layar (x-dl::screen-info): publik, murni sisi klien, tanpa data server; dibuka dari HP untuk mengukur layar sungguhan.
Route::view('ukur', 'ukur')->name('ukur');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');

    // Lupa password: tautan reset lewat e-mail (maks 5 permintaan / menit / IP).
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

// Logo perusahaan di kop cetakan: tanpa login karena cetakan tautan publik (doc-share) memuatnya.
Route::get('company/logo', [CompanyController::class, 'logo'])->name('company.logo');

Route::middleware(['auth', 'active', 'module'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    // Layar sementara modul Jays yang belum dibuat (menu GA: Gedung dan AC; HR: Employee dan Recruitment; Purchase: PR, PO, PV).
    Route::view('segera/{name}', 'soon')->whereIn('name', ['gedung', 'ac', 'employee', 'recruitment', 'pr', 'po', 'pv'])->name('soon');
    Route::post('reorder-orders', [ReorderOrderController::class, 'store'])->name('reorder-orders.store');
    // Riwayat Pemesanan (modul ORD): daftar semua status dan detail; Catat Pemesanan dan Tutup Pesanan (dari Beranda) juga milik modul ORD: menulis butuh RW.
    Route::get('reorder-orders', [ReorderOrderController::class, 'index'])->name('reorder-orders.index');
    Route::get('reorder-orders/{order}', [ReorderOrderController::class, 'show'])->name('reorder-orders.show');
    Route::post('reorder-orders/{order}/close', [ReorderOrderController::class, 'close'])->name('reorder-orders.close');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    // Tampilan HP: kelompok modul, modul tanpa tampilan HP dibuka di dalam kerangka HP.
    Route::get('m/group/{parent}', [HpController::class, 'group'])->name('hp.group');
    Route::get('m/open/{code}', [HpController::class, 'open'])->name('hp.open');
    // Cari Barang tampilan HP (modul ITM, dijaga awalan nama rute hp.find): kolom cari/pindai -> kartu barang -> stok per batch FEFO.
    Route::get('m/find', [HpController::class, 'find'])->name('hp.find');
    Route::get('m/find/{product}', [HpController::class, 'product'])->name('hp.find.show');
    // Ping x-dl::idle-logout: permintaan ini memperpanjang sesi; ikut EnsureActive (akun nonaktif ditolak), tanpa kode modul.
    Route::get('session/ping', fn () => response()->noContent())->name('session.ping');
    // HELP: User's Guide (Indonesia) untuk semua yang login; Technical Guide (English) hanya Administrator (grup di bawah).
    Route::view('help', 'help.user-guide')->name('help.user-guide');

    // Keamanan: kelola pengguna, hanya Administrator.
    Route::middleware('administrator')->prefix('users')->controller(UserController::class)->group(function () {
        Route::get('/', 'index')->name('users.index');
        Route::get('create', 'create')->name('users.create');
        Route::post('/', 'store')->name('users.store');
        Route::get('{user}', 'show')->name('users.show');
        Route::put('{user}', 'update')->name('users.update');
        Route::post('{user}/toggle', 'toggle')->name('users.toggle');
    });

    Route::middleware('administrator')->group(function () {
        Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
        Route::get('groups/create', [GroupController::class, 'create'])->name('groups.create');
        Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
        Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
        Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
        Route::post('groups/{group}/toggle', [GroupController::class, 'toggle'])->name('groups.toggle');
        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('sample', [SampleDataController::class, 'index'])->name('sample.index');
        Route::post('sample', [SampleDataController::class, 'refresh'])->name('sample.refresh');
        Route::view('help/technical-guide', 'help.technical-guide')->name('help.technical-guide');
    });

    // Super admin (SSTOCK_SUPER_ADMIN): log aktivitas dan akun UAT. Selain dia = 404.
    Route::middleware('super')->prefix('super')->name('super.')->controller(SuperAdminController::class)->group(function () {
        Route::get('log', 'log')->name('log');
        Route::get('log/unduh', 'download')->name('log.download');
        Route::get('akun', 'accounts')->name('accounts');
        Route::post('akun/{user}/password', 'password')->name('password');
    });

    // Master Barang: semua boleh melihat dan menambah barang baru; ubah parameter dan aktif / nonaktif hanya Supervisor (dicek ProductRequest dan grup di bawah).
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    // Satuan masuk barang: siapa pun yang boleh menulis modul Barang (aturan ubah faktor yang sudah dipakai ada di ItemUnits::save).
    Route::post('products/{product}/units', [ProductController::class, 'saveUnit'])->name('products.units.save');
    Route::middleware('supervisor')->group(function () {
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::get('units/create', [UnitController::class, 'create'])->name('units.create');
        Route::post('units', [UnitController::class, 'store'])->name('units.store');
        Route::get('units/{unit}', [UnitController::class, 'show'])->name('units.show');
        Route::put('units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::post('units/{unit}/toggle', [UnitController::class, 'toggle'])->name('units.toggle');

        // Perusahaan (kop cetakan): satu formulir, hanya Supervisor.
        Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('company', [CompanyController::class, 'update'])->name('company.update');

        foreach (['categories' => [CategoryController::class, 'category'], 'locations' => [LocationController::class, 'location']] as $path => [$controller, $param]) {
            Route::get($path, [$controller, 'index'])->name("{$path}.index");
            Route::get("{$path}/create", [$controller, 'create'])->name("{$path}.create");
            Route::post($path, [$controller, 'store'])->name("{$path}.store");
            Route::get("{$path}/{{$param}}", [$controller, 'show'])->name("{$path}.show");
            Route::put("{$path}/{{$param}}", [$controller, 'update'])->name("{$path}.update");
            Route::post("{$path}/{{$param}}/toggle", [$controller, 'toggle'])->name("{$path}.toggle");
        }
    });
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::post('suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])->name('suppliers.toggle');

    // Master Departemen dan Penerima: Data Entry dan Supervisor sama-sama boleh mengelola.
    foreach (['departments' => [DepartmentController::class, 'department'], 'recipients' => [RecipientController::class, 'recipient']] as $path => [$controller, $param]) {
        Route::get($path, [$controller, 'index'])->name("{$path}.index");
        Route::get("{$path}/create", [$controller, 'create'])->name("{$path}.create");
        Route::post($path, [$controller, 'store'])->name("{$path}.store");
        Route::get("{$path}/{{$param}}", [$controller, 'show'])->name("{$path}.show");
        Route::put("{$path}/{{$param}}", [$controller, 'update'])->name("{$path}.update");
        Route::post("{$path}/{{$param}}/toggle", [$controller, 'toggle'])->name("{$path}.toggle");
    }

    // Dokumen stok: {type} = opening | receiving | issuance | adjustment (hak per jenis dicek di controller / request).
    Route::prefix('docs/{type}')->whereIn('type', ['opening', 'receiving', 'issuance', 'adjustment'])->controller(StockDocumentController::class)->group(function () {
        Route::get('/', 'index')->name('docs.index');
        Route::get('create', 'create')->name('docs.create');
        Route::post('/', 'store')->name('docs.store');
        Route::post('conversion', 'conversion')->name('docs.conversion');
        Route::get('{document}/print', 'print')->name('docs.print');
        Route::get('{document}', 'show')->name('docs.show');
        Route::put('{document}', 'update')->name('docs.update');
    });

    // Stok Opname tampilan HP (modul SO): daftar dan layar hitung satu barang per kartu.
    Route::get('m/counts', [StockCountController::class, 'phoneIndex'])->name('hp.counts');
    Route::get('m/counts/{count}', [StockCountController::class, 'phone'])->name('hp.count');

    // Stok Opname: modul SO (lihat, isi hasil hitung, cetak lembar = akses modul; Data Entry boleh). Mulai, tinjau, setujui, batalkan, buka kunci = Supervisor.
    Route::prefix('stock-counts')->controller(StockCountController::class)->group(function () {
        Route::get('/', 'index')->name('stock-counts.index');
        Route::middleware('supervisor')->group(function () {
            Route::get('create', 'create')->name('stock-counts.create');
            Route::post('/', 'store')->name('stock-counts.store');
            Route::post('schedule', 'schedule')->name('stock-counts.schedule');
            Route::post('{count}/cancel', 'cancel')->name('stock-counts.cancel');
            Route::post('{count}/lock', 'lock')->name('stock-counts.lock');
            Route::post('{count}/unlock', 'unlock')->name('stock-counts.unlock');
            Route::post('{count}/review', 'reviewLines')->name('stock-counts.review');
            Route::post('{count}/refresh', 'refreshMoved')->name('stock-counts.refresh');
            Route::post('{count}/lines/{line}/refresh', 'refreshOne')->whereNumber('line')->name('stock-counts.refresh-line');
            Route::post('{count}/approve', 'approve')->name('stock-counts.approve');
            Route::post('{count}/reject', 'reject')->name('stock-counts.reject');
            Route::get('{count}/report', 'report')->name('stock-counts.report');
        });
        Route::get('{count}', 'show')->name('stock-counts.show');
        Route::get('{count}/sheet', 'sheet')->name('stock-counts.sheet');
        Route::post('{count}/lines', 'saveLines')->name('stock-counts.lines');
        Route::post('{count}/save', 'save')->name('stock-counts.save');
        Route::post('{count}/items', 'addItem')->name('stock-counts.items');
        Route::post('{count}/finish', 'finish')->name('stock-counts.finish');
    });

    // GA Perawatan Aset Kendaraan: Monitoring + Detail + lampiran (GVM, hanya-baca), master Kendaraan (GVK) dan Jenis Kendaraan beserta rencana perawatannya (GVT); hak per modul lewat Grup.
    Route::get('fleet', [FleetController::class, 'index'])->name('fleet.index');
    Route::get('fleet/file/{kind}/{id}', [FleetController::class, 'file'])->whereIn('kind', ['doc', 'odometer'])->whereNumber('id')->name('fleet.file');
    Route::get('fleet/{vehicle}', [FleetController::class, 'show'])->whereNumber('vehicle')->name('fleet.show');
    // Tiga form input: Perpanjang Dokumen (GVD), Catat Servis (GVS), Catat Odometer (GVO, satu-satunya modul sopir); menulis butuh akses RW modulnya.
    Route::get('vehicle-docs/create', [VehicleDocController::class, 'create'])->name('vehicle-docs.create');
    Route::post('vehicle-docs', [VehicleDocController::class, 'store'])->name('vehicle-docs.store');
    Route::get('vehicle-services/create', [VehicleServiceController::class, 'create'])->name('vehicle-services.create');
    Route::post('vehicle-services', [VehicleServiceController::class, 'store'])->name('vehicle-services.store');
    Route::get('vehicle-odometers/create', [VehicleOdometerController::class, 'create'])->name('vehicle-odometers.create');
    Route::post('vehicle-odometers', [VehicleOdometerController::class, 'store'])->name('vehicle-odometers.store');
    Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
    Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
    Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::post('vehicles/{vehicle}/toggle', [VehicleController::class, 'toggle'])->name('vehicles.toggle');
    Route::get('vehicle-types', [VehicleTypeController::class, 'index'])->name('vehicle-types.index');
    Route::get('vehicle-types/create', [VehicleTypeController::class, 'create'])->name('vehicle-types.create');
    Route::post('vehicle-types', [VehicleTypeController::class, 'store'])->name('vehicle-types.store');
    Route::get('vehicle-types/{type}', [VehicleTypeController::class, 'show'])->name('vehicle-types.show');
    Route::put('vehicle-types/{type}', [VehicleTypeController::class, 'update'])->name('vehicle-types.update');
    Route::post('vehicle-types/{type}/toggle', [VehicleTypeController::class, 'toggle'])->name('vehicle-types.toggle');

    // GA Perawatan AC, Penjadwalan (GAS): kalender kapasitas, daftar AC, jadwalkan, susun otomatis, kapasitas harian.
    Route::get('ac-schedule', [AcScheduleController::class, 'index'])->name('ac-schedule.index');
    Route::post('ac-schedule', [AcScheduleController::class, 'store'])->name('ac-schedule.store');
    Route::post('ac-schedule/auto', [AcScheduleController::class, 'auto'])->name('ac-schedule.auto');
    Route::post('ac-schedule/capacity', [AcScheduleController::class, 'capacity'])->name('ac-schedule.capacity');
    // GA Perawatan AC, Denah (GAD): denah per area dengan titik AC; gambar denah hanya lewat controller.
    Route::get('ac-plan', [AcPlanController::class, 'index'])->name('ac-plan.index');
    Route::get('ac-plan/image/{area}', [AcPlanController::class, 'image'])->whereNumber('area')->name('ac-plan.image');
    // GA Perawatan AC, Kunjungan (GAV): daftar, layar satu pesanan, tahap berurutan, foto bukti, Berita Acara cetak.
    Route::get('ac-visits', [AcVisitController::class, 'index'])->name('ac-visits.index');
    Route::get('ac-visits/file/{line}/{kind}', [AcVisitController::class, 'file'])->whereNumber('line')->whereIn('kind', ['before', 'after'])->name('ac-visits.file');
    Route::get('ac-visits/{order}', [AcVisitController::class, 'show'])->whereNumber('order')->name('ac-visits.show');
    Route::get('ac-visits/{order}/print', [AcVisitController::class, 'print'])->whereNumber('order')->name('ac-visits.print');
    foreach (['send', 'confirm', 'complete', 'reschedule', 'cancel', 'photo'] as $action) {
        Route::post("ac-visits/{order}/{$action}", [AcVisitController::class, $action])->whereNumber('order')->name("ac-visits.{$action}");
    }
    // GA Perawatan AC, master: Unit AC (GAK), Area dan Gedung (GAA, termasuk gambar denah), Ruang (GAR), Vendor (GAE); hak per modul lewat Grup.
    foreach (['ac-units' => [AcUnitController::class, 'unit'], 'ac-areas' => [AcAreaController::class, 'area'], 'ac-rooms' => [AcRoomController::class, 'room'], 'ac-vendors' => [AcVendorController::class, 'vendor']] as $path => [$controller, $param]) {
        Route::get($path, [$controller, 'index'])->name("{$path}.index");
        Route::get("{$path}/create", [$controller, 'create'])->name("{$path}.create");
        Route::post($path, [$controller, 'store'])->name("{$path}.store");
        Route::get("{$path}/{{$param}}", [$controller, 'show'])->whereNumber($param)->name("{$path}.show");
        Route::put("{$path}/{{$param}}", [$controller, 'update'])->whereNumber($param)->name("{$path}.update");
        Route::post("{$path}/{{$param}}/toggle", [$controller, 'toggle'])->whereNumber($param)->name("{$path}.toggle");
    }
    Route::get('ac-areas/{area}/plan', [AcAreaController::class, 'plan'])->whereNumber('area')->name('ac-areas.plan');

    Route::prefix('reports')->controller(ReportController::class)->group(function () {
        Route::get('stock-card', 'stockCard')->name('reports.stock-card');
        Route::get('summary', 'summary')->name('reports.summary');
        Route::get('expiry', 'expiry')->name('reports.expiry');
        Route::get('receiving', 'receiving')->name('reports.receiving');
        Route::get('issuance', 'issuance')->name('reports.issuance');
        Route::get('adjustment', 'adjustment')->middleware('supervisor')->name('reports.adjustment');
    });
});

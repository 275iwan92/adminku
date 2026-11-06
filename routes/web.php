<?php

/* REMARK
 * Tujuan       : Rute web Simple Stock: login, layar utama, master (item, supplier), dokumen stok, laporan.
 * Hak akses    : semua di grup auth; Item create/ubah, master Satuan, Opening / Adjustment / laporan penyesuaian hanya Supervisor.
 * Dipakai oleh : bootstrap/app.php.
 * Status       : Aktif.
 */

use App\Http\Controllers\AcAreaController;
use App\Http\Controllers\AcCareController;
use App\Http\Controllers\AcRoomController;
use App\Http\Controllers\AcScheduleController;
use App\Http\Controllers\AcUnitController;
use App\Http\Controllers\AcVendorController;
use App\Http\Controllers\AcVisitController;
use App\Http\Controllers\AgentReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\CashAdjustmentController;
use App\Http\Controllers\CashCardController;
use App\Http\Controllers\CashInController;
use App\Http\Controllers\CashOutController;
use App\Http\Controllers\CashPurposeController;
use App\Http\Controllers\CashReportController;
use App\Http\Controllers\CashSourceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\DeliveryTripController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\DriverTripController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\GaSettingsController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HpController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OpeningController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseDecisionController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReceiptController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\RecipientController;
use App\Http\Controllers\RecruitStepController;
use App\Http\Controllers\ReorderOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SampleDataController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockDocumentController;
use App\Http\Controllers\StockHomeController;
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

// Laporan agen Python "More details" (Perangkat Saya): publik tanpa CSRF, dijaga token sekali pakai buatan pengguna yang sudah setuju (lihat AgentReportController).
Route::post('agent/report/{token}', [AgentReportController::class, 'store'])->middleware('throttle:10,1')->name('agent.report');

// Layar sopir (Pengiriman): publik tanpa login, dijaga token rahasia per trip; sopir menandai alamat yang sudah tiba (lihat DriverTripController).
Route::middleware('throttle:60,1')->prefix('d/{token}')->whereAlphaNumeric('token')->group(function () {
    Route::get('/', [DriverTripController::class, 'show'])->name('driver.show');
    Route::post('stop/{stop}/arrive', [DriverTripController::class, 'arrive'])->whereNumber('stop')->name('driver.arrive');
    Route::post('stop/{stop}/undo', [DriverTripController::class, 'undo'])->whereNumber('stop')->name('driver.undo');
    Route::post('stop/{stop}/scan', [DriverTripController::class, 'scan'])->whereNumber('stop')->name('driver.scan');
    Route::get('manifest.webmanifest', [DriverTripController::class, 'manifest'])->name('driver.manifest');
});
// Posisi GPS sopir: batas 6 kiriman per menit per perangkat (layar sopir mengirim tiap >= 15 dtk).
Route::middleware('throttle:6,1')->prefix('d/{token}')->whereAlphaNumeric('token')->group(function () {
    Route::post('position', [DriverTripController::class, 'position'])->name('driver.position');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    // Token formulir segar untuk halaman masuk yang dibiarkan terbuka (lihat partials/csrf-keepalive); hanya token, tanpa data.
    Route::get('login/token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'))->name('login.token');

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
    // Beranda Stok (modul STB): isi Beranda lama (pengingat opname, angka stok, titik pesan ulang, kedaluwarsa); Beranda global ada di route home.
    Route::get('stok', StockHomeController::class)->name('stock.home');
    // Layar pembuka kelompok menu / modul (induk menu diklik): Data Master, Transaksi, Laporan, GA, HR, Purchase; hak dicek OpeningController.
    Route::get('buka/{section}', OpeningController::class)->whereIn('section', ['master', 'transaksi', 'laporan', 'ga', 'hr', 'purchase', 'kas'])->name('opening');
    // Layar sementara modul Jays yang belum dibuat (menu GA: Gedung; HR: Employee dan Recruitment; Purchase: PR, PO, PV).
    Route::view('segera/{name}', 'soon')->whereIn('name', ['gedung'])->name('soon');
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
    // Perangkat Saya (menu profil): sesi login milik sendiri, semua peran, rute bebas (tanpa kode modul / hak Grup).
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    // Uji kecepatan ke server ini (unduh: byte acak; unggah: badan permintaan dibuang), dipakai speedometer di layar Perangkat Saya.
    Route::get('devices/speed', [DeviceController::class, 'speedDown'])->name('devices.speed-down');
    Route::post('devices/speed', [DeviceController::class, 'speedUp'])->name('devices.speed-up');
    // More details: layar rincian + persetujuan; POST agent = pengguna setuju, token sekali pakai dibuat dan skrip Python diunduh.
    Route::get('devices/details', [DeviceController::class, 'details'])->name('devices.details');
    Route::get('devices/helper', [DeviceController::class, 'helper'])->name('devices.helper');
    Route::get('devices/status', [DeviceController::class, 'status'])->name('devices.status');
    Route::post('devices/agent', [DeviceController::class, 'agent'])->name('devices.agent');
    Route::post('devices/others', [DeviceController::class, 'destroyOthers'])->name('devices.destroy-others');
    Route::post('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

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
        Route::post('sample/hr', [SampleDataController::class, 'resetHr'])->name('sample.hr');
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

        // Perusahaan (kop cetakan): satu formulir, hanya Administrator.
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
            Route::middleware('approver')->group(function () {
                Route::post('{count}/approve', 'approve')->name('stock-counts.approve');
                Route::post('{count}/reject', 'reject')->name('stock-counts.reject');
            });
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
    Route::get('fleet/setup', [FleetController::class, 'setup'])->name('fleet.setup');
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

    // HR Karyawan (HRE): kepegawaian tanpa gaji; tidak dihapus, hanya dinonaktifkan dengan alasan. Bagian memakai layar Departemen (departments.*).
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::get('employees/{employee}/file/{kind}', [EmployeeController::class, 'file'])->whereNumber('employee')->whereIn('kind', ['photo', 'ktp'])->name('employees.file');

    // HR Kedisiplinan (HRD): penerbitan SP 1 / SP 2 / SP 3 / PHK dengan nomor surat otomatis dan cetak; tidak diubah setelah terbit, hanya dicabut.
    Route::get('disciplines', [DisciplineController::class, 'index'])->name('disciplines.index');
    Route::get('disciplines/create', [DisciplineController::class, 'create'])->name('disciplines.create');
    Route::post('disciplines', [DisciplineController::class, 'store'])->name('disciplines.store');
    Route::get('disciplines/{discipline}', [DisciplineController::class, 'show'])->whereNumber('discipline')->name('disciplines.show');
    Route::get('disciplines/{discipline}/print', [DisciplineController::class, 'print'])->whereNumber('discipline')->name('disciplines.print');
    Route::post('disciplines/{discipline}/revoke', [DisciplineController::class, 'revoke'])->whereNumber('discipline')->name('disciplines.revoke');

    // HR Rekrutmen (HRR): papan kanban + daftar kandidat (kunci NIK), pindah tahap bebas; Setup = urutan tahap (recruit-steps.*, satu-satunya tombol Setup).
    Route::get('candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::get('candidates/create', [CandidateController::class, 'create'])->name('candidates.create');
    Route::post('candidates', [CandidateController::class, 'store'])->name('candidates.store');
    Route::post('candidates/move', [CandidateController::class, 'move'])->name('candidates.move');
    Route::post('candidates/{candidate}/result', [CandidateController::class, 'result'])->whereNumber('candidate')->name('candidates.result');
    Route::get('candidates/{candidate}', [CandidateController::class, 'show'])->whereNumber('candidate')->name('candidates.show');
    Route::put('candidates/{candidate}', [CandidateController::class, 'update'])->whereNumber('candidate')->name('candidates.update');
    Route::get('candidates/{candidate}/file/{kind}', [CandidateController::class, 'file'])->whereNumber('candidate')->whereIn('kind', ['ktp', 'cv', 'photo'])->name('candidates.file');
    // Purchase, Permintaan Pembelian (PUP): daftar, form satu PP banyak barang, siklus lewat PUT (save, confirm, approve, reject, cancel, delete, duplicate).
    Route::get('pr', [PurchaseRequestController::class, 'index'])->name('pr.index');
    Route::get('pr/create', [PurchaseRequestController::class, 'create'])->name('pr.create');
    Route::post('pr', [PurchaseRequestController::class, 'store'])->name('pr.store');
    Route::get('pr/{pr}', [PurchaseRequestController::class, 'show'])->whereNumber('pr')->name('pr.show');
    Route::get('pr/{pr}/print', [PurchaseRequestController::class, 'print'])->whereNumber('pr')->name('pr.print');
    Route::put('pr/{pr}', [PurchaseRequestController::class, 'update'])->whereNumber('pr')->name('pr.update');
    // Purchase, Keputusan Pesanan (PUK): layar kerja satu barang (Proses = GET), Buat PO menambahkan ke draf PO pemasok.
    Route::get('keputusan-pesanan', [PurchaseDecisionController::class, 'index'])->name('po-decision.index');
    Route::post('keputusan-pesanan', [PurchaseDecisionController::class, 'make'])->name('po-decision.make');
    // Purchase, Pesanan Pembelian (PUO): daftar, form satu pemasok banyak barang, siklus lewat PUT, cetak setelah Disetujui.
    Route::get('po', [PurchaseOrderController::class, 'index'])->name('po.index');
    Route::get('po/create', [PurchaseOrderController::class, 'create'])->name('po.create');
    Route::post('po', [PurchaseOrderController::class, 'store'])->name('po.store');
    Route::get('po/{po}', [PurchaseOrderController::class, 'show'])->whereNumber('po')->name('po.show');
    Route::put('po/{po}', [PurchaseOrderController::class, 'update'])->whereNumber('po')->name('po.update');
    Route::get('po/{po}/print', [PurchaseOrderController::class, 'print'])->whereNumber('po')->name('po.print');
    // Pengiriman (PGK): planner trip satu kendaraan + satu crew; Surat Jalan (SJL): daftar Pengeluaran Barang sebagai surat jalan.
    Route::get('trips', [DeliveryTripController::class, 'index'])->name('trips.index');
    Route::get('trips/create', [DeliveryTripController::class, 'create'])->name('trips.create');
    Route::post('trips', [DeliveryTripController::class, 'store'])->name('trips.store');
    Route::get('trips/{trip}', [DeliveryTripController::class, 'show'])->whereNumber('trip')->name('trips.show');
    Route::put('trips/{trip}', [DeliveryTripController::class, 'update'])->whereNumber('trip')->name('trips.update');
    Route::get('trips/{trip}/position', [DeliveryTripController::class, 'position'])->whereNumber('trip')->name('trips.position');
    Route::get('delivery-notes', [DeliveryNoteController::class, 'index'])->name('delivery-notes.index');
    // Purchase, Penerimaan Pembelian (PUV): barang datang dari PO; Konfirmasi membuat Penerimaan Barang (RCV) di Stok otomatis.
    Route::get('pv', [PurchaseReceiptController::class, 'index'])->name('pv.index');
    Route::get('pv/create', [PurchaseReceiptController::class, 'create'])->name('pv.create');
    Route::post('pv', [PurchaseReceiptController::class, 'store'])->name('pv.store');
    Route::get('pv/{pv}', [PurchaseReceiptController::class, 'show'])->whereNumber('pv')->name('pv.show');
    Route::get('pv/{pv}/print', [PurchaseReceiptController::class, 'print'])->whereNumber('pv')->name('pv.print');
    Route::put('pv/{pv}', [PurchaseReceiptController::class, 'update'])->whereNumber('pv')->name('pv.update');
    // Kas, Uang Masuk (KSM): daftar, form satu Uang Masuk, siklus lewat PUT (save, confirm, approve, reject, cancel, delete, duplicate).
    Route::get('cash-in', [CashInController::class, 'index'])->name('cash-in.index');
    Route::get('cash-in/create', [CashInController::class, 'create'])->name('cash-in.create');
    Route::post('cash-in', [CashInController::class, 'store'])->name('cash-in.store');
    Route::get('cash-in/{cashIn}', [CashInController::class, 'show'])->whereNumber('cashIn')->name('cash-in.show');
    Route::put('cash-in/{cashIn}', [CashInController::class, 'update'])->whereNumber('cashIn')->name('cash-in.update');
    Route::get('cash-in/{cashIn}/file', [CashInController::class, 'file'])->whereNumber('cashIn')->name('cash-in.file');
    // Kas, Uang Keluar (KSK): wajib ditaut ke satu Uang Masuk (sumber dana); siklus sama Uang Masuk.
    Route::get('cash-out', [CashOutController::class, 'index'])->name('cash-out.index');
    Route::get('cash-out/create', [CashOutController::class, 'create'])->name('cash-out.create');
    Route::post('cash-out', [CashOutController::class, 'store'])->name('cash-out.store');
    Route::get('cash-out/{cashOut}', [CashOutController::class, 'show'])->whereNumber('cashOut')->name('cash-out.show');
    Route::put('cash-out/{cashOut}', [CashOutController::class, 'update'])->whereNumber('cashOut')->name('cash-out.update');
    Route::get('cash-out/{cashOut}/file', [CashOutController::class, 'file'])->whereNumber('cashOut')->name('cash-out.file');
    // Kas, master Kas / Rekening (KSR): dibuka lewat tombol Setup di Uang Masuk / Uang Keluar.
    Route::get('cash-purposes', [CashPurposeController::class, 'index'])->name('cash-purposes.index');
    Route::get('cash-purposes/create', [CashPurposeController::class, 'create'])->name('cash-purposes.create');
    Route::post('cash-purposes', [CashPurposeController::class, 'store'])->name('cash-purposes.store');
    Route::get('cash-purposes/{purpose}', [CashPurposeController::class, 'show'])->whereNumber('purpose')->name('cash-purposes.show');
    Route::put('cash-purposes/{purpose}', [CashPurposeController::class, 'update'])->whereNumber('purpose')->name('cash-purposes.update');
    Route::get('cash-sources', [CashSourceController::class, 'index'])->name('cash-sources.index');
    Route::get('cash-sources/create', [CashSourceController::class, 'create'])->name('cash-sources.create');
    Route::post('cash-sources', [CashSourceController::class, 'store'])->name('cash-sources.store');
    Route::get('cash-sources/{source}', [CashSourceController::class, 'show'])->whereNumber('source')->name('cash-sources.show');
    Route::put('cash-sources/{source}', [CashSourceController::class, 'update'])->whereNumber('source')->name('cash-sources.update');
    Route::post('cash-in/bank-account', [CashAccountController::class, 'storeBank'])->name('cash-in.bank-account');
    Route::post('cash-out/bank-account', [CashAccountController::class, 'storeBank'])->name('cash-out.bank-account');
    Route::get('cash-accounts/create', [CashAccountController::class, 'create'])->name('cash-accounts.create');
    Route::post('cash-accounts', [CashAccountController::class, 'store'])->name('cash-accounts.store');
    Route::get('cash-accounts', [CashAccountController::class, 'index'])->name('cash-accounts.index');
    Route::get('cash-accounts/{account}', [CashAccountController::class, 'show'])->whereNumber('account')->name('cash-accounts.show');
    Route::put('cash-accounts/{account}', [CashAccountController::class, 'update'])->whereNumber('account')->name('cash-accounts.update');
    Route::post('cash-accounts/{account}/toggle', [CashAccountController::class, 'toggle'])->whereNumber('account')->name('cash-accounts.toggle');
    Route::get('cash-card', [CashCardController::class, 'index'])->name('cash-card.index');
    Route::get('recruit-steps', [RecruitStepController::class, 'index'])->name('recruit-steps.index');
    Route::get('recruit-steps/create', [RecruitStepController::class, 'create'])->name('recruit-steps.create');
    Route::post('recruit-steps', [RecruitStepController::class, 'store'])->name('recruit-steps.store');
    Route::get('recruit-steps/{step}', [RecruitStepController::class, 'show'])->name('recruit-steps.show');
    Route::put('recruit-steps/{step}', [RecruitStepController::class, 'update'])->name('recruit-steps.update');

    Route::get('cash-adjust', [CashAdjustmentController::class, 'index'])->name('cash-adjust.index');
    Route::get('cash-adjust/create', [CashAdjustmentController::class, 'create'])->name('cash-adjust.create');
    Route::post('cash-adjust', [CashAdjustmentController::class, 'store'])->name('cash-adjust.store');
    Route::get('cash-adjust/{adjustment}', [CashAdjustmentController::class, 'show'])->whereNumber('adjustment')->name('cash-adjust.show');
    Route::put('cash-adjust/{adjustment}', [CashAdjustmentController::class, 'update'])->whereNumber('adjustment')->name('cash-adjust.update');
    // GA Perawatan AC (GAM): satu butir menu; panel Monitoring dan hub Setup. Layar lain dibuka lewat tombol di panel (hak per modul tetap).
    Route::get('ac-care', [AcCareController::class, 'index'])->name('ac-care.index');
    Route::get('ac-care/{unit}', [AcCareController::class, 'show'])->whereNumber('unit')->name('ac-care.show');
    Route::get('ac-setup', [AcCareController::class, 'setup'])->name('ac-setup.index');
    // GA Perawatan AC, Penjadwalan (GAS): kalender kapasitas, daftar AC, jadwalkan, susun otomatis, kapasitas harian.
    Route::get('ac-schedule', [AcScheduleController::class, 'index'])->name('ac-schedule.index');
    Route::post('ac-schedule', [AcScheduleController::class, 'store'])->name('ac-schedule.store');
    Route::post('ac-schedule/auto', [AcScheduleController::class, 'auto'])->name('ac-schedule.auto');
    Route::post('ac-schedule/capacity', [AcScheduleController::class, 'capacity'])->name('ac-schedule.capacity');
    // GA Perawatan AC, Kunjungan (GAV): daftar, layar satu pesanan, tahap berurutan, foto bukti, Berita Acara cetak.
    Route::get('ac-visits', [AcVisitController::class, 'index'])->name('ac-visits.index');
    Route::get('ac-visits/file/{line}/{kind}', [AcVisitController::class, 'file'])->whereNumber('line')->whereIn('kind', ['before', 'after'])->name('ac-visits.file');
    Route::get('ac-visits/{order}', [AcVisitController::class, 'show'])->whereNumber('order')->name('ac-visits.show');
    Route::get('ac-visits/{order}/print', [AcVisitController::class, 'print'])->whereNumber('order')->name('ac-visits.print');
    foreach (['send', 'confirm', 'complete', 'result', 'reschedule', 'cancel', 'photo'] as $action) {
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
    // GA Pengaturan (GAP): ambang kendaraan dan aturan Perawatan AC (sys_constant ga.*); hanya Supervisor ke atas, hak lewat Grup.
    Route::get('ga-settings', [GaSettingsController::class, 'edit'])->name('ga-settings.edit');
    Route::put('ga-settings', [GaSettingsController::class, 'update'])->name('ga-settings.update');

    Route::prefix('reports')->controller(ReportController::class)->group(function () {
        Route::get('stock-card', 'stockCard')->name('reports.stock-card');
        Route::get('summary', 'summary')->name('reports.summary');
        Route::get('expiry', 'expiry')->name('reports.expiry');
        Route::get('receiving', 'receiving')->name('reports.receiving');
        Route::get('issuance', 'issuance')->name('reports.issuance');
        Route::get('adjustment', 'adjustment')->middleware('supervisor')->name('reports.adjustment');
    });
    Route::prefix('reports')->controller(CashReportController::class)->group(function () {
        Route::get('cash-flow', 'cashFlow')->name('reports.cash-flow');
        Route::get('cash-receipts', 'receipts')->name('reports.cash-receipts');
        Route::get('cash-disbursements', 'disbursements')->name('reports.cash-disbursements');
    });
});

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/* REMARK
 * Tujuan       : Satu alamat di trip: antar (ant) atau ambil (amb), pelanggan, alamat, menit tempuh dari titik sebelumnya, barang yang diketik (items JSON) atau surat jalan yang ditautkan (docs, hanya untuk antar).
 * Sumber hulu  : delivery_stop, delivery_stop_doc, rm_stock (Pengeluaran Barang).
 * Dipakai oleh : DeliveryTrip, DeliveryTripController.
 * Status       : Aktif.
 */
#[Fillable(['trip_id', 'seq', 'kind', 'customer', 'address', 'lat', 'lng', 'travel_min', 'arrived_at', 'scanned_at', 'scan_code', 'scan_ok', 'items'])]
class DeliveryStop extends Model
{
    protected $table = 'delivery_stop';

    protected function casts(): array
    {
        return ['items' => 'array', 'arrived_at' => 'datetime', 'scanned_at' => 'datetime', 'scan_ok' => 'boolean'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(DeliveryTrip::class, 'trip_id');
    }

    // Kode yang dianggap cocok saat dipindai di alamat ini: nomor surat jalan / PV yang tertaut, atau kode alamat (nomor trip-urutan) untuk barang ketikan.
    public function expectedCodes(): array
    {
        return $this->docs->pluck('doc_no')->push($this->trip->number().'-'.$this->seq)->map(fn ($c) => mb_strtoupper(trim($c)))->all();
    }

    public function docs(): BelongsToMany
    {
        return $this->belongsToMany(StockDocument::class, 'delivery_stop_doc', 'stop_id', 'stock_document_id');
    }
}

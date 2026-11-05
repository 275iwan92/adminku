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
#[Fillable(['trip_id', 'seq', 'kind', 'customer', 'address', 'lat', 'lng', 'travel_min', 'items'])]
class DeliveryStop extends Model
{
    protected $table = 'delivery_stop';

    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(DeliveryTrip::class, 'trip_id');
    }

    public function docs(): BelongsToMany
    {
        return $this->belongsToMany(StockDocument::class, 'delivery_stop_doc', 'stop_id', 'stock_document_id');
    }
}

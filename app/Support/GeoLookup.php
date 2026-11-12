<?php

namespace App\Support;

use Devlib2\Lookup;

/* REMARK
 * Tujuan       : Lookup DevLib "geo_area" (kotak cari wilayah, ketik min. 2 huruf: kecamatan / kota / kode pos); nilai terpilih tampil sebagai label penuh + kode pos; kode pos disimpan server dari wilayah terpilih.
 * Sumber hulu  : GeoArea (tabel geo_*). Pola sama dengan lookup geo_area di tmerp.
 * Output       : Lookup::define('geo_area').
 * Dipakai oleh : AppServiceProvider::boot, view suppliers.form.
 * Status       : Aktif.
 */
final class GeoLookup
{
    public static function register(): void
    {
        Lookup::define('geo_area', [
            'key' => 'area', 'label' => 'display', 'source' => 'geo_country > geo_region > geo_city > geo_district > geo_village',
            'columns' => ['full_label' => 'Region', 'postal_code' => 'Postcode'],
            'search' => fn (string $q) => GeoArea::search($q),
            'find' => fn ($v) => GeoArea::find((string) $v),
        ]);
    }
}

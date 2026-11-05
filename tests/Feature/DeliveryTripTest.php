<?php

namespace Tests\Feature;

use App\Enums\StockDocumentType;
use App\Models\Category;
use App\Models\DeliveryTrip;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\StockDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Pengiriman (PGK) dan Surat Jalan (SJL): draf lalu Tugaskan (nomor lahir, trip terkunci), alamat tanpa barang ditolak saat Tugaskan, surat jalan harus Pengeluaran Barang terkonfirmasi dan hanya di satu alamat,
 *                Batalkan hanya Supervisor (surat jalan bebas lagi), Hapus hanya draf, grid Surat Jalan menampilkan pemakai, dan hak akses modul.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class DeliveryTripTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $boss;

    private Vehicle $van;

    protected function setUp(): void
    {
        parent::setUp();
        $group = UserGroup::create(['code' => 'DLV', 'name' => 'Pengiriman']);
        $group->syncAccess(['PGK' => 'rw', 'SJL' => 'rw', 'ISS' => 'rw']);
        $this->staff = User::factory()->create(['group_id' => $group->id]);
        $this->boss = User::factory()->supervisor()->create(['group_id' => $group->id]);
        $type = VehicleType::create(['code' => 'MOBIL', 'name' => 'Mobil']);
        $this->van = Vehicle::create(['vehicle_type_id' => $type->id, 'plate' => 'B 9123 KX', 'name' => 'Gran Max', 'is_active' => true]);
    }

    private function payload(array $stops, array $extra = []): array
    {
        return $extra + ['trip_date' => '2026-11-06', 'vehicle_id' => $this->van->id, 'driver' => 'Budi', 'assistant' => 'Agus', 'depart_time' => '08:00', 'return_min' => 25, 'stops_json' => json_encode($stops)];
    }

    private function typedStop(string $kind = 'ant', string $customer = 'Toko Maju'): array
    {
        return ['kind' => $kind, 'customer' => $customer, 'address' => 'Jl. Melati 12', 'travel_min' => 15, 'items' => [['Sabun', 10, 'dus', 60]], 'docs' => []];
    }

    private function confirmedIssuance(): StockDocument
    {
        $cat = Category::firstOrCreate(['code' => 'ATK'], ['name' => 'ATK']);
        $batch = $this->makeBatch(['code' => 'KRT', 'name' => 'Kertas A4', 'category_id' => $cat->id, 'unit' => 'pcs', 'unit_price' => 100, 'reorder_point' => 0, 'reorder_qty' => 50, 'lead_time_days' => 3]);
        $docs = app(StockDocuments::class);
        $in = $docs->create(StockDocumentType::Receiving, ['supplier_id' => null, 'ref_no' => 'X'], [['item_id' => $batch->item_id, 'qty' => 100, 'unit' => 'pcs', 'unit_cost' => 100]], $this->boss);
        $docs->confirm($in, $this->boss);
        $out = $docs->create(StockDocumentType::Issuance, ['recipient' => 'Toko Maju'], [['batch_id' => $batch->id, 'qty' => 5]], $this->boss);
        $docs->confirm($out, $this->boss);

        return $out->fresh();
    }

    public function test_draft_then_assign_gives_number_and_locks(): void
    {
        $res = $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([$this->typedStop(), $this->typedStop('amb', 'Bu Sari')]));
        $trip = DeliveryTrip::firstOrFail();
        $res->assertRedirect(route('trips.show', $trip));
        $this->assertNull($trip->doc_no);
        $this->assertSame(2, $trip->stops()->count());

        $this->actingAs($this->staff)->put(route('trips.update', $trip), $this->payload([$this->typedStop()], ['_action' => 'confirm']))->assertRedirect();
        $trip->refresh();
        $this->assertSame('assigned', $trip->status);
        $this->assertMatchesRegularExpression('/^PGK-\d{4}-0001$/', $trip->doc_no);

        $this->actingAs($this->staff)->put(route('trips.update', $trip), $this->payload([$this->typedStop()], ['_action' => 'save']))->assertStatus(422);
    }

    public function test_assign_rejects_empty_trip_and_stop_without_items(): void
    {
        $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([]));
        $trip = DeliveryTrip::firstOrFail();

        $this->actingAs($this->staff)->put(route('trips.update', $trip), $this->payload([], ['_action' => 'confirm']))->assertSessionHasErrors('stops_json');
        $empty = $this->typedStop();
        $empty['items'] = [];
        $this->actingAs($this->staff)->put(route('trips.update', $trip), $this->payload([$empty], ['_action' => 'confirm']))->assertSessionHasErrors('stops_json');
        $this->assertSame('draft', $trip->fresh()->status);
    }

    public function test_delivery_note_only_confirmed_issuance_and_one_stop(): void
    {
        $note = $this->confirmedIssuance();
        $stop = $this->typedStop();
        $stop['docs'] = [$note->id];

        $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([$stop]))->assertRedirect();
        $trip = DeliveryTrip::firstOrFail();
        $this->assertSame([$note->id], $trip->stops()->first()->docs()->pluck('rm_stock.id')->all());

        // sama di dua alamat satu trip: ditolak
        $two = $this->payload([$stop, $stop]);
        $this->actingAs($this->staff)->put(route('trips.update', $trip), $two)->assertSessionHasErrors('stops_json');

        // trip lain tidak boleh memakai surat jalan yang sama
        $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([$stop]))->assertSessionHasErrors('stops_json');

        // grid Surat Jalan menunjukkan pemakainya
        $this->actingAs($this->staff)->get(route('delivery-notes.index'))->assertOk()->assertSee('B 9123 KX');
    }

    public function test_cancel_is_supervisor_only_and_frees_notes(): void
    {
        $note = $this->confirmedIssuance();
        $stop = $this->typedStop();
        $stop['docs'] = [$note->id];
        $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([$stop]));
        $trip = DeliveryTrip::firstOrFail();
        $this->actingAs($this->staff)->put(route('trips.update', $trip), $this->payload([$stop], ['_action' => 'confirm']));

        $this->actingAs($this->staff)->put(route('trips.update', $trip), ['_action' => 'cancel', '_reason' => 'Batal kirim'])->assertForbidden();
        $this->actingAs($this->boss)->put(route('trips.update', $trip), ['_action' => 'cancel', '_reason' => 'Batal kirim'])->assertRedirect();
        $this->assertSame('cancelled', $trip->fresh()->status);
        $this->assertDatabaseCount('delivery_stop_doc', 0);
    }

    public function test_delete_only_draft(): void
    {
        $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([$this->typedStop()]));
        $trip = DeliveryTrip::firstOrFail();
        $this->actingAs($this->staff)->put(route('trips.update', $trip), ['_action' => 'delete'])->assertRedirect(route('trips.index'));
        $this->assertDatabaseCount('delivery_trip', 0);
    }

    public function test_screens_open_and_module_access_is_enforced(): void
    {
        $this->actingAs($this->staff)->get(route('trips.index'))->assertOk();
        $this->actingAs($this->staff)->get(route('trips.create'))->assertOk()->assertSee('Tambah alamat');

        $none = UserGroup::create(['code' => 'NO', 'name' => 'Tanpa']);
        $none->syncAccess(['ISS' => 'rw']);
        $other = User::factory()->create(['group_id' => $none->id]);
        $this->actingAs($other)->get(route('trips.index'))->assertForbidden();
        $this->actingAs($other)->get(route('delivery-notes.index'))->assertForbidden();
    }
}

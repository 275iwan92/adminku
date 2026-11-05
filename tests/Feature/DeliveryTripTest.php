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

    public function test_stop_coordinates_are_saved_and_out_of_range_ignored(): void
    {
        $ok = $this->typedStop() + ['lat' => -6.2, 'lng' => 106.8];
        $bad = $this->typedStop('ant', 'Toko Jauh') + ['lat' => 'abc', 'lng' => 999];
        $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([$ok, $bad]))->assertRedirect();
        $stops = DeliveryTrip::firstOrFail()->stops;
        $this->assertSame(-6.2, (float) $stops[0]->lat);
        $this->assertSame(106.8, (float) $stops[0]->lng);
        $this->assertNull($stops[1]->lat);
        $this->assertNull($stops[1]->lng);
    }

    public function test_driver_link_marks_arrival_and_recalculates_eta(): void
    {
        $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([$this->typedStop('ant', 'Toko Satu'), $this->typedStop('ant', 'Toko Dua')]));
        $trip = DeliveryTrip::firstOrFail();
        $this->get(route('trips.show', $trip))->assertOk();
        $this->actingAs($this->staff)->put(route('trips.update', $trip), $this->payload([$this->typedStop('ant', 'Toko Satu'), $this->typedStop('ant', 'Toko Dua')], ['_action' => 'confirm']));
        $trip->refresh();
        $this->assertSame(40, strlen($trip->token));

        auth()->logout();
        $this->get(route('driver.show', $trip->token))->assertOk()->assertSee('Toko Satu')->assertSee('Perkiraan tiba');
        $first = $trip->stops()->first();
        $this->travelTo(now()->setTime(9, 30));
        $this->post(route('driver.arrive', [$trip->token, $first->id]))->assertRedirect(route('driver.show', $trip->token));
        $this->assertSame('09:30', $first->fresh()->arrived_at->format('H:i'));
        // ETA alamat kedua dihitung dari jam tiba asli: 09:30 + layanan 4 mnt (asisten, 60 kg = 11 x 0,7 = 8) + tempuh 15
        $sched = $trip->fresh()->load('stops')->schedule();
        $second = $trip->stops()->skip(1)->first();
        $this->assertTrue($sched[$first->id]['actual']);
        $this->assertSame(9 * 60 + 30 + 8 + 15, $sched[$second->id]['arrive']);

        $this->post(route('driver.undo', [$trip->token, $first->id]));
        $this->assertNull($first->fresh()->arrived_at);
        $this->get(route('driver.show', 'tokensalah123'))->assertNotFound();
    }

    public function test_driver_link_refuses_cancelled_trip(): void
    {
        $this->actingAs($this->staff)->post(route('trips.store'), $this->payload([$this->typedStop()]));
        $trip = DeliveryTrip::firstOrFail();
        $this->put(route('trips.update', $trip), $this->payload([$this->typedStop()], ['_action' => 'confirm']));
        $token = $trip->fresh()->token;
        $this->actingAs($this->boss)->put(route('trips.update', $trip), ['_action' => 'cancel', '_reason' => 'Salah alamat']);
        auth()->logout();
        $this->get(route('driver.show', $token))->assertStatus(410);
        $this->post(route('driver.arrive', [$token, $trip->stops()->first()->id]))->assertStatus(410);
    }
}

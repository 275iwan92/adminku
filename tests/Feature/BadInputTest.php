<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Masukan buruk tidak boleh berakhir 500: isian dikirim sebagai array (name[]=x), parameter query berbentuk array (?from[]=x), dan ID yang tidak ada / aneh pada rute tulis utama harus menghasilkan 404 / 422 / halaman biasa.
 *                Pemeriksaan penuh semua rute dijalankan dengan skrip fuzz (lihat laporan); tes ini menjaga kasus yang pernah 500.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class BadInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_forms_reject_array_fields_with_a_validation_error_not_a_500(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        foreach (['suppliers', 'departments', 'recipients', 'categories', 'locations', 'units', 'users'] as $path) {
            $this->post(route("{$path}.store"), ['name' => ['a'], 'code' => ['b'], 'username' => ['c']])->assertSessionHasErrors('name');
        }
    }

    public function test_get_screens_ignore_array_query_parameters(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        foreach (['reports.stock-card' => ['batch_id' => ['1']], 'reports.summary' => ['from' => ['x'], 'category_id' => ['x']], 'reports.issuance' => ['who' => ['a']], 'reports.adjustment' => ['status' => ['a']],
            'reports.expiry' => ['category_id' => ['x']], 'home' => ['month' => ['x'], 'open' => ['x']], 'hp.find' => ['q' => ['a']]] as $route => $query) {
            $this->get(route($route, $query))->assertOk();
        }
    }

    public function test_write_routes_with_unknown_ids_answer_404(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->put(route('docs.update', ['receiving', 99999999]), ['_action' => 'confirm'])->assertNotFound();
        $this->postJson(route('stock-counts.lines', 99999999), ['changed' => [['id' => 1]]])->assertNotFound();
        $this->post(route('reorder-orders.close', 99999999))->assertNotFound();
        $this->put(route('products.update', 'abc'), [])->assertNotFound();
    }

    public function test_empty_and_wrong_typed_payloads_answer_422_or_a_redirect(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        $this->post(route('docs.store', 'receiving'), [])->assertSessionHasErrors();
        $this->post(route('docs.store', 'issuance'), ['lines' => 'abc'])->assertSessionHasErrors('lines');
        $this->postJson(route('docs.store', 'adjustment'), ['lines' => [['batch_id' => ['x'], 'counted_qty' => ['1']]]])->assertStatus(422);
        $this->post(route('reorder-orders.store'), ['item_id' => ['1'], 'qty' => ['1']])->assertSessionHasErrors();
    }
}

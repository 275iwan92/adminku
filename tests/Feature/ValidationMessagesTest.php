<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga pesan validasi hasil uji kemudahan: tidak ada kunci mentah ("validation.email"), kata Inggris ("today"), nama isian mentah ("ordered at"), atau awal kalimat huruf kecil.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function firstError(string $key): string
    {
        return session('errors')->getBag('default')->first($key);
    }

    public function test_every_rule_the_app_uses_has_an_indonesian_message(): void
    {
        foreach (['after_or_equal', 'array', 'before_or_equal', 'between.numeric', 'boolean', 'confirmed', 'date', 'dimensions', 'distinct', 'email', 'exists', 'file', 'gt.numeric', 'in', 'integer', 'max.string', 'max.numeric', 'mimetypes', 'min.string', 'min.numeric', 'not_in', 'numeric', 'regex', 'required', 'required_if', 'required_with', 'required_without', 'string', 'unique'] as $rule) {
            $this->assertNotSame("validation.{$rule}", __("validation.{$rule}"), "pesan validasi {$rule} belum ada");
        }
    }

    public function test_an_invalid_user_email_reads_as_a_sentence(): void
    {
        $this->actingAs($this->admin())->post(route('users.store'), ['name' => 'X', 'username' => 'x1', 'email' => 'bukan-email', 'role' => 'data_entry', 'password' => 'abcdefgh'])->assertSessionHasErrors('email');

        $this->assertSame('E-mail harus berupa alamat e-mail yang valid.', $this->firstError('email'));
    }

    public function test_a_future_opening_date_says_today_in_indonesian(): void
    {
        $sup = User::factory()->supervisor()->create();
        $this->actingAs($sup)->post(route('docs.store', 'opening'), ['doc_at' => today()->addDay()->toDateString(), 'lines' => [['batch_id' => 1, 'qty' => 1, 'unit_cost' => 1]]])->assertSessionHasErrors('doc_at');

        $this->assertSame('Tanggal saldo awal tidak boleh setelah hari ini.', $this->firstError('doc_at'));
    }

    public function test_a_future_order_date_uses_the_friendly_field_name(): void
    {
        $this->actingAs(User::factory()->create())->post(route('reorder-orders.store'), ['item_id' => 1, 'supplier_id' => 1, 'qty' => 5, 'ordered_at' => today()->addDay()->toDateString()])->assertSessionHasErrors('ordered_at');

        $this->assertSame('Tanggal pesan tidak boleh setelah hari ini.', $this->firstError('ordered_at'));
    }

    public function test_messages_start_with_a_capital_letter(): void
    {
        $this->actingAs(User::factory()->create())->post(route('departments.store'), ['code' => 'D1', 'name' => ''])->assertSessionHasErrors('name');

        $this->assertSame('Nama wajib diisi.', $this->firstError('name'));
    }

    public function test_an_ordered_quantity_with_too_many_decimals_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('reorder-orders.store'), ['item_id' => 1, 'supplier_id' => 1, 'qty' => '1.2345', 'ordered_at' => today()->toDateString()])->assertSessionHasErrors('qty');

        $this->assertStringContainsString('paling banyak 3 angka desimal', $this->firstError('qty'));
    }
}

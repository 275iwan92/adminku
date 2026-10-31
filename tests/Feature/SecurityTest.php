<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActive;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\Menu;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga menu Keamanan: peran Administrator, kelola pengguna dan grup, pengaturan modul per grup, serta penjagaan akses modul lewat URL.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function codes(User $user): array
    {
        return collect(Menu::modules($user))->flatMap(fn ($m) => collect($m['children'])->pluck('code'))->all();
    }

    public function test_only_administrators_see_and_open_the_security_menu(): void
    {
        $admin = User::factory()->administrator()->create();
        $sup = User::factory()->supervisor()->create();
        $entry = User::factory()->create();

        $this->assertEqualsCanonicalizing(array_keys(Modules::all()), $this->codes($admin));
        $this->assertNotContains('USR', $this->codes($sup));
        $this->assertContains('OPN', $this->codes($sup));
        $this->assertNotContains('OPN', $this->codes($entry));
        $this->assertContains('RCV', $this->codes($entry));

        foreach (['users.index', 'groups.index', 'modules.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
            $this->actingAs($sup)->get(route($route))->assertForbidden();
            $this->actingAs($entry)->get(route($route))->assertForbidden();
        }
        $this->assertTrue($admin->isSupervisor());   // administrator juga punya hak Supervisor
    }

    public function test_group_modules_limit_menu_and_direct_urls(): void
    {
        $group = UserGroup::create(['code' => 'GUDANG', 'name' => 'Gudang']);
        $group->syncAccess(['ISS' => 'rw', 'OPN' => 'rw']);   // OPN butuh peran Supervisor, jadi tetap tertutup bagi Data Entry
        $entry = User::factory()->create(['group_id' => $group->id]);

        $this->assertSame(['ISS'], $this->codes($entry));
        $this->actingAs($entry)->get(route('docs.index', 'issuance'))->assertOk();
        $this->get(route('docs.index', 'receiving'))->assertForbidden();
        $this->get(route('products.index'))->assertForbidden();
        $this->get(route('reports.summary'))->assertForbidden();
        $this->get(route('docs.index', 'opening'))->assertForbidden();
        $this->get(route('home'))->assertOk();
    }

    public function test_read_only_access_opens_screens_but_blocks_writes(): void
    {
        $group = UserGroup::create(['code' => 'VIEW', 'name' => 'Viewer']);
        $group->syncAccess(['ITM' => 'ro', 'RCV' => 'rw']);
        $entry = User::factory()->create(['group_id' => $group->id]);
        $this->actingAs($entry);

        $this->assertSame(['ro', 'rw', null], [$entry->moduleAccess('ITM'), $entry->moduleAccess('RCV'), $entry->moduleAccess('ISS')]);
        $this->get(route('products.index'))->assertOk();                                 // RO: boleh lihat
        $this->get(route('docs.create', 'receiving'))->assertOk();                       // RW: boleh buka form
        $product = Item::register(['name' => 'Item X', 'category_id' => Category::create(['code' => 'ATK', 'name' => 'ATK'])->id, 'unit' => 'pcs', 'unit_price' => 1], 'X1');
        $this->postJson(route('products.toggle', $product))->assertForbidden();                // RO: perubahan ditolak
        $this->post(route('docs.store', 'issuance'), [])->assertForbidden();             // ISS tanpa akses
    }

    public function test_administrator_manages_users_and_groups(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $this->post(route('groups.store'), ['code' => 'qa', 'name' => 'QA', 'modules' => ['ITM' => ['access' => 'rw'], 'R-SUM' => ['access' => 'ro'], 'SUP' => ['access' => 'none']]])->assertRedirect();
        $group = UserGroup::where('code', 'QA')->firstOrFail();
        $this->assertSame(['ITM' => 'rw', 'R-SUM' => 'ro'], $group->moduleAccess());
        $this->put(route('groups.update', $group), ['name' => 'QA Team', 'modules' => ['ITM' => ['access' => 'ro']]])->assertRedirect();
        $this->assertSame(['ITM' => 'ro'], $group->fresh()->moduleAccess());
        $this->post(route('groups.store'), ['code' => 'bad', 'name' => 'X', 'modules' => ['ITM' => ['access' => 'xx']]])->assertSessionHasErrors('modules.ITM.access');

        $this->post(route('users.store'), ['name' => 'Budi', 'username' => 'Budi.QA', 'email' => 'b@x.test', 'role' => 'data_entry', 'group_id' => $group->id, 'password' => 'rahasia123'])->assertRedirect();
        $user = User::where('username', 'budi.qa')->firstOrFail();
        $this->assertSame([$group->id, 'Data Entry'], [$user->group_id, $user->roleLabel()]);
        $this->assertSame(['ITM'], $this->codes($user));

        $this->post(route('users.store'), ['name' => 'Pendek', 'username' => 'pendek', 'email' => 'p@x.test', 'role' => 'data_entry', 'password' => 'abc'])->assertSessionHasErrors('password');
        $this->put(route('users.update', $user), ['name' => 'Budi QA', 'username' => 'budi.qa', 'email' => 'b@x.test', 'role' => 'supervisor', 'group_id' => $group->id, 'password' => ''])->assertRedirect();
        $this->assertSame('Supervisor', $user->fresh()->roleLabel());
        $this->assertTrue(password_verify('rahasia123', $user->fresh()->password));   // password kosong = tidak berubah
    }

    public function test_administrator_cannot_lock_themselves_out(): void
    {
        $admin = User::factory()->administrator()->create(['username' => 'boss']);
        $this->actingAs($admin);

        $this->put(route('users.update', $admin), ['_action' => 'deactivate'])->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->is_active);
        $this->put(route('users.update', $admin), ['name' => $admin->name, 'username' => 'boss', 'email' => $admin->email, 'role' => 'data_entry', 'password' => ''])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdministrator());
        $this->postJson(route('users.toggle', $admin))->assertStatus(422);
    }

    public function test_user_deactivated_during_an_active_session_is_rejected_and_loses_sessions(): void
    {
        $admin = User::factory()->administrator()->create();
        $entry = User::factory()->create(['remember_token' => 'abc']);
        DB::table('sessions')->insert([['id' => 's1', 'user_id' => $entry->id, 'payload' => '', 'last_activity' => time()], ['id' => 's2', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]]);

        $this->actingAs($entry)->get(route('home'))->assertOk();
        $this->actingAs($admin)->postJson(route('users.toggle', $entry))->assertOk()->assertJson(['active' => false]);
        $this->assertNull($entry->fresh()->remember_token);
        $this->assertSame(['s2'], DB::table('sessions')->pluck('id')->all());   // hanya sesi pengguna itu yang dicabut

        $this->actingAs($entry->fresh())->get(route('home'))->assertRedirect(route('login'))->assertSessionHas('error', 'Akun Anda dinonaktifkan.');
        $this->assertGuest();
        $this->actingAs($entry->fresh())->getJson(route('home'))->assertForbidden()->assertJson(['message' => 'Akun Anda dinonaktifkan.']);
        $this->assertGuest();
    }

    public function test_deactivate_action_from_the_form_also_revokes_sessions(): void
    {
        $admin = User::factory()->administrator()->create();
        $entry = User::factory()->create();
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $entry->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->put(route('users.update', $entry), ['_action' => 'deactivate'])->assertRedirect();
        $this->assertFalse($entry->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $entry->id)->count());
    }

    public function test_the_last_active_administrator_cannot_be_deactivated(): void
    {
        $first = User::factory()->administrator()->create();
        $second = User::factory()->administrator()->create();

        $this->actingAs($first)->postJson(route('users.toggle', $second))->assertOk();   // masih ada $first
        $this->assertTrue($first->fresh()->is_active);

        // Pelaku yang bukan administrator aktif (middleware dilewati) tetap tidak bisa menonaktifkan administrator aktif terakhir.
        $this->withoutMiddleware(EnsureActive::class)->actingAs($second->fresh())->postJson(route('users.toggle', $first))->assertStatus(422);
        $this->assertTrue($first->fresh()->is_active);
    }
}

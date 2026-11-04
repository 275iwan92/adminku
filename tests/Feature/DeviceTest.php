<?php

namespace Tests\Feature;

use App\Http\Controllers\DeviceController;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/* REMARK
 * Tujuan       : Menjaga Perangkat Saya: sesi ini tercatat dan ditandai, perangkat lain bisa dikeluarkan satu per satu atau sekaligus, milik pengguna lain tidak tersentuh, semua peran boleh membuka.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */
class DeviceTest extends TestCase
{
    use RefreshDatabase;

    private function other(User $user, string $sid = 'sid-lain'): UserDevice
    {
        return UserDevice::create(['user_id' => $user->id, 'session_id' => $sid, 'ip' => '10.0.0.9', 'agent' => 'Mozilla/5.0 (Windows NT 10.0) Firefox/120.0', 'last_active_at' => now(), 'created_at' => now()]);
    }

    public function test_every_role_sees_this_device_marked_and_the_other_one(): void
    {
        foreach (['data_entry', 'supervisor', 'administrator'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->other($user, "sid-$role");

            $this->actingAs($user)->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537')->get(route('devices.index'))
                ->assertOk()->assertSee('Perangkat ini', false)->assertSee('Firefox di Windows', false)->assertSee('10.0.0.9', false);
        }
    }

    public function test_other_device_can_be_kicked_but_not_this_one_or_someone_elses(): void
    {
        $me = User::factory()->create();
        $stranger = User::factory()->create();
        $mine = $this->other($me);
        $theirs = $this->other($stranger, 'sid-orang');

        $this->actingAs($me);
        $self = $this->other($me, str_repeat('s', 40));

        $this->post(route('devices.destroy', $theirs))->assertForbidden();
        // Sesi ini sendiri tidak boleh dikeluarkan lewat tombol "perangkat lain" (tes HTTP tidak membawa cookie sesi, jadi pengendali dipanggil langsung).
        $request = Request::create('/');
        $request->setLaravelSession(tap(app('session')->driver())->setId($self->session_id));
        $request->setUserResolver(fn () => $me);
        try {
            app(DeviceController::class)->destroy($request, $self);
            $this->fail('Sesi sendiri seharusnya ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->post(route('devices.destroy', $mine))->assertRedirect();

        $this->assertModelMissing($mine);
        $this->assertModelExists($theirs);
        $this->assertModelExists($self);
    }

    public function test_kick_all_others_keeps_this_session_and_other_users(): void
    {
        $me = User::factory()->create();
        $stranger = User::factory()->create();
        $this->other($me, 'a');
        $this->other($me, 'b');
        $theirs = $this->other($stranger, 'c');

        $this->actingAs($me)->get(route('devices.index'));
        $this->post(route('devices.destroy-others'))->assertRedirect();

        $this->assertSame(1, UserDevice::where('user_id', $me->id)->count());
        $this->assertModelExists($theirs);
    }

    public function test_speed_test_serves_requested_bytes_and_swallows_uploads_for_logged_in_users_only(): void
    {
        $this->get(route('devices.speed-down'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->assertSame(2048, strlen($this->get(route('devices.speed-down', ['kb' => 2]))->assertOk()->getContent()));
        $this->assertSame(8192 * 1024, strlen($this->get(route('devices.speed-down', ['kb' => 999999]))->getContent()));
        $this->call('POST', route('devices.speed-up'), [], [], [], [], str_repeat('x', 4096))->assertNoContent();
    }

    public function test_describe_reads_browser_and_system(): void
    {
        $this->assertSame('Edge di Windows', UserDevice::describe('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537 Edg/120'));
        $this->assertSame('Safari di iPhone', UserDevice::describe('Mozilla/5.0 (iPhone; CPU iPhone OS 17) Safari/604'));
        $this->assertSame('Browser', UserDevice::describe(null));
    }
}

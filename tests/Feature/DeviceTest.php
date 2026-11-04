<?php

namespace Tests\Feature;

use App\Http\Controllers\DeviceController;
use App\Models\DeviceReport;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    private function report(): array
    {
        return ['cpu' => ['name' => 'Intel Core i5-10310U', 'threads' => 8], 'memory' => ['total_gb' => 16.0, 'modules' => []], 'os' => ['system' => 'Windows', 'hostname' => 'LENOVO-M2'],
            'storage' => [['name' => 'C:', 'total_gb' => 238, 'free_gb' => 12]], 'software' => [['name' => 'Git', 'version' => '2.45']], 'patches' => []];
    }

    public function test_agent_needs_consent_then_report_arrives_once_and_shows_in_details(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me)->post(route('devices.agent'))->assertStatus(422);

        $response = $this->post(route('devices.agent'), ['consent' => 1])->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="adminku-perangkat.py"');
        $script = $response->getContent();
        $this->assertStringNotContainsString('__URL__', $script);
        preg_match('#"(http[^"]*/agent/report/[A-Za-z0-9]+)"#', $script, $m);
        $this->assertNotEmpty($m, 'alamat laporan tertanam di skrip');

        auth()->logout();   // agen tidak membawa sesi: hanya token
        $this->postJson($m[1], $this->report())->assertOk();
        $this->postJson($m[1], $this->report())->assertForbidden();   // token sekali pakai
        $this->postJson(route('agent.report', 'tokensalah'), $this->report())->assertForbidden();

        $this->actingAs($me)->get(route('devices.details'))->assertOk()->assertSee('Intel Core i5-10310U', false)->assertSee('LENOVO-M2', false)->assertSee('Git', false);
    }

    public function test_windows_download_is_a_cmd_with_crlf_and_embedded_token_and_needs_no_python(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->post(route('devices.agent'), ['consent' => 1, 'kind' => 'cmd'])->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="adminku-perangkat.cmd"');
        $script = $response->getContent();

        $this->assertStringNotContainsString('__URL__', $script);
        $this->assertMatchesRegularExpression('#\$Url = \'http[^\']*/agent/report/[A-Za-z0-9]{48}\'#', $script);
        $this->assertStringContainsString("\r\n", $script);
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $script), 'semua akhir baris CRLF');
        $this->assertStringNotContainsString('python', strtolower(substr($script, 0, strpos($script, '#PS#') + 4)));
    }

    public function test_history_lists_reports_opens_an_older_one_and_summary_on_main_screen_uses_the_latest(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $mk = fn ($u, $cpu, $at) => DeviceReport::create(['user_id' => $u->id, 'hostname' => 'PC-'.$cpu, 'cpu' => $cpu, 'ram_gb' => 8, 'payload' => ['cpu' => ['name' => $cpu, 'cores' => 2, 'threads' => 4]] + $this->report(), 'checked_at' => $at]);
        $old = $mk($me, 'CPU-LAMA', now()->subDay());
        $mk($me, 'CPU-BARU', now());
        $theirs = $mk($other, 'CPU-ORANG', now());

        $this->actingAs($me)->get(route('devices.details'))->assertOk()->assertSee('CPU-BARU', false)->assertSee('CPU-LAMA', false)->assertDontSee('CPU-ORANG', false);
        $this->get(route('devices.details', ['r' => $old->id]))->assertOk()->assertSee('PC-CPU-LAMA', false);
        $this->get(route('devices.details', ['r' => $theirs->id]))->assertNotFound();
        $this->get(route('devices.index'))->assertOk()->assertSee('CPU-BARU', false)->assertSee('Hasil pemeriksaan lengkap', false);
    }

    public function test_report_with_unknown_shape_is_rejected_and_details_empty_state_shows(): void
    {
        $me = User::factory()->create();
        Cache::put('agent-token:abc', $me->id, now()->addMinutes(5));
        $this->postJson(route('agent.report', 'abc'), ['foo' => 'bar'])->assertStatus(422);

        $this->actingAs($me)->get(route('devices.details'))->assertOk()->assertSee('Belum ada pemeriksaan', false);
    }

    public function test_describe_reads_browser_and_system(): void
    {
        $this->assertSame('Edge di Windows', UserDevice::describe('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537 Edg/120'));
        $this->assertSame('Safari di iPhone', UserDevice::describe('Mozilla/5.0 (iPhone; CPU iPhone OS 17) Safari/604'));
        $this->assertSame('Browser', UserDevice::describe(null));
    }
}

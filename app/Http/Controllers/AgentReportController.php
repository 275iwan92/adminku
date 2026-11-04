<?php

namespace App\Http\Controllers;

use App\Models\DeviceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/* REMARK
 * Tujuan       : Menerima laporan agen Python (More details) lewat token sekali pakai yang dibuat DeviceController@agent setelah pengguna menyetujui. Tanpa login dan tanpa CSRF (token = kuncinya).
 *                Token dibuang saat laporan diterima (tidak bisa dipakai dua kali) dan kedaluwarsa sendiri. Isi dibatasi ukuran dan bentuknya; yang tersimpan hanya kolom yang dikenal.
 * Dipakai oleh : routes/web.php (agent.report, di luar grup auth, dibatasi throttle).
 * Status       : Aktif.
 */
class AgentReportController extends Controller
{
    public const MAX_BYTES = 1_500_000;

    public function store(Request $request, string $token)
    {
        $userId = Cache::pull('agent-token:'.$token);   // pull = sekali pakai
        abort_if($userId === null, 403, 'Token tidak berlaku atau sudah dipakai.');
        abort_if(strlen($request->getContent()) > self::MAX_BYTES, 413);

        $data = json_decode($request->getContent(), true);
        abort_unless(is_array($data) && is_array($data['cpu'] ?? null), 422, 'Isi laporan tidak dikenal.');

        $data['software'] = array_slice((array) ($data['software'] ?? []), 0, 2000);
        $data['patches'] = array_slice((array) ($data['patches'] ?? []), 0, 500);

        DeviceReport::create([
            'user_id' => $userId,
            'ip' => $request->ip(),
            'platform' => mb_substr((string) ($data['os']['system'] ?? ''), 0, 30),
            'hostname' => mb_substr((string) ($data['os']['hostname'] ?? ''), 0, 100),
            'cpu' => mb_substr((string) ($data['cpu']['name'] ?? ''), 0, 150),
            'ram_gb' => is_numeric($data['memory']['total_gb'] ?? null) ? $data['memory']['total_gb'] : null,
            'payload' => $data,
            'checked_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}

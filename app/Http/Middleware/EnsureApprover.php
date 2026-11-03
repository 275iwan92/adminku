<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/* REMARK
 * Tujuan       : Membatasi aksi persetujuan (setujui / tolak / kembalikan) hanya untuk yang boleh menyetujui: Supervisor ke atas, bukan Admin Data.
 * Dipakai oleh : routes/web.php (alias 'approver').
 * Status       : Aktif.
 */
class EnsureApprover
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canApprove(), 403, 'Hanya Supervisor yang menyetujui.');

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RecruitStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Setup Rekrutmen (modul HRR, satu-satunya tombol Setup di panel): urutan tahap proses. Daftar (awal) dan Diterima (akhir) tetap: tidak bisa diganti nama atau dinonaktifkan;
 *                tahap tengah bebas ditambah, diganti nama, diurutkan (angka Urutan), dan dinonaktifkan. Tahap tidak dihapus (riwayat kandidat memakainya).
 * Sumber hulu  : m_recruit_step (+ jumlah kandidat di tahap itu).
 * Output       : grid Tahap, form Tahap.
 * Dipakai oleh : routes/web.php (recruit-steps.*).
 * Status       : Aktif.
 */
class RecruitStepController extends Controller
{
    public function index()
    {
        $rows = RecruitStep::query()->withCount('candidates')->ordered()->get()->map(fn (RecruitStep $s) => [
            'id' => $s->id, 'name' => $s->name, 'sort' => $s->sort, 'kind' => ['start' => 'Awal (tetap)', 'mid' => 'Tengah', 'end' => 'Akhir (tetap)'][$s->kind], 'scheduled' => $s->needs_schedule ? 'Ya' : '', 'candidates' => $s->candidates_count, 'active' => $s->is_active,
        ])->all();

        return view('recruit-steps.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('recruit-steps.form', ['step' => new RecruitStep(['sort' => (RecruitStep::where('kind', 'mid')->max('sort') ?? 10) + 10, 'kind' => 'mid'])]);
    }

    public function show(RecruitStep $step)
    {
        return view('recruit-steps.form', ['step' => $step->loadCount('candidates')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $step = RecruitStep::create($this->data($request) + ['kind' => 'mid']);

        return redirect()->route('recruit-steps.show', $step)->with('status', 'Tahap disimpan.');
    }

    public function update(Request $request, RecruitStep $step): RedirectResponse
    {
        $action = $request->input('_action');
        if (in_array($action, ['activate', 'deactivate'], true)) {
            if ($step->isFixed()) {
                return redirect()->route('recruit-steps.show', $step)->withErrors(['name' => 'Tahap awal dan akhir tidak bisa dinonaktifkan.']);
            }
            $step->update(['is_active' => $action === 'activate']);

            return redirect()->route('recruit-steps.show', $step)->with('status', $action === 'activate' ? 'Tahap diaktifkan.' : 'Tahap dinonaktifkan.');
        }
        $data = $this->data($request);
        $step->update($step->isFixed() ? ['sort' => $step->sort] : $data);   // tahap awal / akhir tetap: nama dan urutan tidak berubah

        return redirect()->route('recruit-steps.show', $step)->with('status', 'Tahap disimpan.');
    }

    private function data(Request $request): array
    {
        $step = $request->route('step');

        return $request->validate([
            'name' => ['required', 'string', 'max:60', \Illuminate\Validation\Rule::unique('m_recruit_step', 'name')->ignore($step)],
            'sort' => ['required', 'integer', 'min:1', 'max:9999'],
            'tone' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(RecruitStep::TONES))],
            'wip_limit' => ['nullable', 'integer', 'min:1', 'max:999'],
        ], ['name.unique' => 'Nama tahap ini sudah ada.']) + ['needs_schedule' => $request->boolean('needs_schedule')];
    }
}

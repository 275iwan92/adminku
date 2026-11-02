<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateRequest;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\RecruitStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/* REMARK
 * Tujuan       : Rekrutmen (HR, modul HRR): pendaftaran kandidat (kunci NIK KTP, boleh melamar ke beberapa bagian, foto KTP dan CV), papan kanban per tahap (pindah bebas: loncat atau mundur) dan daftar dengan pencarian NIK / filter.
 *                Tiap perpindahan tahap menambah satu baris riwayat (jadwal, penilai, hasil, catatan) yang bisa diubah di kartu kandidat. Kandidat tidak dihapus: status Ditolak / Mundur. Berkas disimpan privat (disk 'ga' folder recruit).
 * Sumber hulu  : h_recruit_candidate (+ dept, event), m_recruit_step, m_department, m_employee (peringatan NIK).
 * Output       : papan / daftar Kandidat, form Kandidat, unduh berkas, pindah tahap (JSON).
 * Dipakai oleh : routes/web.php (candidates.*).
 * Status       : Aktif.
 */
class CandidateController extends Controller
{
    public function index(Request $request)
    {
        $steps = RecruitStep::query()->where('is_active', true)->ordered()->get();
        $candidates = Candidate::query()->with(['departments', 'events'])->orderByDesc('applied_at')->orderByDesc('id')->get();
        $view = $request->query('view') === 'list' ? 'list' : 'board';

        $rows = $candidates->map(function (Candidate $c) use ($steps) {
            $last = $c->lastEvent();
            $upcoming = $c->events->filter(fn ($e) => $e->scheduled_on && $e->result === 'waiting')->sortBy('scheduled_on')->first();

            return [
                'id' => $c->id, 'name' => $c->name, 'nik' => $c->nik, 'depts' => $c->departments->pluck('name')->implode(', '), 'step' => $steps->firstWhere('id', $c->step_id)?->name ?? $c->step->name,
                'status' => Candidate::STATUSES[$c->status], 'result' => $last ? Candidate::RESULTS[$last->result] : '', 'source' => Candidate::SOURCES[$c->source] ?? '',
                'schedule' => $upcoming ? $upcoming->scheduled_on->format('Y-m-d').($upcoming->scheduled_time ? ' '.$upcoming->scheduled_time : '') : '', 'applied_at' => $c->applied_at->format('Y-m-d'), 'active' => $c->status === 'active',
            ];
        })->all();

        $cards = $candidates->where('status', 'active')->map(function (Candidate $c) {
            $last = $c->lastEvent();
            $due = $c->events->filter(fn ($e) => $e->scheduled_on && $e->result === 'waiting')->sortBy('scheduled_on')->first();

            return array_filter([
                'id' => (string) $c->id, 'col' => (string) $c->step_id, 'title' => $c->name, 'desc' => $c->nik.' · '.$c->departments->pluck('name')->implode(', '),
                'tags' => $last && $last->result !== 'waiting' ? [['label' => Candidate::RESULTS[$last->result], 'tone' => $last->result === 'pass' ? 'success' : 'danger']] : [],
                'due' => $due ? $due->scheduled_on->format('Y-m-d').' '.($due->scheduled_time ?: '00:00') : null,
            ]);
        })->values()->all();

        return view('candidates.index', [
            'view' => $view, 'rows' => $rows, 'cards' => $cards, 'columns' => $steps->map(fn ($s) => ['key' => (string) $s->id, 'label' => $s->name, 'tone' => $s->kind === 'mid' ? 'neutral' : 'success'])->all(),
            'steps' => $steps->pluck('name', 'id')->all(),
        ]);
    }

    public function create()
    {
        return view('candidates.form', $this->formData(new Candidate(['applied_at' => today(), 'status' => 'active', 'step_id' => RecruitStep::where('kind', 'start')->value('id')])));
    }

    public function show(Candidate $candidate)
    {
        return view('candidates.form', $this->formData($candidate));
    }

    public function store(CandidateRequest $request): RedirectResponse
    {
        $candidate = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['departments', 'ktp', 'cv', 'events']);
            $data['step_id'] = RecruitStep::where('kind', 'start')->value('id');   // kandidat baru selalu mulai dari Daftar
            $data['status'] = 'active';
            $candidate = Candidate::create($data + ['created_by' => $request->user()->id]);
            $candidate->departments()->sync($request->validated('departments'));
            $candidate->events()->create(['step_id' => $candidate->step_id, 'result' => 'pass', 'created_by' => $request->user()->id]);
            $this->saveFiles($candidate, $request);

            return $candidate;
        });

        return redirect()->route('candidates.show', $candidate)->with('status', 'Kandidat disimpan.');
    }

    public function update(CandidateRequest $request, Candidate $candidate): RedirectResponse
    {
        DB::transaction(function () use ($request, $candidate) {
            $data = $request->safe()->except(['departments', 'ktp', 'cv', 'events']);
            $data['step_id'] = $data['step_id'] ?? $candidate->step_id;
            $data['status'] = $data['status'] ?? $candidate->status;
            $candidate->update($data);
            $candidate->departments()->sync($request->validated('departments'));
            $candidate->events()->delete();   // riwayat = salinan form (tidak ada yang merujuk id barisnya)
            $candidate->events()->createMany(array_map(fn ($e) => $e + ['created_by' => $request->user()->id], array_values($request->validated('events', []))));
            $this->saveFiles($candidate, $request);
        });

        return redirect()->route('candidates.show', $candidate)->with('status', 'Kandidat disimpan.');
    }

    // Pindah tahap dari papan: bebas (loncat / mundur) ke tahap aktif mana pun; server memutuskan, riwayat bertambah satu baris.
    public function move(Request $request): JsonResponse
    {
        $data = $request->validate(['card' => ['required', 'integer'], 'to' => ['required', 'integer']]);
        $candidate = Candidate::findOrFail($data['card']);
        $step = RecruitStep::where('is_active', true)->find($data['to']);
        if (! $step) {
            return response()->json(['ok' => false, 'message' => 'Tahap tidak ditemukan atau nonaktif.'], 422);
        }
        if ($candidate->step_id !== $step->id) {
            DB::transaction(function () use ($candidate, $step, $request) {
                $candidate->update(['step_id' => $step->id]);
                $candidate->events()->create(['step_id' => $step->id, 'result' => $step->kind === 'end' ? 'pass' : 'waiting', 'created_by' => $request->user()->id]);
            });
        }

        return response()->json(['ok' => true]);
    }

    public function file(Candidate $candidate, string $kind)
    {
        $path = $candidate->{$kind.'_path'};
        abort_unless($path && Storage::disk('ga')->exists($path), 404);

        return Storage::disk('ga')->download($path, $candidate->{$kind.'_name'});
    }

    private function saveFiles(Candidate $candidate, CandidateRequest $request): void
    {
        foreach (['ktp', 'cv'] as $kind) {
            if (! $request->hasFile($kind)) {
                continue;
            }
            $file = $request->file($kind);
            $old = $candidate->{$kind.'_path'};
            $candidate->update([$kind.'_path' => $file->store("recruit/{$candidate->id}", 'ga'), $kind.'_name' => mb_substr($file->getClientOriginalName(), 0, 150)]);
            $old && Storage::disk('ga')->delete($old);
        }
    }

    private function formData(Candidate $candidate): array
    {
        $events = old('events') ?? $candidate->events->map(fn ($e) => ['step_id' => $e->step_id, 'scheduled_on' => $e->scheduled_on?->format('Y-m-d'), 'scheduled_time' => $e->scheduled_time, 'assessor' => $e->assessor, 'result' => $e->result, 'note' => $e->note])->all();
        $same = $candidate->nik ? Candidate::query()->where('nik', $candidate->nik)->where('id', '!=', $candidate->id ?? 0)->orderBy('applied_at')->get() : collect();

        return [
            'candidate' => $candidate,
            'departments' => Department::query()->where('is_active', true)->orWhereIn('id', $candidate->exists ? $candidate->departments->pluck('id') : [])->orderBy('name')->pluck('name', 'id')->all(),
            'chosen' => old('departments', $candidate->exists ? $candidate->departments->pluck('id')->all() : []),
            'steps' => RecruitStep::query()->where('is_active', true)->orWhere('id', $candidate->step_id)->ordered()->pluck('name', 'id')->all(),
            'events' => $events,
            'warnings' => array_values(array_filter([
                $same->isNotEmpty() ? 'NIK ini pernah melamar: '.$same->map(fn ($c) => $c->applied_at->format('d-m-Y'))->implode(', ').'.' : null,
                $candidate->nik && ($emp = Employee::where('nik_ktp', $candidate->nik)->first()) ? 'NIK ini sudah terdaftar sebagai karyawan: '.$emp->code.' · '.$emp->name.'.' : null,
            ])),
            'isEnd' => $candidate->exists && $candidate->step?->kind === 'end',
        ];
    }
}

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

        // Kartu mengikuti desain kanban DevLib: label bagian, hasil tahap sebelumnya, tenggat = jadwal terdekat, penilai (avatar), jumlah tahap lulus, prioritas = kedekatan jadwal, jumlah catatan.
        // meta[id] = sumber lamaran, penilai, lampiran: dipakai skrip papan (strip warna sumber, ikon lampiran, filter Sumber dan Penilai).
        $midSteps = $steps->where('kind', '!=', 'start');
        $meta = [];
        $cards = $candidates->where('status', 'active')->map(function (Candidate $c) use ($midSteps, $steps, &$meta) {
            $last = $c->lastEvent();
            $due = $c->events->filter(fn ($e) => $e->scheduled_on && $e->result === 'waiting')->sortBy('scheduled_on')->first();
            $days = $due ? (int) today()->diffInDays($due->scheduled_on, false) : null;
            $passed = $c->events->where('result', 'pass')->pluck('step_id')->all();
            $before = $c->events->filter(fn ($e) => $e->result !== 'waiting' && $e->step_id !== $c->step_id && $steps->firstWhere('id', $e->step_id)?->kind === 'mid')->last();
            $nik = substr($c->nik, 0, 4).' **** **** '.substr($c->nik, -4);
            $meta[(string) $c->id] = ['src' => $c->source ?: 'other', 'assessor' => $due?->assessor ?? $c->events->pluck('assessor')->filter()->last(), 'att' => (bool) ($c->ktp_path || $c->cv_path), 'applied' => $c->applied_at->toDateString(), 'pending' => (bool) ($steps->firstWhere('id', $c->step_id)?->needs_schedule && $last?->result === 'waiting'), 'stepName' => $steps->firstWhere('id', $c->step_id)?->name];

            return array_filter([
                'id' => (string) $c->id, 'col' => (string) $c->step_id, 'title' => $c->name, 'desc' => ($c->position ? $c->position.' · ' : '').$nik,
                'tags' => [...$c->departments->take(3)->map(fn ($d) => ['label' => $d->name, 'tone' => 'accent'])->all(),
                    ...($before ? [['label' => $steps->firstWhere('id', $before->step_id)->name.': '.Candidate::RESULTS[$before->result], 'tone' => $before->result === 'pass' ? 'success' : 'danger']] : []),
                    ...($last && $last->result === 'waiting' && $c->step->kind === 'mid' ? [['label' => Candidate::RESULTS['waiting'], 'tone' => 'warn']] : [])],
                'priority' => $days === null ? 'Low' : ($days <= 0 ? 'High' : ($days <= 7 ? 'Medium' : 'Low')),
                'due' => $due ? $due->scheduled_on->format('Y-m-d').' '.($due->scheduled_time ?: '00:00') : null,
                'assignee' => $meta[(string) $c->id]['assessor'],
                'checklist' => $midSteps->map(fn ($s) => ['text' => $s->name, 'done' => in_array($s->id, $passed, true)])->values()->all(),
                'comments' => $c->note && ! str_starts_with($c->note, 'Contoh') ? [['by' => 'Catatan', 'at' => '', 'text' => $c->note]] : [],
            ]);
        })->values()->all();

        $tones = ['accent', 'violet', 'warn'];
        $columns = $steps->values()->map(fn ($s, $i) => ['key' => (string) $s->id, 'label' => $s->name, 'tone' => $s->tone ?: ($s->kind === 'start' ? 'neutral' : ($s->kind === 'end' ? 'success' : $tones[$i % 3]))] + ($s->kind === 'end' ? ['done' => true] : []) + ($s->wip_limit ? ['limit' => $s->wip_limit] : []))->all();

        $active = $candidates->where('status', 'active');
        $weekEnd = today()->endOfWeek();
        $kpi = [
            'active' => $active->count(),
            'interviews' => $active->sum(fn (Candidate $c) => $c->events->filter(fn ($e) => $e->result === 'waiting' && $e->scheduled_on && $e->scheduled_on->between(today()->startOfWeek(), $weekEnd))->count()),
            'waiting' => $active->filter(fn (Candidate $c) => $c->step->kind === 'mid' && $c->lastEvent()?->result === 'waiting')->count(),
            'hired' => \App\Models\CandidateMove::query()->whereIn('to_step_id', $steps->where('kind', 'end')->pluck('id'))->where('moved_at', '>=', today()->startOfMonth())->count(),
        ];

        return view('candidates.index', [
            'view' => $view, 'rows' => $rows, 'cards' => $cards, 'columns' => $columns, 'meta' => $meta, 'kpi' => $kpi,
            'steps' => $steps->pluck('name', 'id')->all(), 'scheduled' => $steps->where('needs_schedule', true)->pluck('name', 'id')->all(),
            'assessors' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name')->all(),
            'departments' => Department::query()->whereIn('id', DB::table('h_recruit_candidate_dept')->pluck('department_id'))->orderBy('name')->pluck('name')->all(),
            'sources' => Candidate::SOURCES,
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

    // Pindah tahap dari papan (seret atau menu Pindah ke): bebas (loncat / mundur) ke tahap aktif mana pun; server memutuskan. Tahap berjadwal wajib tanggal + jam (pop-up di papan).
    // Tiap perpindahan menambah satu baris log (waktu, pemindah, dari, ke) dan satu baris riwayat tahap.
    public function move(Request $request): JsonResponse
    {
        $data = $request->validate(['card' => ['required', 'integer'], 'to' => ['required', 'integer'], 'scheduled_on' => ['nullable', 'date'], 'scheduled_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'], 'assessor' => ['nullable', 'string', 'max:100'], 'note' => ['nullable', 'string', 'max:255'], 'no_result' => ['nullable', 'boolean']]);
        $candidate = Candidate::findOrFail($data['card']);
        $step = RecruitStep::where('is_active', true)->find($data['to']);
        if (! $step) {
            return response()->json(['ok' => false, 'message' => 'Tahap tidak ditemukan atau nonaktif.'], 422);
        }
        if ($step->needs_schedule && $candidate->step_id !== $step->id && (empty($data['scheduled_on']) || empty($data['scheduled_time']))) {
            return response()->json(['ok' => false, 'message' => 'Isi tanggal dan jam untuk tahap '.$step->name.'.'], 422);
        }
        if ($candidate->step_id !== $step->id) {
            DB::transaction(function () use ($candidate, $step, $request, $data) {
                $from = $candidate->step_id;
                $when = $step->needs_schedule ? ['scheduled_on' => $data['scheduled_on'], 'scheduled_time' => $data['scheduled_time'], 'assessor' => $data['assessor'] ?? null] : [];
                $candidate->update(['step_id' => $step->id]);
                $candidate->moves()->create(['from_step_id' => $from, 'to_step_id' => $step->id, 'moved_by' => $request->user()->id, 'moved_at' => now(), 'no_result' => (bool) ($data['no_result'] ?? false)] + $when);
                $candidate->events()->create(['step_id' => $step->id, 'result' => $step->kind === 'end' ? 'pass' : 'waiting', 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id] + $when);
            });
        }

        return response()->json(['ok' => true]);
    }

    // Isi hasil tahap berjadwal yang sedang berjalan (dari peringatan "hasil belum diisi" saat kartu dipindah): memperbarui riwayat tahap terbaru yang masih Menunggu.
    public function result(Request $request, Candidate $candidate): JsonResponse
    {
        $data = $request->validate(['result' => ['required', 'in:pass,fail'], 'note' => ['nullable', 'string', 'max:255']]);
        $event = $candidate->events()->where('step_id', $candidate->step_id)->where('result', 'waiting')->reorder('id', 'desc')->first();
        if (! $event) {
            return response()->json(['ok' => false, 'message' => 'Tidak ada hasil yang menunggu pada tahap ini.'], 422);
        }
        $event->update(['result' => $data['result']] + (($data['note'] ?? null) ? ['note' => $data['note']] : []));

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
            'events' => $events, 'moves' => $candidate->exists ? $candidate->moves()->with(['from', 'to', 'mover'])->get() : collect(), 'assessors' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'name')->all() + collect($events)->pluck('assessor')->filter()->mapWithKeys(fn ($a) => [$a => $a])->all(),
            'warnings' => array_values(array_filter([
                $same->isNotEmpty() ? 'NIK ini pernah melamar: '.$same->map(fn ($c) => $c->applied_at->format('d-m-Y'))->implode(', ').'.' : null,
                $candidate->nik && ($emp = Employee::where('nik_ktp', $candidate->nik)->first()) ? 'NIK ini sudah terdaftar sebagai karyawan: '.$emp->code.' · '.$emp->name.'.' : null,
            ])),
            'isEnd' => $candidate->exists && $candidate->step?->kind === 'end',
        ];
    }
}

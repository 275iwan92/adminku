<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisciplineRequest;
use App\Models\Discipline;
use App\Models\Employee;
use App\Support\DisciplinePrint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Kedisiplinan (HR, modul HRD): penerbitan SP 1 / SP 2 / SP 3 / PHK kepada karyawan dengan nomor surat otomatis dan surat cetak. Urutan hanya disarankan (loncat memberi peringatan, tidak memblokir);
 *                SP berlaku 6 bulan (bisa diubah), PHK punya tanggal efektif dan tidak menonaktifkan karyawan otomatis. Surat terbit tidak diubah: Cabut dengan alasan.
 * Sumber hulu  : h_discipline, m_employee.
 * Output       : daftar penerbitan, form penerbitan, detail + Cabut, cetak surat.
 * Dipakai oleh : routes/web.php (disciplines.*).
 * Status       : Aktif.
 */
class DisciplineController extends Controller
{
    public function index()
    {
        $all = Discipline::query()->with('employee.department')->orderByDesc('issued_on')->orderByDesc('id')->get();
        $byEmployee = $all->groupBy('employee_id');
        $rows = $all->map(fn (Discipline $d) => [
            'id' => $d->id, 'doc_no' => $d->doc_no, 'issued_on' => $d->issued_on->format('Y-m-d'), 'employee' => $d->employee->name, 'code' => $d->employee->code, 'department' => $d->employee->department->name,
            'type' => Discipline::TYPES[$d->type], 'until_on' => $d->until_on?->format('Y-m-d'), 'state' => $d->stateLabel($byEmployee[$d->employee_id]), 'reason' => $d->reason,
            'now' => in_array($d->state($byEmployee[$d->employee_id]), ['active', 'issued'], true),
        ])->all();

        return view('disciplines.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $employee = Employee::where('is_active', true)->find($request->query('employee'));
        $suggest = $employee ? $this->suggest($employee) : 'sp1';
        $last = Discipline::query()->latest('id')->first();

        return view('disciplines.form', [
            'discipline' => new Discipline(['employee_id' => $employee?->id, 'type' => old('type', $request->query('type', $suggest)), 'issued_on' => today(), 'signer_name' => $last?->signer_name, 'signer_title' => $last?->signer_title]),
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn (Employee $e) => [$e->id => $e->code.' · '.$e->name])->all(),
            'employee' => $employee, 'suggest' => $suggest, 'inForce' => $employee ? $this->inForce($employee) : null,
        ]);
    }

    public function show(Discipline $discipline)
    {
        $discipline->load('employee.department');

        return view('disciplines.show', ['discipline' => $discipline, 'state' => $discipline->stateLabel(Discipline::where('employee_id', $discipline->employee_id)->get())]);
    }

    public function store(DisciplineRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $employee = Employee::findOrFail($data['employee_id']);
        $suggest = $this->suggest($employee);
        $issued = \Illuminate\Support\Carbon::parse($data['issued_on']);
        if ($data['type'] !== 'phk') {
            $data['until_on'] = ($data['until_on'] ?? null) ?: $issued->copy()->addMonths(Discipline::VALID_MONTHS)->toDateString();
        }
        $discipline = DB::transaction(function () use ($data, $issued, $request) {
            [$year, $seq, $no] = Discipline::nextNumber($data['type'], $issued);

            return Discipline::create($data + ['year' => $year, 'seq' => $seq, 'doc_no' => $no, 'created_by' => $request->user()->id]);
        });
        $skip = Discipline::RANK[$data['type']] > Discipline::RANK[$suggest];

        return redirect()->route('disciplines.show', $discipline)->with('status', 'Surat diterbitkan: '.$discipline->doc_no.'.'.($skip ? ' Perhatian: urutan yang disarankan untuk karyawan ini adalah '.Discipline::TYPES[$suggest].'.' : ''));
    }

    public function revoke(Request $request, Discipline $discipline): RedirectResponse
    {
        $data = $request->validate(['revoke_reason' => ['required', 'string', 'max:255']], ['revoke_reason.required' => 'Isi alasan pencabutan.']);
        abort_if($discipline->status === 'revoked', 422, 'Surat ini sudah dicabut.');
        $discipline->update($data + ['status' => 'revoked', 'revoked_at' => now()]);

        return redirect()->route('disciplines.show', $discipline)->with('status', 'Surat dicabut.');
    }

    public function print(Discipline $discipline, DisciplinePrint $print)
    {
        return view('disciplines.print', ['discipline' => $discipline, 'data' => $print->letter($discipline)]);
    }

    // SP yang masih berlaku bagi karyawan (tertinggi dulu).
    private function inForce(Employee $employee): ?Discipline
    {
        return Discipline::query()->inForce()->where('employee_id', $employee->id)->get()->sortByDesc(fn (Discipline $d) => Discipline::RANK[$d->type])->first();
    }

    // Jenis berikutnya yang sesuai urutan: belum ada SP berlaku = SP 1, lalu SP 2, SP 3, PHK.
    private function suggest(Employee $employee): string
    {
        $rank = $this->inForce($employee) ? Discipline::RANK[$this->inForce($employee)->type] : 0;

        return array_search(min($rank + 1, 4), Discipline::RANK, true);
    }
}

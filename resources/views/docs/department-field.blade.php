{{-- REMARK
 Tujuan       : Satu pilihan Departemen (dropdown master) yang dipakai di form Receiving, Issuance dan Adjustment.
 Sumber hulu  : StockDocumentController@formData (departments aktif), dokumen ($doc, departemen nonaktif tetap tampil di dokumen lama).
 Input        : doc, departments, v, e (dari docs/form).
 Output       : x-dl2::field.select name=department_id (+ tombol plus tambah cepat bila boleh menulis modul Departemen: \App\Support\QuickAdd).
 Dipakai oleh : docs/form.blade.php.
 Status       : Aktif.
--}}
<x-dl2::field.select name="department_id" label="Department" :options="$departments + ($doc->department_id && ! isset($departments[$doc->department_id]) ? [$doc->department_id => $doc->department] : [])" :value="$v('department_id')" :add="$locked ? [] : \App\Support\QuickAdd::for('department')" :error="$e('department_id')" :hint="$t === \App\Enums\StockDocumentType::Issuance ? 'Empty = the recipient\'s default department.' : null" />

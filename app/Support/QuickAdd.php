<?php

namespace App\Support;

use App\Models\User;

/* REMARK
 * Tujuan       : Satu-satunya tempat definisi tombol plus "tambah cepat" (x-dl2::field.select prop add) untuk dropdown master: alamat POST, judul, isian popup.
 *                Tombol hanya ada bila pengguna boleh MENULIS modul master itu (peran + Grup RW, sama dengan penjagaan rute); selain itu kosong = dropdown biasa.
 * Sumber hulu  : Modules / User::canWrite (hak), endpoint JSON store masing-masing master (balasan 201 {id,name}).
 * Input        : jenis (supplier | recipient | department | category | location | unit).
 * Output       : array untuk prop add, atau [] bila tak berhak.
 * Dipakai oleh : docs/form, partials/reorder-alert, recipients/form, products/form (dropdown master).
 * Status       : Aktif.
 */
final class QuickAdd
{
    // jenis => [kode modul, rute store, judul popup, ikon, isian [name, label, wajib, tipe, maxlength], catatan]
    private const KINDS = [
        'supplier' => ['SUP', 'suppliers.store', 'Add Supplier', 'fa-truck', [['name', 'Supplier Name', true, 'text', 150], ['phone', 'Company Phone', false, 'tel', 40], ['contact_name', 'Contact Name', false, 'text', 100]],
            'The supplier code is created automatically. Address and region can be completed later in Suppliers.'],
        'recipient' => ['RCP', 'recipients.store', 'Add Recipient', 'fa-user-tag', [['name', 'Recipient Name', true, 'text', 100]], null],
        'department' => ['DEP', 'departments.store', 'Add Department', 'fa-building', [['name', 'Department Name', true, 'text', 100]], 'The department code is created automatically.'],
        'category' => ['CAT', 'categories.store', 'Add Category', 'fa-tags', [['code', 'Code Prefix', true, 'text', 3], ['name', 'Category Name', true, 'text', 100]], 'Exactly 3 letters or digits. Cannot be changed after saving.'],
        'location' => ['LOC', 'locations.store', 'Add Location', 'fa-warehouse', [['code', 'Location Code', true, 'text', 50], ['name', 'Location Name', true, 'text', 100]], null],
        'unit' => ['UOM', 'units.store', 'Add Unit', 'fa-ruler', [['code', 'Unit Code', true, 'text', 20], ['name', 'Unit Name', true, 'text', 100]], 'Short, lowercase (pcs, box, kg). Cannot be changed after saving.'],
        // Sumber Dana: nilai opsi = nama (disalin ke Uang Masuk), jadi value-key name. Rekening bank: balasan {id,name}, dipakai id.
        'cash_source' => ['KSM', 'cash-sources.store', 'Add Source of Funds', 'fa-hand-holding-dollar', [['name', 'Source of Funds', true, 'text', 150]], null, 'name'],
        'cash_purpose' => ['KSK', 'cash-purposes.store', 'Add Purpose', 'fa-bullseye', [['name', 'Purpose', true, 'text', 150]], null, 'name'],
        'cash_bank' => ['KSM', 'cash-in.bank-account', 'Add Bank Account', 'fa-building-columns', [['bank_name', 'Bank Name', true, 'text', 100], ['account_no', 'Account Number', true, 'text', 30], ['account_holder', 'Account Holder', false, 'text', 100]], null, 'id'],
        'cash_bank_out' => ['KSK', 'cash-out.bank-account', 'Add Bank Account', 'fa-building-columns', [['bank_name', 'Bank Name', true, 'text', 100], ['account_no', 'Account Number', true, 'text', 30], ['account_holder', 'Account Holder', false, 'text', 100]], null, 'id'],
    ];

    public static function for(string $kind, ?User $user = null): array
    {
        [$module, $route, $title, $icon, $fields, $note, $valueKey] = self::KINDS[$kind] + [6 => null];
        $user ??= auth()->user();
        if (! $user?->canWrite($module)) {
            return [];
        }

        return array_filter([
            'url' => parse_url(route($route), PHP_URL_PATH), 'title' => $title, 'icon' => $icon, 'note' => $note, 'value-key' => $valueKey,
            'fields' => array_map(fn ($f) => ['name' => $f[0], 'label' => $f[1], 'required' => $f[2], 'type' => $f[3], 'maxlength' => $f[4]], $fields),
        ]);
    }
}

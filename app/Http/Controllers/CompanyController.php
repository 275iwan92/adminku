<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Support\Company;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Layar Master Data > Perusahaan: satu formulir (nama, nama PT / CV, NPWP, telepon, e-mail, alamat, logo, tampilan kop) yang menjadi SATU sumber kop semua cetakan. Hanya Supervisor.
 *                Rute company.logo menyajikan logo TANPA login (cetakan tautan publik doc-share memuatnya), dengan tipe isi benar dan cache; nama berkas tidak pernah dari pengguna.
 * Sumber hulu  : App\Support\Company (tabel sys_constant, storage/app/company/logo.png).
 * Output       : view company.form; berkas logo.
 * Dipakai oleh : routes/web.php (company.edit, company.update di grup supervisor; company.logo publik).
 * Status       : Aktif.
 */
class CompanyController extends Controller
{
    public function edit()
    {
        return view('company.form', ['company' => Company::get(), 'logoUrl' => Company::logoUrl()]);
    }

    public function update(CompanyRequest $request): RedirectResponse
    {
        Company::save($request->safe()->only([...Company::FIELDS, 'kop_style']), $request->file('logo'), $request->boolean('remove_logo'));

        return redirect()->route('company.edit')->with('status', 'Data perusahaan disimpan. Kop semua cetakan memakainya.');
    }

    public function logo()
    {
        $path = Company::logoPath();
        abort_unless($path, 404);

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}

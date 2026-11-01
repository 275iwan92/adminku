<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* REMARK
 * Tujuan       : Validasi simpan pengguna (tambah / ubah): nama, username unik (huruf kecil), e-mail unik, peran, password (wajib saat baru, kosong = tidak diganti).
 * Dipakai oleh : UserController@store, @update (hanya Administrator, dijaga middleware).
 * Status       : Aktif.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdministrator();
    }

    public function rules(): array
    {
        if (in_array($this->input('_action'), ['activate', 'deactivate'], true)) {
            return [];
        }
        $isNew = ! $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($this->route('user'))],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'group_id' => ['nullable', Rule::exists('sys_user_group', 'id')],
            'password' => [$isNew ? 'required' : 'nullable', 'string', 'min:8', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['username.regex' => 'Username hanya huruf kecil, angka, titik, garis bawah, atau strip.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => strtolower(Text::of($this->input('username')))]);
    }
}

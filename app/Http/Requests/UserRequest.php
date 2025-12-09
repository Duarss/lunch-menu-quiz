<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // protected function prepareForValidation(): void
    // {
    //     if ($this->has('username')) {
    //         $this->merge([
    //             'username' => trim((string) $this->input('username')),
    //         ]);
    //     }

    //     // UPDATE: password kosong jangan dikirim sebagai "" ke service
    //     if (!$this->isMethod('post') && !$this->filled('password')) {
    //         $this->request->remove('password');
    //     }
    // }

	protected function prepareForValidation(): void
	{
		if ($this->has('username')) {
			$this->merge([
				'username' => trim((string) $this->input('username')),
			]);
		}

		// CREATE: password default = username kalau kosong
		if ($this->isMethod('post') && !$this->filled('password')) {
			$this->merge([
				'password' => $this->input('username'),
			]);
		}

		// UPDATE: password kosong jangan dikirim sebagai ""
		if (!$this->isMethod('post') && !$this->filled('password')) {
			$this->request->remove('password');
		}
	}


    public function rules(): array
	{
		/** @var \App\Models\User|null $routeUser */
		$routeUser = $this->route('masterUser') ?? $this->route('user');

		$baseUsernameRules = ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'];

		if ($this->isMethod('post')) {
			// CREATE: username WAJIB ada dan unik
			$usernameRules = array_merge(
				['required'],
				$baseUsernameRules,
				[Rule::unique('users', 'username')] // atau 'lmq_users' kalau mau eksplisit
			);
		} else {
			// UPDATE: username read-only, hanya boleh sama dengan yang ada di model
			$usernameRules = array_merge(['sometimes'], $baseUsernameRules);

			if ($routeUser instanceof User) {
				$usernameRules[] = Rule::in([$routeUser->username]);
			}
		}

		return [
			'name'         => ['required', 'string', 'max:255'],
			'username'     => $usernameRules,
			'password'     => ['nullable', 'string', 'max:255'],
			'role'         => ['nullable', Rule::in(['karyawan'])],
			'company_code' => ['nullable', 'string', 'exists:companies,code'],
		];
	}
}

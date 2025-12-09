<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('day')) {
            $this->merge([
                'day' => strtoupper((string) $this->input('day')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'day' => ['required', Rule::in(['MON', 'TUE', 'WED', 'THU'])],
            'name_a' => ['required', 'string', 'max:255'],
            'name_b' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'day.in' => 'Hari menu tidak valid.',
            'name_a.required' => 'Nama menu A wajib diisi.',
            'name_b.required' => 'Nama menu B wajib diisi.',
            'image.image' => 'File yang diunggah harus berupa gambar.',
        ];
    }
}

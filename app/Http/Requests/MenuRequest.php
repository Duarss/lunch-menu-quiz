<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by route middleware; allow here.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('day') && ($this->routeIs('masterMenu.store') || $this->routeIs('masterMenu.updateImage'))) {
            $this->merge([
                'day' => strtoupper((string) $this->input('day')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->routeIs('masterMenu.store')) {
            return [
                'week_code' => ['required', 'string'],
                'day' => ['required', Rule::in(['MON', 'TUE', 'WED', 'THU'])],
                'vendor_a_image' => ['required', 'file', 'image', 'max:2048'],
                'vendor_b_image' => ['required', 'file', 'image', 'max:2048'],
            ];
        }

        if ($this->routeIs('masterMenu.updateImage')) {
            return [
                'week_code' => ['required', 'string'],
                'day' => ['required', Rule::in(['MON', 'TUE', 'WED', 'THU'])],
                'catering' => ['required', Rule::in(['vendorA', 'vendorB'])],
                'image' => ['required', 'file', 'image', 'max:2048'],
            ];
        }

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                'unique:menus,code',
                'regex:/^\d{4}-\d{2}-W\d+-(MON|TUE|WED|THU)-\d+$/i'
            ],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'file', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        if ($this->routeIs('masterMenu.store')) {
            $vendorALabel = $this->vendorDisplayName('vendorA');
            $vendorBLabel = $this->vendorDisplayName('vendorB');
            return [
                'vendor_a_image.required' => $vendorALabel . ' image is required.',
                'vendor_b_image.required' => $vendorBLabel . ' image is required.',
            ];
        }

        if ($this->routeIs('masterMenu.updateImage')) {
            return [
                'image.required' => 'Please choose an image to upload.',
            ];
        }

        return [];
    }

    protected function vendorDisplayName(string $code): string
    {
        static $cache = [];

        if (!isset($cache[$code])) {
            $name = Company::where('code', $code)->value('name');
            if (!$name) {
                $name = match ($code) {
                    'vendorA' => 'Vendor A',
                    'vendorB' => 'Vendor B',
                    default => ucfirst($code),
                };
            }
            $cache[$code] = $name;
        }

        return $cache[$code];
    }
}

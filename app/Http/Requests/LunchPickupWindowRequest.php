<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class LunchPickupWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'windows' => ['nullable', 'array'],
        ];

        $windows = $this->input('windows', []);
        if (!is_array($windows)) {
            $windows = [];
        }

        foreach ($windows as $index => $window) {
            $idRule = Rule::exists('lunch_pickup_windows', 'id');

            $rules["windows.$index.id"] = ['nullable', 'integer', $idRule];
            $rules["windows.$index.date"] = [
                'required',
                'date_format:Y-m-d',
                'distinct',
                Rule::unique('lunch_pickup_windows', 'date')->ignore($window['id'] ?? null),
            ];
            $rules["windows.$index.start_time"] = ['required', 'date_format:H:i'];
            $rules["windows.$index.end_time"] = ['required', 'date_format:H:i'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $windows = $this->input('windows', []);
            if (!is_array($windows)) {
                $windows = [];
            }

            foreach ($windows as $index => $window) {
                $start = $window['start_time'] ?? null;
                $end = $window['end_time'] ?? null;

                if ($start && $end && $start >= $end) {
                    $validator->errors()->add("windows.$index.end_time", 'Jam selesai harus lebih besar dari jam mulai.');
                }
            }
        });
    }
}

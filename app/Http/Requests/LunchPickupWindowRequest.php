<?php

namespace App\Http\Requests;

use App\Models\LunchPickupWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LunchPickupWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'windows' => ['required', 'array'],
        ];

        foreach (LunchPickupWindow::DAYS as $day) {
            $rules["windows.$day.start_time"] = ['nullable', 'date_format:H:i'];
            $rules["windows.$day.end_time"] = ['nullable', 'date_format:H:i'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (LunchPickupWindow::DAYS as $day) {
                $start = $this->input("windows.$day.start_time");
                $end = $this->input("windows.$day.end_time");

                if ($start && !$end) {
                    $validator->errors()->add("windows.$day.end_time", 'Jam selesai wajib diisi jika jam mulai diisi.');
                }

                if (!$start && $end) {
                    $validator->errors()->add("windows.$day.start_time", 'Jam mulai wajib diisi jika jam selesai diisi.');
                }

                if ($start && $end && $start >= $end) {
                    $validator->errors()->add("windows.$day.end_time", 'Jam selesai harus lebih besar dari jam mulai.');
                }
            }
        });
    }
}

<?php

namespace App\Actions\LunchPickupWindow;

use App\Models\LunchPickupWindow;
use Illuminate\Validation\ValidationException;

class CreateLunchPickupWindows extends LunchPickupWindowAction
{
    public function __invoke(array $windows): void
    {
        $payloads = collect($windows)
            ->values()
            ->map(function (array $window, int $index) {
                // make sure that the date being chosen is aligining with the day (Monday to Thursday) only
                $date = $window['date'] ?? null;
                $dayOfWeek = $date ? date('N', strtotime($date)) : null;
                if ($dayOfWeek && ($dayOfWeek < 1 || $dayOfWeek > 4)) {
                    throw ValidationException::withMessages([
                        "windows.$index.date" => 'Tanggal harus antara Senin hingga Kamis.',
                    ]);
                }

                $rawStart = $window['start_time'] ?? null;
                $rawEnd = $window['end_time'] ?? null;

                $start = $this->formatTimeForStorage($rawStart);
                $end = $this->formatTimeForStorage($rawEnd);

                if ($start && $end && $start >= $end) {
                    throw ValidationException::withMessages([
                        "windows.$index.end_time" => 'Jam selesai harus lebih besar dari jam mulai.',
                    ]);
                }

                return [
                    'id' => $window['id'] ?? null,
                    'date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            })
            ->filter(fn(array $window) => !empty($window['date']))
            ->values();

        $persistedIds = [];

        foreach ($payloads as $windowPayload) {
            $window = null;

            if (!empty($windowPayload['id'])) {
                $window = LunchPickupWindow::find($windowPayload['id']);
            }

            if (!$window) {
                $window = LunchPickupWindow::firstOrNew(['date' => $windowPayload['date']]);
            }

            $window->date = $windowPayload['date'];
            $window->start_time = $windowPayload['start_time'];
            $window->end_time = $windowPayload['end_time'];
            $window->save();

            $persistedIds[] = $window->id;
        }

        if (count($persistedIds)) {
            LunchPickupWindow::whereNotIn('id', $persistedIds)->delete();
        } else {
            LunchPickupWindow::query()->delete();
        }
    }
}

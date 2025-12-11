<?php

namespace App\Actions\LunchPickupWindow;

use App\Models\LunchPickupWindow;

class GetLunchPickupWindowsForForm extends LunchPickupWindowAction
{
    // Note: __construct injects the GetLunchPickupWindows action
    public function __construct(private GetLunchPickupWindows $getLunchPickupWindows) {}

    // Note: __invoke retrieves lunch pickup windows formatted for form inputs
    public function __invoke(): array
    {
        return ($this->getLunchPickupWindows)()
            ->mapWithKeys(function (LunchPickupWindow $window) {
                return [
                    $window->day_of_week => [
                        'start_time' => $window->start_time_for_input,
                        'end_time' => $window->end_time_for_input,
                    ],
                ];
            })
            ->toArray();
    }
}

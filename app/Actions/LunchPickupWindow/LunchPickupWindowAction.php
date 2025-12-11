<?php

namespace App\Actions\LunchPickupWindow;

use Illuminate\Support\Carbon;

abstract class LunchPickupWindowAction
{
    protected function formatTimeForStorage(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $value)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}

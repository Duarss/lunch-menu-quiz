<?php

namespace App\Actions\LunchPickupWindow;

use App\Models\LunchPickupWindow;
use Illuminate\Validation\ValidationException;

class DeleteLunchPickupWindows extends LunchPickupWindowAction
{
    public function __invoke(string $date): void
    {
        $date = trim($date);

        if ($date === '') {
            throw ValidationException::withMessages([
                'date' => 'Tanggal window tidak valid.'
            ]);
        }

        LunchPickupWindow::where('date', $date)->delete();
    }
}

?>
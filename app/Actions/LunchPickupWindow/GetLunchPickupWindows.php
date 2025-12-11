<?php

namespace App\Actions\LunchPickupWindow;

use App\Models\LunchPickupWindow;
use Illuminate\Support\Collection;

class GetLunchPickupWindows extends LunchPickupWindowAction
{
    public function __invoke(): Collection
    {
        return LunchPickupWindow::orderBy('date')->get();
    }
}

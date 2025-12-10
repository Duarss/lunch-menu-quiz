<?php

namespace App\Services;

use App\Actions\LunchPickupWindow\GetLunchPickupWindows;
use App\Actions\LunchPickupWindow\GetLunchPickupWindowsForForm;
use App\Actions\LunchPickupWindow\UpdateLunchPickupWindows;
use Illuminate\Support\Collection;

class LunchPickupWindowService
{
    public const DAY_LABELS = [
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
    ];

    public function __construct(
        private GetLunchPickupWindows $getLunchPickupWindows,
        private GetLunchPickupWindowsForForm $getLunchPickupWindowsForForm,
        private UpdateLunchPickupWindows $updateLunchPickupWindows
    ) {}

    public function getWindows(): Collection
    {
        return ($this->getLunchPickupWindows)();
    }

    public function getWindowsForForm(): array
    {
        return ($this->getLunchPickupWindowsForForm)();
    }

    public function updateWindows(array $windows): void
    {
        ($this->updateLunchPickupWindows)($windows);
    }
}

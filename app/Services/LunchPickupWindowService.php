<?php

namespace App\Services;

use App\Actions\LunchPickupWindow\GetLunchPickupWindows;
use App\Actions\LunchPickupWindow\GetLunchPickupWindowsForForm;
use App\Actions\LunchPickupWindow\UpdateLunchPickupWindows;
use Illuminate\Support\Collection;

class LunchPickupWindowService
{
    // Note: DAY_LABELS provides the mapping of day keys to their Indonesian names
    public const DAY_LABELS = [
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
    ];

    // Note: __construct initializes the service with the necessary action dependencies
    public function __construct(
        private GetLunchPickupWindows $getLunchPickupWindows,
        private GetLunchPickupWindowsForForm $getLunchPickupWindowsForForm,
        private UpdateLunchPickupWindows $updateLunchPickupWindows
    ) {}

    // Note: getWindows retrieves the current lunch pickup windows as a collection
    public function getWindows(): Collection
    {
        return ($this->getLunchPickupWindows)();
    }

    // Note: getWindowsForForm retrieves the lunch pickup windows formatted for form usage
    public function getWindowsForForm(): array
    {
        return ($this->getLunchPickupWindowsForForm)();
    }

    // Note: updateWindows updates the lunch pickup windows with the provided data
    public function updateWindows(array $windows): void
    {
        ($this->updateLunchPickupWindows)($windows);
    }
}

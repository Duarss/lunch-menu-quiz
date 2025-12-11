<?php

namespace App\Services;

use App\Actions\LunchPickupWindow\GetLunchPickupWindows;
use App\Actions\LunchPickupWindow\GetLunchPickupWindowsForForm;
use App\Actions\LunchPickupWindow\CreateLunchPickupWindows;
use App\Actions\LunchPickupWindow\DeleteLunchPickupWindows;
use App\Actions\LunchPickupWindow\UpdateLunchPickupWindows;
use Illuminate\Support\Collection;

class LunchPickupWindowService
{
    public function __construct(
        private GetLunchPickupWindows $getLunchPickupWindows,
        private GetLunchPickupWindowsForForm $getLunchPickupWindowsForForm,
        private CreateLunchPickupWindows $createLunchPickupWindows,
        private UpdateLunchPickupWindows $updateLunchPickupWindows,
        private DeleteLunchPickupWindows $deleteLunchPickupWindows,
    ) {}

    public function getWindows(): Collection
    {
        return ($this->getLunchPickupWindows)();
    }

    public function getWindowsForForm(): array
    {
        return ($this->getLunchPickupWindowsForForm)();
    }

    public function syncWindowTimes(array $windows): void
    {
        ($this->createLunchPickupWindows)($windows);
    }

    public function updateWindowTimes(array $windows): void
    {
        ($this->updateLunchPickupWindows)($windows);
    }

    public function deleteWindowTimes(string $date): void
    {
        ($this->deleteLunchPickupWindows)($date);
    }
}

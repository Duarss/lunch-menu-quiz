<?php

namespace App\Services;

use App\Actions\Menu\BuildMenuIndexData;
use App\Actions\Menu\BuildVendorIndexData;
use App\Actions\Menu\CreateCateringPair;
use App\Actions\Menu\CreateWeeklyMenu;
use App\Actions\Menu\FormatMenu;
use App\Actions\Menu\GenerateNextWeekOptions;
use App\Actions\Menu\GetSelectionWindowStatus;
use App\Actions\Menu\ResolveVendorLabels;
use App\Actions\Menu\ResolveVendorSlotMap;
use App\Actions\Menu\SaveKaryawanSelections;
use App\Actions\Menu\SaveVendorMenu;
use App\Actions\Menu\UpdateCateringImage;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class MenuService
{
    public function __construct(
        private BuildMenuIndexData $buildMenuIndexDataAction,
        private BuildVendorIndexData $buildVendorIndexDataAction,
        private CreateWeeklyMenu $createWeeklyMenuAction,
        private CreateCateringPair $createCateringPairAction,
        private UpdateCateringImage $updateCateringImageAction,
        private GenerateNextWeekOptions $generateNextWeekOptionsAction,
        private GetSelectionWindowStatus $getSelectionWindowStatusAction,
        private SaveKaryawanSelections $saveKaryawanSelectionsAction,
        private SaveVendorMenu $saveVendorMenuAction,
        private FormatMenu $formatMenuAction,
        private ResolveVendorLabels $resolveVendorLabelsAction,
        private ResolveVendorSlotMap $resolveVendorSlotMapAction,
    ) {}

    public function createWeeklyMenu(array $data, UploadedFile $image): Menu
    {
        return ($this->createWeeklyMenuAction)($data, $image);
    }

    public function createCateringPair(array $data, UploadedFile $image): array
    {
        return ($this->createCateringPairAction)($data, $image);
    }

    public function updateCateringImage(array $data, UploadedFile $image): array
    {
        return ($this->updateCateringImageAction)($data, $image);
    }

    public function generateNextWeekOptions(): array
    {
        return ($this->generateNextWeekOptionsAction)();
    }

    public function getSelectionWindowStatus(): array
    {
        return ($this->getSelectionWindowStatusAction)();
    }

    public function getIndexData(User $user): array
    {
        return ($this->buildMenuIndexDataAction)($user);
    }

    public function saveKaryawanSelections(User $user, string $weekCode, array $choices, string $tz): array
    {
        return ($this->saveKaryawanSelectionsAction)($user, $weekCode, $choices, $tz);
    }

    public function saveVendorMenu(User $vendor, array $payload, ?UploadedFile $image = null): array
    {
        return ($this->saveVendorMenuAction)($vendor, $payload, $image);
    }

    public function getVendorIndexData(User $vendor): array
    {
        return ($this->buildVendorIndexDataAction)($vendor);
    }

    public function formatMenu(Menu $menu): array
    {
        return ($this->formatMenuAction)($menu);
    }

    public function vendorLabels(): array
    {
        return ($this->resolveVendorLabelsAction)();
    }

    public function vendorSlotMap(string $catering): array
    {
        return ($this->resolveVendorSlotMapAction)($catering);
    }
}

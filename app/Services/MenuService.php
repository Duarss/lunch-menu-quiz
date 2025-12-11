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
    // Note: __construct initializes the service with the necessary action dependencies
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

    // Note: createWeeklyMenu creates a new weekly menu with the provided data and image
    public function createWeeklyMenu(array $data, UploadedFile $image): Menu
    {
        return ($this->createWeeklyMenuAction)($data, $image);
    }

    // Note: createCateringPair creates a catering pair with the provided data and image
    public function createCateringPair(array $data, UploadedFile $image): array
    {
        return ($this->createCateringPairAction)($data, $image);
    }

    // Note: updateCateringImage updates the catering image for menus based on provided data and image
    public function updateCateringImage(array $data, UploadedFile $image): array
    {
        return ($this->updateCateringImageAction)($data, $image);
    }

    // Note: generateNextWeekOptions generates the menu options for the next week
    public function generateNextWeekOptions(): array
    {
        return ($this->generateNextWeekOptionsAction)();
    }

    // Note: getSelectionWindowStatus retrieves the current status of the selection window
    public function getSelectionWindowStatus(): array
    {
        return ($this->getSelectionWindowStatusAction)();
    }

    // Note: getIndexData retrieves the menu index data for the specified user
    public function getIndexData(User $user): array
    {
        return ($this->buildMenuIndexDataAction)($user);
    }

    // Note: saveKaryawanSelections saves the menu selections made by the karyawan user
    public function saveKaryawanSelections(User $user, string $weekCode, array $choices, string $tz): array
    {
        return ($this->saveKaryawanSelectionsAction)($user, $weekCode, $choices, $tz);
    }

    // Note: saveVendorMenu saves the vendor menu with the provided payload and optional image
    public function saveVendorMenu(User $vendor, array $payload, ?UploadedFile $image = null): array
    {
        return ($this->saveVendorMenuAction)($vendor, $payload, $image);
    }

    // Note: getVendorIndexData retrieves the vendor-specific index data for the given vendor user
    public function getVendorIndexData(User $vendor): array
    {
        return ($this->buildVendorIndexDataAction)($vendor);
    }

    // Note: formatMenu formats the given menu into an array representation
    public function formatMenu(Menu $menu): array
    {
        return ($this->formatMenuAction)($menu);
    }

    // Note: vendorLabels retrieves the labels for the different vendors
    public function vendorLabels(): array
    {
        return ($this->resolveVendorLabelsAction)();
    }

    // Note: vendorSlotMap retrieves the slot map for the specified catering vendor
    public function vendorSlotMap(string $catering): array
    {
        return ($this->resolveVendorSlotMapAction)($catering);
    }
}

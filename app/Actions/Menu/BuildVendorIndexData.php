<?php

namespace App\Actions\Menu;

use App\Helpers\Project;
use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;

class BuildVendorIndexData extends MenuAction
{
    public function __invoke(User $vendor): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $catering = $this->resolveVendorCatering($vendor);

        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        $creation = $this->resolveNextCreationWeek($targetMonday);
        /** @var Carbon $weekMonday */
        $weekMonday = $creation['monday'];
        $weekCode = $creation['code'];
        $rangeStart = $weekMonday->toDateString();
        $rangeEnd = $weekMonday->copy()->addDays(3)->toDateString();

        $slotMap = $this->vendorSlotMap($catering);

        $menus = Menu::where('code', 'like', $weekCode . '-%')
            ->where('catering', $catering)
            ->orderBy('code')
            ->get()
            ->keyBy('code');

        $dayOrder = ['Mon', 'Tue', 'Wed', 'Thu'];
        $vendorDays = [];
        foreach ($dayOrder as $offset => $label) {
            $date = $weekMonday->copy()->addDays($offset);
            $dayCode = strtoupper(substr($label, 0, 3));

            $isHoliday = $this->isHoliday($date);

            $options = [];
            if (!$isHoliday) {
                foreach ($slotMap as $optionKey => $sequence) {
                    $code = sprintf('%s-%s-%d', $weekCode, $dayCode, $sequence);
                    /** @var Menu|null $menu */
                    $menu = $menus->get($code);

                    $options[$optionKey] = [
                        'option' => $optionKey,
                        'label' => $this->vendorOptionLabel($optionKey),
                        'code' => $menu?->code,
                        'name' => $menu?->name ?? '',
                        'image' => $menu?->image,
                        'image_url' => $this->imageUrl($menu?->image),
                        'has_menu' => $menu !== null,
                    ];
                }
            }

            $vendorDays[$label] = [
                'label' => $label,
                'day_code' => $dayCode,
                'date' => $date->toDateString(),
                'date_label' => $date->format('D, d M Y'),
                'is_holiday' => $isHoliday,
                'options' => $options,
            ];
        }

        return [
            'weekCode' => $weekCode,
            'vendorWeekCode' => $weekCode,
            'vendorDayOrder' => $dayOrder,
            'vendorDays' => $vendorDays,
            'vendorCatering' => $catering,
            'vendorCateringLabel' => $this->cateringDisplayName($catering),
            'vendorRangeStart' => $rangeStart,
            'vendorRangeEnd' => $rangeEnd,
            'selectionWindowReady' => Project::isSelectionWindowReady($weekCode),
            'selectionWindowOpen' => Project::isSelectionWindowOpen($now),
            'vendorExistingCount' => $creation['existing_count'],
        ];
    }
}

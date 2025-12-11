<?php

namespace App\Actions\Menu;

use App\Models\Menu;
use Illuminate\Http\UploadedFile;

class CreateWeeklyMenu extends MenuAction
{
    // Note: __invoke creates a weekly menu entry with the provided data and image
    public function __invoke(array $data, UploadedFile $image): Menu
    {
        if (!empty($data['catering'])) {
            $data['catering'] = $this->normalizeCateringKey($data['catering']);
        }

        if (!empty($data['week_code']) && !empty($data['day'])) {
            $data['menu_date'] = $this->resolveMenuDate($data['week_code'], $data['day']);
        }

        $data['image'] = $this->storeImage(
            $image,
            $data['week_code'] ?? null,
            $data['day'] ?? null,
            $data['catering'] ?? null
        );

        return Menu::create($data);
    }
}

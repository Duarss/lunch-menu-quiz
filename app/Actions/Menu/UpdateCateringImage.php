<?php

namespace App\Actions\Menu;

use App\Models\Menu;
use Illuminate\Http\UploadedFile;

class UpdateCateringImage extends MenuAction
{
    // Note: __invoke updates the catering image for menus based on provided data and image
    public function __invoke(array $data, UploadedFile $image): array
    {
        $weekCode = $data['week_code'];
        $day = strtoupper($data['day']);
        $catering = $this->normalizeCateringKey($data['catering']);

        $slotMap = self::CATERING_SLOT_MAP[$catering] ?? null;
        if ($slotMap === null) {
            throw new \InvalidArgumentException('Unsupported catering: ' . $catering);
        }

        $prefix = $weekCode . '-' . $day . '-';
        $allowedSeq = array_keys($slotMap);
        $targetCodes = array_map(static fn($seq) => sprintf('%s%d', $prefix, $seq), $allowedSeq);

        $menus = Menu::whereIn('code', $targetCodes)
            ->whereIn('catering', $this->cateringCandidates($catering))
            ->get();

        if ($menus->isEmpty()) {
            throw new \RuntimeException('Menus are not available yet. Please create them first.');
        }

        $newImagePath = $this->storeImage($image, $weekCode, $day, $catering);
        $oldImages = [];

        foreach ($menus as $menu) {
            $oldImages[] = $menu->image;
            $updates = [
                'image' => $newImagePath,
                'catering' => $catering,
            ];

            if (empty($menu->menu_date)) {
                $updates['menu_date'] = $this->resolveMenuDate($weekCode, $day);
            }

            $menu->update($updates);
        }

        $this->cleanupReplacedImages($oldImages, $newImagePath);

        return Menu::whereIn('code', $targetCodes)
            ->whereIn('catering', $this->cateringCandidates($catering))
            ->orderBy('code')
            ->get()
            ->all();
    }
}

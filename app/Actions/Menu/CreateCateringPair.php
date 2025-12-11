<?php

namespace App\Actions\Menu;

use App\Models\Menu;
use Illuminate\Http\UploadedFile;

class CreateCateringPair extends MenuAction
{
    // Note: __invoke creates a pair of menus for a given day & catering using a single image
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
        $menuDate = $this->resolveMenuDate($weekCode, $day);

        $existing = Menu::where('code', 'like', $prefix . '%')
            ->whereIn('catering', $this->cateringCandidates($catering))
            ->orderBy('code')
            ->get(['code']);

        $usedSeq = [];
        foreach ($existing as $menu) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(?P<seq>\d+)$/', $menu->code, $matches)) {
                $usedSeq[(int) $matches['seq']] = true;
            }
        }

        $missingSlots = array_values(array_filter($allowedSeq, fn($seq) => !isset($usedSeq[$seq])));
        if (empty($missingSlots)) {
            throw new \RuntimeException('Selected day already has a full set of ' . $this->cateringDisplayName($catering) . ' menus.');
        }

        $imagePath = $this->storeImage($image, $weekCode, $day, $catering);

        $created = [];
        foreach ($missingSlots as $slot) {
            $code = sprintf('%s%d', $prefix, $slot);
            $created[] = Menu::create([
                'code' => $code,
                'name' => $slotMap[$slot] ?? 'Opsi',
                'catering' => $catering,
                'menu_date' => $menuDate,
                'image' => $imagePath,
            ]);
        }

        return $created;
    }
}

<?php

namespace App\Actions\Menu;

use App\Models\Menu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SaveVendorMenu extends MenuAction
{
    // Note: __invoke saves or updates the menu items for a vendor for a given day
    public function __invoke(User $vendor, array $payload, ?UploadedFile $image = null): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $catering = $this->resolveVendorCatering($vendor);

        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        $creation = $this->resolveNextCreationWeek($targetMonday);
        /** @var Carbon $weekMonday */
        $weekMonday = $creation['monday'];
        $weekCode = $creation['code'];

        $day = strtoupper($payload['day'] ?? '');
        $nameA = trim((string) ($payload['name_a'] ?? ''));
        $nameB = trim((string) ($payload['name_b'] ?? ''));

        if ($nameA === '' || $nameB === '') {
            throw new \RuntimeException('Nama menu A dan B wajib diisi.');
        }

        if (!in_array($day, ['MON', 'TUE', 'WED', 'THU'], true)) {
            throw new \RuntimeException('Hari menu tidak valid.');
        }

        $slotMap = $this->vendorSlotMap($catering);
        if (!isset($slotMap['A'], $slotMap['B'])) {
            throw new \RuntimeException('Slot menu vendor belum lengkap dikonfigurasi.');
        }

        $seqA = $slotMap['A'];
        $seqB = $slotMap['B'];

        $codeA = sprintf('%s-%s-%d', $weekCode, $day, $seqA);
        $codeB = sprintf('%s-%s-%d', $weekCode, $day, $seqB);

        /** @var Menu|null $menuA */
        $menuA = Menu::where('code', $codeA)->first();
        /** @var Menu|null $menuB */
        $menuB = Menu::where('code', $codeB)->first();

        $menuACatering = $menuA ? $this->normalizeCatering($menuA->catering) : null;
        $menuBCatering = $menuB ? $this->normalizeCatering($menuB->catering) : null;

        if (($menuA && $menuACatering && $menuACatering !== $catering)
            || ($menuB && $menuBCatering && $menuBCatering !== $catering)
        ) {
            throw new \RuntimeException('Menu ini tidak terdaftar untuk vendor Anda.');
        }

        $anyExisting = $menuA || $menuB;
        if (!$anyExisting && !$image) {
            throw new \RuntimeException('Gambar wajib diunggah untuk menu baru.');
        }

        $imagePath = null;
        if ($image) {
            $imagePath = $this->storeImage($image, $weekCode, $day, $catering);
        }

        $updatedMenus = [];
        $menuDate = $this->resolveMenuDate($weekCode, $day);

        if (!$menuA) {
            $menuA = Menu::create([
                'code' => $codeA,
                'name' => $nameA,
                'image' => $imagePath,
                'catering' => $catering,
                'menu_date' => $menuDate,
            ]);
        } else {
            $updateA = [
                'name' => $nameA,
                'catering' => $catering,
                'menu_date' => $menuDate,
            ];
            if ($imagePath !== null) {
                $oldImageA = $menuA->image;
                $updateA['image'] = $imagePath;
                if ($oldImageA && !preg_match('/^https?:\/\//i', $oldImageA) && $oldImageA !== $imagePath) {
                    Storage::disk('public')->delete($oldImageA);
                }
            }
            $menuA->fill($updateA)->save();
        }
        $updatedMenus[] = $menuA->refresh();

        if (!$menuB) {
            $menuB = Menu::create([
                'code' => $codeB,
                'name' => $nameB,
                'image' => $imagePath,
                'catering' => $catering,
                'menu_date' => $menuDate,
            ]);
        } else {
            $updateB = [
                'name' => $nameB,
                'catering' => $catering,
                'menu_date' => $menuDate,
            ];
            if ($imagePath !== null) {
                $oldImageB = $menuB->image;
                $updateB['image'] = $imagePath;
                if ($oldImageB && !preg_match('/^https?:\/\//i', $oldImageB) && $oldImageB !== $imagePath) {
                    Storage::disk('public')->delete($oldImageB);
                }
            }
            $menuB->fill($updateB)->save();
        }
        $updatedMenus[] = $menuB->refresh();

        return $updatedMenus;
    }
}

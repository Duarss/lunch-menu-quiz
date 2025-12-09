<?php

namespace Database\Seeders;

use App\Helpers\Project;
use App\Models\Menu;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);
        $currentMonday = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekCode1 = Project::monthWeekCode($currentMonday);
        $weekCode2 = Project::monthWeekCode($currentMonday->copy()->addWeek());

        $days = ['MON', 'TUE', 'WED', 'THU'];

        $dayOffsets = ['MON' => 0, 'TUE' => 1, 'WED' => 2, 'THU' => 3];

        // Seed 2 Vendor A + 2 Vendor B menus per day, sharing one image per catering
        foreach ([$weekCode1, $weekCode2] as $wk) {
            foreach ($days as $day) {
                $menuDate = Project::mondayFromMonthWeekCode($wk)->copy()->addDays($dayOffsets[$day])->toDateString();

                // Vendor A image shared by 2 menus
                $vendorAImage = "https://picsum.photos/640/480?random=" . rand(1, 1000);
                foreach ([1, 2] as $seq) {
                    Menu::updateOrCreate(
                        ['code' => sprintf('%s-%s-%d', $wk, $day, $seq)],
                        [
                            'name' => $seq === 1 ? 'Opsi A' : 'Opsi B',
                            'catering' => 'vendorA',
                            'menu_date' => $menuDate,
                            'image' => $vendorAImage,
                        ]
                    );
                }

                // Vendor B image shared by 2 menus
                $vendorBImage = "https://picsum.photos/640/480?random=" . rand(1, 1000);
                foreach ([3, 4] as $seq) {
                    Menu::updateOrCreate(
                        ['code' => sprintf('%s-%s-%d', $wk, $day, $seq)],
                        [
                            'name' => $seq === 3 ? 'Opsi A' : 'Opsi B',
                            'catering' => 'vendorB',
                            'menu_date' => $menuDate,
                            'image' => $vendorBImage,
                        ]
                    );
                }
            }
        }
    }
}

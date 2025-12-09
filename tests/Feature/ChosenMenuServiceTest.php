<?php

namespace Tests\Feature;

use App\Services\ChosenMenuService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChosenMenuServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function seedMenus(int $count = 6): void
    {
        for ($i = 1; $i <= $count; $i++) {
            DB::table('menus')->insert([
                'code' => 'M' . $i,
                'name' => 'Menu ' . $i,
                'image' => 'img' . $i . '.jpg',
                'description' => 'Desc ' . $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function makeUser(string $username = 'u1'): User
    {
        return User::factory()->create([
            'username' => $username,
            'role' => 'karyawan',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_successful_choice(): void
    {
        $this->seedMenus();
        $user = $this->makeUser('alice');
        $service = app(ChosenMenuService::class);

        $day = Carbon::now()->next(Carbon::MONDAY)->startOfDay();
        $prevWedNoon = $day->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->addDays(2)->setTime(12, 0);

        $options = $service->getDayOptions($day);
        $picked = $options->first();

        $payload = $service->choose($user->id, $picked, $day, $prevWedNoon);

        $this->assertDatabaseHas('chosen_menus', [
            'chosen_by' => $user->id,
            'menu_code' => $picked,
            'chosen_for_day' => $day->toDateString(),
        ]);
    }

    public function test_cannot_choose_twice_same_day(): void
    {
        $this->seedMenus();
        $user = $this->makeUser('bob');
        $service = app(ChosenMenuService::class);

        $day = Carbon::now()->next(Carbon::TUESDAY)->startOfDay();
        $prevWed = $day->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->addDays(2)->setTime(10, 0);
        $picked = $service->getDayOptions($day)->first();

        $service->choose($user->id, $picked, $day, $prevWed);

        $this->expectException(\RuntimeException::class);
        $service->choose($user->id, $picked, $day, $prevWed);
    }

    public function test_cannot_choose_outside_window(): void
    {
        $this->seedMenus();
        $user = $this->makeUser('carl');
        $service = app(ChosenMenuService::class);

        $day = Carbon::now()->next(Carbon::WEDNESDAY)->startOfDay();
        // Outside window: previous week's Tuesday
        $outside = $day->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->addDay()->setTime(9, 0);
        $picked = $service->getDayOptions($day)->first();

        $this->expectException(\RuntimeException::class);
        $service->choose($user->id, $picked, $day, $outside);
    }

    public function test_cannot_choose_menu_not_in_options(): void
    {
        $this->seedMenus();
        $user = $this->makeUser('dina');
        $service = app(ChosenMenuService::class);

        $day = Carbon::now()->next(Carbon::THURSDAY)->startOfDay();
        $prevFri = $day->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->addDays(4)->setTime(16, 0);

        // Pick a code that is not in options (ensure 5th/6th may still be in options). Generate a fake code.
        $invalid = 'NOT_EXIST';

        $this->expectException(\InvalidArgumentException::class);
        $service->choose($user->id, $invalid, $day, $prevFri);
    }
}

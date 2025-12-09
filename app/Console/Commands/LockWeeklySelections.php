<?php

namespace App\Console\Commands;

use App\Helpers\Project;
use App\Models\ChosenMenu;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LockWeeklySelections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menus:lock-weekly-selections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lock karyawan lunch selections for the upcoming week after the selection window closes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);

        // Target upcoming Monday for which selections should now be locked (Mon-Thu)
        $monday = $now->copy()->next(Carbon::MONDAY);
        $rangeStart = $monday->toDateString();
        $rangeEnd = $monday->copy()->addDays(3)->toDateString();

        $affected = ChosenMenu::whereBetween('chosen_for_day', [$rangeStart, $rangeEnd])
            ->where('is_locked', false)
            ->update([
                'is_locked' => true,
                'chosen_at' => Carbon::now($tz),
            ]);

        $weekCode = Project::monthWeekCode($monday);
        Project::closeSelectionWindow($weekCode);

        $this->info("Locked {$affected} selections for week {$rangeStart} to {$rangeEnd}.");

        return Command::SUCCESS;
    }
}

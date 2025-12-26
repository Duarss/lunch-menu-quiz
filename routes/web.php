<?php

use App\Helpers\Project;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatatableController;
use App\Http\Controllers\LunchPickupWindowController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Select2Controller;
use App\Models\ChosenMenu;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('auth.login');
});

Route::middleware(['auth', 'web'])->group(function () {
    Route::get("/main/dashboard", [DashboardController::class, "index"])->name("dashboard");

    Route::apiResource("master/user", UserController::class)
        ->parameters(["master-user" => "masterUser"])
        ->names("masterUser");

    Route::apiResource("master/lunch-pickup-window", LunchPickupWindowController::class)
        ->parameters(["master-lunch-pickup-window" => "masterLunchPickupWindow"])
        ->names("masterLunchPickupWindow");

    Route::get('master/user/{user}/details', [UserController::class, 'viewDetails'])
        ->name('masterUser.details');

    Route::post('master/user/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->name('masterUser.resetPassword');

    // Karyawan self-service password change
    Route::post('karyawan/change-password', [UserController::class, 'changeOwnPassword'])
        ->name('karyawan.change-password');

    Route::apiResource("master/menu", MenuController::class)
        ->parameters(["master-menu" => "masterMenu"])
        ->names("masterMenu");

    Route::post('master/menu/generate-week', [MenuController::class, 'generateWeek'])->name('masterMenu.generateWeek');
    Route::post('master/menu/toggle-window', [MenuController::class, 'toggleSelectionWindow'])->name('masterMenu.toggleWindow');
    Route::get('master/menu/window-status', [MenuController::class, 'windowStatus'])->name('masterMenu.windowStatus');
    Route::post('master/menu/update-image', [MenuController::class, 'updateImage'])->name('masterMenu.updateImage');
    Route::post('vendor/menu/save', [MenuController::class, 'storeVendorMenu'])->name('vendorMenu.store');

    Route::apiResource("master/report", ReportController::class)
        ->parameters(["master-report" => "masterReport"])
        ->names("masterReport");

    Route::post('master/report/{masterReport}/export', [ReportController::class, 'export'])
        ->name('masterReport.export');

    Route::group(["prefix" => "datatables", "as" => "datatables."], function () {
        Route::post("master-user", [DatatableController::class, "masterUser"])->name("master-user");
        Route::post('lunch-windows', [DatatableController::class, 'lunchPickupWindows'])->name('lunch-windows');
    });

    Route::group(["prefix" => "select2", "as" => "select2."], function () {
        Route::get('/karyawan-users', [Select2Controller::class, 'karyawanUsers'])->name('karyawan-users');
        Route::post('/companies', [Select2Controller::class, 'companies'])->name('companies');
    });

    // Admin named route aliases used by dashboard buttons
    Route::prefix('admin')->name('admin.')->group(function () {
        // Bridge to existing User resource index
        Route::get('/users', function () {
            return redirect()->route('masterUser.index');
        })->name('users.index');

        // Bridge to existing Menu resource index
        Route::get('/menus', function () {
            return redirect()->route('masterMenu.index');
        })->name('menus.index');

        // Placeholder routes for reports and analytics (replace with real controllers later)
        Route::get('/reports', function () {
            return redirect()->route('masterReport.index');
        })->name('reports.index');

        // Route::get('/reports/show', function () {
        //     return response('Report detail view (to be implemented)', 200);
        // })->name('reports.show');

        // Route::get('/reports/export', function () {
        //     return response('Reports export (to be implemented)', 200);
        // })->name('reports.export');
    });

    // BM named route placeholders used by BM dashboard
    Route::prefix('bm')->name('bm.')->group(function () {

        Route::get('/users', function () {
            return redirect()->route('masterUser.index');
        })->name('users.index');

        Route::get('/menus', function () {
            return redirect()->route('masterMenu.index');
        })->name('menus.index');

        Route::get('/reports', function () {
            return redirect()->route('masterReport.index');
        })->name('reports.index');

        // Route::get('/reports/{report}', function ($report) {
        //     return response("BM Report detail for $report (to be implemented)", 200);
        // })->name('reports.show');

        // Route::get('/reports/{report}/export', function ($report) {
        //     return response("BM Report export for $report (to be implemented)", 200);
        // })->name('reports.export');
    });

    // Karyawan named route placeholders used by karyawan dashboard
    Route::prefix('karyawan')->name('karyawan.')->group(function () {
        Route::get('master/user/{username}/history', function ($username) {
            $user = auth()->user();
            $tz = config('app.timezone', 'Asia/Jakarta');
            $all = ChosenMenu::with('menu')
                ->where('chosen_by', $user->username)
                ->orderByDesc('chosen_for_day')
                ->get();
            $rows = [];
            $weeks = collect();
            foreach ($all as $cm) {
                $day = Carbon::parse($cm->chosen_for_day, $tz);
                $monday = $day->copy()->startOfWeek(Carbon::MONDAY);
                $weekCode = Project::monthWeekCode($monday);
                $weeks->push($weekCode);
                $rows[] = [
                    'week_code' => $weekCode,
                    'day_label' => $day->format('D'),
                    'menu_name' => $cm->menu?->name ?? '—',
                    'status' => $cm->is_locked ? 'Locked' : 'Saved',
                    'chosen_label' => $cm->chosen_at ? Carbon::parse($cm->chosen_at, $tz)->format('d M Y H:i') : '—',
                ];
            }
            $weeks = $weeks->unique();
            $nextWeekCode = Project::monthWeekCode(Carbon::now($tz)->next(Carbon::MONDAY));
            $title = 'Selection History';
            return view('masters.user.history', compact('title', 'weeks', 'rows', 'nextWeekCode'));
        })->name('history.index');
        Route::post('/selections/{week}', [MenuController::class, 'saveSelections'])->name('selections.save');
    });
});

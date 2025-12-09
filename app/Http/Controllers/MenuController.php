<?php

namespace App\Http\Controllers;

use App\Events\SelectionWindowToggled;
use App\Http\Requests\VendorMenuRequest;
use App\Traits\HasResponse;
use App\Traits\HasTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Helpers\Project;
use App\Models\Menu;
use App\Models\ChosenMenu;
use App\Services\MenuService;
use App\Http\Requests\MenuRequest;

class MenuController extends Controller
{
    use HasTransaction, HasResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $title = 'Master Menu';
        $user = auth()->user();
        $data = app(MenuService::class)->getIndexData($user);
        return view('masters.menu.index', array_merge(['title' => $title], $data));
    }

    public function storeVendorMenu(VendorMenuRequest $request, MenuService $service)
    {
        $user = $request->user();

        if (($user->role ?? 'guest') !== 'vendor') {
            abort(403);
        }

        try {
            $menus = $service->saveVendorMenu($user, $request->validated(), $request->file('image'));
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Menu saved successfully.',
            'menus' => collect($menus)->map(fn($m) => array_merge(
                $service->formatMenu($m),
                [
                    // derive option label (A/B) from sequence using vendorSlotMap
                    'option' => preg_match('/-(\d+)$/', $m->code, $mm)
                        ? (collect($service->vendorSlotMap($m->catering ?? ''))->search((int) $mm[1]) ?: null)
                        : null,
                ]
            ))->values(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(MenuRequest $request, MenuService $service)
    {
        $validated = $request->validated();

        $created = [];

        try {
            if ($request->file('vendor_a_image')) {
                $created = array_merge($created, $service->createCateringPair([
                    'week_code' => $validated['week_code'],
                    'day' => $validated['day'],
                    'catering' => 'vendorA',
                ], $request->file('vendor_a_image')));
            }

            if ($request->file('vendor_b_image')) {
                $created = array_merge($created, $service->createCateringPair([
                    'week_code' => $validated['week_code'],
                    'day' => $validated['day'],
                    'catering' => 'vendorB',
                ], $request->file('vendor_b_image')));
            }
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['menus' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Menus created', 'created' => collect($created)->map(fn($m) => $service->formatMenu($m))]);
        }
        return redirect()->route('masterMenu.index')->with('status', 'Menus created for ' . ($validated['week_code'] ?? 'unknown week') . '.');
    }

    public function updateImage(MenuRequest $request, MenuService $service)
    {
        $validated = $request->validated();

        try {
            $service->updateCateringImage($validated, $request->file('image'));
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['menus' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Menu image updated.']);
        }

        return redirect()->route('masterMenu.index')->with('status', 'Menu image updated.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $menu = Menu::where('code', $id)->firstOrFail();
        $menu->delete();
        return redirect()->route('masterMenu.index')->with('status', 'Menu deleted.');
    }

    /**
     * Generate next week's options from fallback day-only menus.
     */
    public function generateWeek(Request $request, MenuService $service)
    {
        $result = $service->generateNextWeekOptions();
        $created = $result['created'];
        $targetWeekCode = $result['target_week_code'];
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Week generated',
                'created' => $created,
                'target_week_code' => $targetWeekCode,
                'source_week_code' => $result['source_week_code'],
            ]);
        }

        return redirect()->route('masterMenu.index')->with(
            'status',
            "Generated $created menu option(s) for $targetWeekCode."
        );
    }

    /**
     * Allow admin/BM to manually open or close the upcoming selection window without extra migrations.
     */
    public function toggleSelectionWindow(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role ?? 'guest', ['admin', 'bm'])) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:open,close'],
        ]);

        $tz = config('app.timezone', 'Asia/Jakarta');
        $targetMonday = Carbon::now($tz)->next(Carbon::MONDAY)->startOfDay();
        $weekCode = Project::monthWeekCode($targetMonday);

        $now = Carbon::now($tz);
        $ready = false;
        $open = false;

        if ($validated['status'] === 'open') {
            Project::openSelectionWindow($weekCode, $targetMonday);
            $ready = true;
            $open = Project::isSelectionWindowOpen($now);
            $message = "Selection window opened for $weekCode.";
        } else {
            Project::closeSelectionWindow($weekCode);
            $message = "Selection window closed for $weekCode.";
        }

        event(new SelectionWindowToggled(
            weekCode: $weekCode,
            ready: $ready,
            open: $open,
            triggeredBy: $user->username ?? $user->email ?? 'system',
            triggeredAt: $now->toIso8601String(),
        ));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'week_code' => $weekCode,
                'status' => $validated['status'],
            ]);
        }

        return redirect()->route('masterMenu.index')->with('status', $message);
    }

    public function windowStatus(MenuService $service)
    {
        return response()->json($service->getSelectionWindowStatus());
    }

    /**
     * Persist karyawan weekly selections for the upcoming week.
     */
    public function saveSelections(string $week, Request $request, MenuService $service)
    {
        $user = $request->user();
        if (($user->role ?? 'guest') !== 'karyawan') {
            abort(403);
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $targetMonday = Project::mondayFromMonthWeekCode($week, $tz);
        $now = Carbon::now($tz);

        if (!Project::isSelectionWindowReady($week) || !Project::isSelectionWindowOpen($now)) {
            return back()->withErrors(['window' => 'Selection window is not open.']);
        }

        $choices = $request->input('choices', []);
        if (empty($choices) || !is_array($choices)) {
            return back()->withErrors(['choices' => 'No selections received.']);
        }

        try {
            $saved = $service->saveKaryawanSelections($user, $week, $choices, $tz);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['choices' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Selections saved.',
                'saved' => $saved,
            ]);
        }

        return redirect()->route('masterMenu.index')->with('status', 'Selections saved.');
    }
}

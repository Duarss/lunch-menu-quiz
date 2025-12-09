<?php

namespace App\Http\Controllers;

use App\Http\Requests\LunchPickupWindowRequest;
use App\Models\LunchPickupWindow;
use App\Services\LunchPickupWindowService;

class LunchPickupWindowController extends Controller
{
    public function __construct(private LunchPickupWindowService $service) {}

    public function index()
    {
        $this->authorize('viewAny', LunchPickupWindow::class);

        return view('masters.lunch-window.index', [
            'title' => 'Pengaturan Waktu Ambil Lunch',
            'windows' => $this->service->getWindowsForForm(),
            'dayLabels' => LunchPickupWindowService::DAY_LABELS,
        ]);
    }

    public function update(LunchPickupWindowRequest $request)
    {
        $this->authorize('update', LunchPickupWindow::class);

        $this->service->updateWindows($request->validated()['windows']);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan waktu pengambilan lunch berhasil disimpan.',
        ]);
    }
}

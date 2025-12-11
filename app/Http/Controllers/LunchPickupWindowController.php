<?php

namespace App\Http\Controllers;

use App\Http\Requests\LunchPickupWindowRequest;
use App\Models\LunchPickupWindow;
use App\Services\LunchPickupWindowService;
use App\Traits\HasResponse;
use App\Traits\HasTransaction;

class LunchPickupWindowController extends Controller
{
    use HasTransaction, HasResponse;

    public function __construct(private LunchPickupWindowService $service) {}

    public function index()
    {
        $this->authorize('viewAny', LunchPickupWindow::class);

        $windows = $this->service->getWindowsForForm();

        return view('masters.lunch-window.index', [
            'title' => 'Pengaturan Waktu Ambil Lunch',
            'windows' => $windows,
        ]);
    }

    public function store(LunchPickupWindowRequest $request)
    {
        $this->authorize('create', LunchPickupWindow::class);

        $this->service->syncWindowTimes($request->validated()['windows']);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan waktu pengambilan lunch berhasil disimpan.',
            'windows' => $this->service->getWindowsForForm(),
        ]);
    }

    public function show(LunchPickupWindow $window)
    {
        $this->authorize('view', $window);

        return response()->json([
            'success' => true,
            'window' => $window,
        ]);
    }

    public function update(LunchPickupWindowRequest $request, LunchPickupWindow $window, LunchPickupWindowService $service)
    {
        $this->authorize('update', $window);

        return $this->handle(__FUNCTION__, function () use ($request, $service) {
            return $service->updateWindowTimes($request->validated()['windows']);
        });
    }

    public function destroy(string $date)
    {
        $this->authorize('delete', LunchPickupWindow::class);

        return $this->handle(__FUNCTION__, function () use ($date) {
            return $this->service->deleteWindowTimes($date);
        });
    }
}

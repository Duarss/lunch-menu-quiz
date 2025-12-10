<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\BuildAdminDashboardData;
use App\Actions\Dashboard\BuildBMDashboardData;
use App\Actions\Dashboard\BuildKaryawanDashboardData;
use App\Actions\Dashboard\BuildVendorDashboardData;
use App\Traits\HasResponse;
use App\Traits\HasTransaction;
use Illuminate\Http\Request;
use App\Helpers\Project;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use HasTransaction, HasResponse;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);

        // Target week for selection/exports: upcoming week (Mon–Thu)
        $targetMonday = $now->copy()->next(Carbon::MONDAY);
        $weekCode = Project::monthWeekCode($targetMonday);
        $rangeStart = $targetMonday->toDateString();
        $rangeEnd = $targetMonday->copy()->addDays(3)->toDateString();

        return match ($user->role) {
            'admin' => view('dashboard.admin.index', app(BuildAdminDashboardData::class)(
                $rangeStart,
                $rangeEnd,
                $weekCode
            )),
            'bm' => view('dashboard.bm.index', app(BuildBMDashboardData::class)(
                $rangeStart,
                $rangeEnd,
                $weekCode,
                $targetMonday->copy(),
                $now
            )),
            'vendor' => view('dashboard.vendor.index', app(BuildVendorDashboardData::class)(
                $user,
                $weekCode
            )),
            default => view('dashboard.karyawan.index', app(BuildKaryawanDashboardData::class)(
                $user,
                $rangeStart,
                $rangeEnd,
                $weekCode,
                $targetMonday->copy(),
                $now
            )),
        };
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
    public function store(Request $request)
    {
        //
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
        //
    }
}

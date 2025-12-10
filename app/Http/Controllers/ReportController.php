<?php

namespace App\Http\Controllers;

use App\Helpers\Project;
use App\Models\Report;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('viewAny', Report::class);

        $title = 'Master Report';
        $data = $this->reportService->getIndexData();
        $role = auth()->user()->role ?? 'guest';

        return view('masters.report.index', array_merge(['title' => $title, 'role' => $role], $data));
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

    /**
     * Export a weekly report and close the selection window.
     */
    public function export(Request $request, Report $masterReport, ReportExportService $exportService)
    {
        $this->authorize('export', $masterReport);

        $role = $request->user()->role ?? 'guest';
        $tz = config('app.timezone', 'Asia/Jakarta');

        if ($masterReport->exported_at) {
            if ($role === 'admin') {
                return $exportService->streamWeekly($masterReport->code);
            }

            return redirect()
                ->route('masterReport.index')
                ->with('status', 'Report already exported on ' . $masterReport->exported_at->timezone($tz)->format('D, d M Y H:i') . '.');
        }

        $now = Carbon::now($tz);
        $monday = Project::mondayFromMonthWeekCode($masterReport->code, $tz);
        $availableAt = $monday->copy()->subDays(3)->startOfDay();

        if ($now->lessThan($availableAt)) {
            return redirect()
                ->route('masterReport.index')
                ->withErrors(['export' => 'Export opens on ' . $availableAt->format('D, d M Y H:i')]);
        }

        $finalize = $role === 'bm';
        $wasWindowReady = Project::isSelectionWindowReady($masterReport->code);

        if ($finalize) {
            $masterReport->forceFill([
                'exported_at' => $now,
                'exported_by' => $request->user()->username,
            ])->save();

            Project::closeSelectionWindow($masterReport->code);
        }

        try {
            if (!$masterReport->exported_at) {
                $masterReport->forceFill([
                    'exported_by' => $request->user()->username,
                ])->save();
            }

            return $exportService->streamWeekly($masterReport->code);
        } catch (\Throwable $e) {
            if ($finalize) {
                // Roll back export markers if streaming fails.
                $masterReport->forceFill([
                    'exported_at' => null,
                    'exported_by' => null,
                ])->save();
                if ($wasWindowReady) {
                    Project::openSelectionWindow($masterReport->code, $monday);
                }
            }

            throw $e;
        }
    }
}

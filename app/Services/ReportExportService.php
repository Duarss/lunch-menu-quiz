<?php

namespace App\Services;

use App\Actions\Report\StreamWeeklyReport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    // Note: __construct injects the StreamWeeklyReport action
    public function __construct(private StreamWeeklyReport $streamWeeklyReport) {}

    // Note: streamWeekly streams the weekly report for the specified week code
    public function streamWeekly(string $weekCode): StreamedResponse
    {
        return ($this->streamWeeklyReport)($weekCode);
    }
}

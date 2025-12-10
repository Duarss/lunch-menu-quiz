<?php

namespace App\Services;

use App\Actions\Report\StreamWeeklyReport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(private StreamWeeklyReport $streamWeeklyReport) {}

    public function streamWeekly(string $weekCode): StreamedResponse
    {
        return ($this->streamWeeklyReport)($weekCode);
    }
}

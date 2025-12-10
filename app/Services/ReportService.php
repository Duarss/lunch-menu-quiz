<?php

namespace App\Services;

use App\Actions\Report\BuildReportIndexData;

class ReportService
{
    public function __construct(private BuildReportIndexData $buildReportIndexData) {}

    public function getIndexData(): array
    {
        return ($this->buildReportIndexData)();
    }
}

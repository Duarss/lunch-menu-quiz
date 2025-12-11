<?php

namespace App\Services;

use App\Actions\Report\BuildReportIndexData;

class ReportService
{
    // Note: __construct injects the BuildReportIndexData action
    public function __construct(private BuildReportIndexData $buildReportIndexData) {}

    // Note: getIndexData retrieves the report index data as an array
    public function getIndexData(): array
    {
        return ($this->buildReportIndexData)();
    }
}

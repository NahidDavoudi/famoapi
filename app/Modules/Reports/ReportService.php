<?php

namespace App\Modules\Reports;

use App\Core\Pagination;

class ReportService
{
    public function getReports(?string $dateFrom, ?string $dateTo, int $page, int $perPage): array
    {
        $total = Report::countAll($dateFrom, $dateTo);
        $pagination = Pagination::build($page, $perPage, $total);
        $reports = Report::findAll($dateFrom, $dateTo, $pagination['page'], $perPage);
        return ['reports' => $reports, 'pagination' => $pagination];
    }

    public function getStats(): array
    {
        return ['stats' => Report::getStats()];
    }
}
<?php

namespace App\Modules\Reports;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReportController
{
    public function __construct(private ReportService $service) {}

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 20);

        $result = $this->service->getReports($dateFrom, $dateTo, $page, $perPage);

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => ['reports' => $result['reports']],
            'pagination' => $result['pagination'], 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function stats(Request $request, Response $response): Response
    {
        $result = $this->service->getStats();

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }
}
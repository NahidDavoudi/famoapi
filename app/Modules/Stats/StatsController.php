<?php

namespace App\Modules\Stats;

use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Module 6: admin statistics. Protected by the existing admin JWT. The
 * content-reading endpoint additionally requires the content permission.
 */
class StatsController
{
    public function __construct(private StatsService $service)
    {
    }

    public function overview(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();

        return ResponseHelper::json($response, $this->service->reportsOverview(
            $query['from'] ?? null,
            $query['to'] ?? null
        ));
    }

    public function byMajor(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();

        return ResponseHelper::json($response, $this->service->reportsByMajor(
            $query['from'] ?? null,
            $query['to'] ?? null
        ));
    }

    public function bySupporter(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();

        return ResponseHelper::json($response, $this->service->reportsBySupporter(
            $query['from'] ?? null,
            $query['to'] ?? null
        ));
    }

    public function noReport(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 7);
        $page = (int) ($query['page'] ?? 1);
        $perPage = (int) ($query['perPage'] ?? 20);
        $search = isset($query['search']) ? (string) $query['search'] : null;

        $result = $this->service->studentsWithNoReport($days, $page, $perPage, $search);

        return ResponseHelper::json($response, $result['data'], $result['pagination']);
    }

    public function performance(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();

        return ResponseHelper::json($response, $this->service->supporterPerformance(
            $query['from'] ?? null,
            $query['to'] ?? null
        ));
    }

    public function history(Request $request, Response $response, array $args): Response
    {
        $query = $request->getQueryParams();

        return ResponseHelper::json($response, $this->service->studentHistory(
            (int) $args['id'],
            $query['from'] ?? null,
            $query['to'] ?? null
        ));
    }

    public function thread(Request $request, Response $response, array $args): Response
    {
        $query = $request->getQueryParams();
        $day = (string) ($query['day'] ?? $this->today());
        $page = (int) ($query['page'] ?? 1);
        $perPage = (int) ($query['perPage'] ?? 50);

        $result = $this->service->studentThreadContent((int) $args['id'], $day, $page, $perPage);

        return ResponseHelper::json($response, $result['data'], $result['pagination']);
    }

    private function today(): string
    {
        return \App\Modules\Bot\IranDay::today();
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Threads;

use App\Core\ApiException;
use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ThreadController
{
    public function __construct(private ThreadService $service)
    {
    }

    public function sendMessage(Request $request, Response $response): Response
    {
        $actor = $request->getAttribute('bot_actor');
        $body = $request->getParsedBody() ?? [];

        if ($body === [] || (!isset($body['text']) && !isset($body['attachments']))) {
            throw new ApiException('متن پیام یا پیوست الزامی است', 422, 'VALIDATION_ERROR');
        }

        return ResponseHelper::json($response, $this->service->sendStudentMessage($actor, $body), null, 201);
    }

    public function getDay(Request $request, Response $response): Response
    {
        $actor = $request->getAttribute('bot_actor');
        $query = $request->getQueryParams();

        $studentId = isset($query['student_id']) ? (int) $query['student_id'] : null;
        $day       = isset($query['day']) ? (string) $query['day'] : null;
        $page      = (int) ($query['page'] ?? 1);
        $perPage   = (int) ($query['perPage'] ?? 50);

        // دانشجو خودش رو از actor می‌شناسه؛ فقط پشتیبان/ادمین باید student_id بدن
        if ($studentId === null && ($actor['role'] ?? '') !== 'student') {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }

        return ResponseHelper::json(
            $response,
            $this->service->getDay($actor, $studentId, $day, $page, $perPage)
        );
    }

    public function weekly(Request $request, Response $response): Response
    {
        $actor = $request->getAttribute('bot_actor');
        $query = $request->getQueryParams();

        $studentId = isset($query['student_id']) ? (int) $query['student_id'] : null;
        $weekStart = isset($query['week_start']) ? (string) $query['week_start'] : null;

        return ResponseHelper::json(
            $response,
            $this->service->weekly($actor, $studentId, $weekStart)
        );
    }

    public function markRead(Request $request, Response $response): Response
    {
        $actor = $request->getAttribute('bot_actor');
        $body = $request->getParsedBody() ?? [];

        $studentId = isset($body['student_id']) ? (int) $body['student_id'] : null;
        $day       = isset($body['day']) ? (string) $body['day'] : null;

        // دانشجو برای خودش student_id نمی‌فرسته
        if ($studentId === null && ($actor['role'] ?? '') !== 'student') {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }

        return ResponseHelper::json(
            $response,
            $this->service->markRead($actor, $studentId, $day)
        );
    }
}
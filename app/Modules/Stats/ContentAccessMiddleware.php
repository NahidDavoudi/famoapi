<?php

namespace App\Modules\Stats;

use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Second permission level: reading message content. Separate from the admin
 * role and disabled by default. Only admin user ids listed in the
 * ADMIN_CONTENT_READER_IDS env variable (comma-separated) may pass.
 */
final class ContentAccessMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $userId = (int) ($user->sub ?? 0);

        $allowList = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) ($_ENV['ADMIN_CONTENT_READER_IDS'] ?? ''))
        ), static fn ($id) => $id > 0));

        if ($userId <= 0 || !in_array($userId, $allowList, true)) {
            return ResponseHelper::error(
                new Response(),
                'CONTENT_ACCESS_DISABLED',
                'دسترسی به محتوای پیام‌ها فعال نیست',
                403
            );
        }

        return $handler->handle($request);
    }
}

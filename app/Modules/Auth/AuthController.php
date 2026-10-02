<?php

namespace App\Modules\Auth;

use App\Core\Auth;
use App\Core\AuthCookie;
use App\Core\ClientIp;
use App\Core\RateLimitedException;
use App\Core\RateLimiter;
use App\Core\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function __construct(private AuthService $service)
    {
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $validator = new Validator();
        $validator
            ->required('username', 'نام کاربری', $body['username'] ?? null)
            ->required('password', 'رمز عبور', $body['password'] ?? null);

        if (!$validator->passes()) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $validator->firstError(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $username = (string) $body['username'];
        $ip = ClientIp::fromRequest($request);
        $window = $this->envInt('AUTH_LOGIN_WINDOW_SECONDS', 900);
        $lockout = $this->envInt('AUTH_LOGIN_LOCKOUT_SECONDS', 900);
        $userIpKey = $ip !== null ? RateLimiter::key('login:user_ip', $username . '|' . $ip) : null;
        $ipKey = $ip !== null ? RateLimiter::key('login:ip', $ip) : null;
        $userKey = RateLimiter::key('login:user', $username);

        try {
            $this->assertLoginAllowed($userIpKey, $ipKey, $userKey, $window);

            $result = $this->service->login($username, (string) $body['password'], $ip);

            if ($userIpKey !== null) {
                RateLimiter::clear($userIpKey);
            }
            RateLimiter::clear($userKey);
        } catch (RateLimitedException $e) {
            return $this->rateLimitedResponse($response, $e);
        } catch (\App\Core\ApiException $e) {
            if ($userIpKey !== null) {
                RateLimiter::attempt($userIpKey, $this->envInt('AUTH_LOGIN_MAX_ATTEMPTS', 5), $window, $lockout);
            }
            if ($ipKey !== null) {
                RateLimiter::attempt($ipKey, $this->envInt('AUTH_LOGIN_IP_MAX_ATTEMPTS', 30), $window, $window);
            }
            RateLimiter::attempt($userKey, $this->envInt('AUTH_LOGIN_USER_SOFT_MAX', 20), $window, $window);

            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getHttpStatus())->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $status = !empty($result['requires_2fa']) ? 202 : 200;
        $token = $result['token'] ?? null;
        $cookieOnly = $request->getHeaderLine('X-Auth-Mode') === 'cookie';
        if ($cookieOnly) {
            unset($result['token']);
        }
        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        $response = $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
        return is_string($token) ? AuthCookie::issue($response, $request, $token) : $response;
    }

    public function verify2fa(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $validator = new Validator();
        $validator
            ->required('user_id', 'شناسه کاربر', $body['user_id'] ?? null)
            ->required('code', 'کد تأیید', $body['code'] ?? null);

        if (!$validator->passes()) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $validator->firstError(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $userId = (int) $body['user_id'];
        $ttl = $this->envInt('AUTH_2FA_CHALLENGE_TTL', 300);
        $twoFaKey = RateLimiter::key('2fa:user', (string) $userId);

        try {
            $status = RateLimiter::status($twoFaKey, $this->envInt('AUTH_2FA_MAX_ATTEMPTS', 5));
            if (!$status['allowed']) {
                throw new RateLimitedException($status['retry_after'] > 0 ? $status['retry_after'] : $ttl);
            }

            $result = $this->service->verify2fa($userId, $body['code']);

            RateLimiter::clear($twoFaKey);
        } catch (RateLimitedException $e) {
            return $this->rateLimitedResponse($response, $e);
        } catch (\App\Core\ApiException $e) {
            RateLimiter::attempt($twoFaKey, $this->envInt('AUTH_2FA_MAX_ATTEMPTS', 5), $ttl, $ttl);

            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getHttpStatus())->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $token = $result['token'] ?? null;
        $cookieOnly = $request->getHeaderLine('X-Auth-Mode') === 'cookie';
        if ($cookieOnly) {
            unset($result['token']);
        }
        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        return is_string($token) ? AuthCookie::issue($response, $request, $token) : $response;
    }

    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $validator = new Validator();
        $validator
            ->required('phone', 'شماره تلفن', $body['phone'] ?? null)
            ->phone('phone', 'شماره تلفن', $body['phone'] ?? null)
            ->required('password', 'رمز عبور', $body['password'] ?? null)
            ->minLength('password', 'رمز عبور', $body['password'] ?? null, 4)
            ->required('nationalId', 'کد ملی', $body['nationalId'] ?? null)
            ->nationalId('nationalId', 'کد ملی', $body['nationalId'] ?? null)
            ->required('grade', 'پایه تحصیلی', $body['grade'] ?? null)
            ->required('field', 'رشته تحصیلی', $body['field'] ?? null)
            ->required('name', 'نام و نام خانوادگی', $body['name'] ?? null);

        if (isset($body['grade'])) {
            $validator->inArray('grade', 'پایه تحصیلی', (int) $body['grade'], [7, 8, 9, 10, 11, 12]);
        }

        if (!$validator->passes()) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $validator->firstError(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        try {
            $result = $this->service->register($body);
        } catch (\App\Core\ApiException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getHttpStatus())->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $token = $result['token'] ?? null;
        $cookieOnly = $request->getHeaderLine('X-Auth-Mode') === 'cookie';
        if ($cookieOnly) {
            unset($result['token']);
        }
        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        $response = $response->withStatus(201)->withHeader('Content-Type', 'application/json; charset=utf-8');
        return is_string($token) ? AuthCookie::issue($response, $request, $token) : $response;
    }

    public function me(Request $request, Response $response): Response
    {
        $decoded = $request->getAttribute('user');

        try {
            $userData = $this->service->me((int) $decoded->sub);
        } catch (\App\Core\ApiException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getHttpStatus())->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $userData,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function logout(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => null,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return AuthCookie::clear($response->withHeader('Content-Type', 'application/json; charset=utf-8'), $request);
    }

    private function assertLoginAllowed(?string $userIpKey, ?string $ipKey, string $userKey, int $window): void
    {
        $checks = [];
        if ($userIpKey !== null) {
            $checks[] = [$userIpKey, $this->envInt('AUTH_LOGIN_MAX_ATTEMPTS', 5)];
        }
        if ($ipKey !== null) {
            $checks[] = [$ipKey, $this->envInt('AUTH_LOGIN_IP_MAX_ATTEMPTS', 30)];
        }
        $checks[] = [$userKey, $this->envInt('AUTH_LOGIN_USER_SOFT_MAX', 20)];

        foreach ($checks as [$key, $max]) {
            $status = RateLimiter::status($key, $max);
            if (!$status['allowed']) {
                throw new RateLimitedException($status['retry_after'] > 0 ? $status['retry_after'] : $window);
            }
        }
    }

    private function rateLimitedResponse(Response $response, RateLimitedException $e): Response
    {
        $response->getBody()->write(json_encode([
            'success'    => false,
            'data'       => null,
            'pagination' => null,
            'error'      => [
                'code'    => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ],
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($e->getHttpStatus())
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Retry-After', (string) $e->getRetryAfter());
    }

    private function envInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return max(1, (int) $value);
    }
}

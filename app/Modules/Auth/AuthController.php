<?php

namespace App\Modules\Auth;

use App\Core\Auth;
use App\Core\AuthCookie;
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

        try {
            $result = $this->service->login($body['username'], $body['password']);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'AUTH_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 401)->withHeader('Content-Type', 'application/json; charset=utf-8');
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

        try {
            $result = $this->service->verify2fa(
                (int) $body['user_id'],
                $body['code']
            );
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => '2FA_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 401)->withHeader('Content-Type', 'application/json; charset=utf-8');
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
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'REGISTRATION_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 409)->withHeader('Content-Type', 'application/json; charset=utf-8');
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
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'USER_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 404)->withHeader('Content-Type', 'application/json; charset=utf-8');
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
}

<?php

namespace App\Core;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthCookie
{
    public static function name(): string
    {
        return trim((string) ($_ENV['JWT_COOKIE_NAME'] ?? 'famo_jwt')) ?: 'famo_jwt';
    }

    public static function tokenFromRequest(Request $request): ?string
    {
        $token = $request->getCookieParams()[self::name()] ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function isPresent(Request $request): bool
    {
        return self::tokenFromRequest($request) !== null;
    }

    public static function allowsCookieWrite(Request $request): bool
    {
        $origin = rtrim($request->getHeaderLine('Origin'), '/');
        if ($origin === '') {
            return false;
        }

        $origins = self::allowedOrigins();

        return in_array($origin, $origins, true);
    }

    public static function allowedOrigins(): array
    {
        $origins = array_values(array_filter(array_map(
            static fn (string $item): string => rtrim(trim($item), '/'),
            explode(',', $_ENV['CORS_ORIGINS'] ?? '')
        )));
        if ($origins === []) {
            $origins = [
                'https://famoacademy.ir',
                'https://admin.famoacademy.ir',
                'https://dash.famoacademy.ir',
                'https://auth.famoacademy.ir',
                'http://localhost',
            ];
        }

        return $origins;
    }

    public static function issue(Response $response, Request $request, string $token): Response
    {
        $ttl = max(1, (int) ($_ENV['JWT_TTL'] ?? 86400));
        return $response->withAddedHeader('Set-Cookie', self::cookieHeader($request, $token, time() + $ttl, $ttl));
    }

    public static function clear(Response $response, Request $request): Response
    {
        return $response->withAddedHeader('Set-Cookie', self::cookieHeader($request, '', 1, 0));
    }

    private static function cookieHeader(Request $request, string $value, int $expires, int $maxAge): string
    {
        $name = self::name();
        $cookie = rawurlencode($name) . '=' . rawurlencode($value);
        $cookie .= '; Path=/; Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT; Max-Age=' . $maxAge;

        $host = strtolower($request->getUri()->getHost());
        $domain = trim((string) ($_ENV['JWT_COOKIE_DOMAIN'] ?? ''));
        if ($domain === '' && preg_match('/(^|\.)famoacademy\.ir$/i', $host)) {
            $domain = '.famoacademy.ir';
        }
        if ($domain !== '') {
            $cookie .= '; Domain=' . $domain;
        }

        $origin = parse_url($request->getHeaderLine('Origin'));
        $originHost = strtolower((string) ($origin['host'] ?? ''));
        $localHostRequest = in_array($originHost, ['localhost', '127.0.0.1'], true)
            && in_array($host, ['localhost', '127.0.0.1'], true);
        $cookieIsCrossSite = !$localHostRequest
            && $originHost !== ''
            && !str_ends_with($originHost, '.famoacademy.ir')
            && !str_ends_with($host, '.localhost');
        $sameSiteOverride = array_key_exists('JWT_COOKIE_SAMESITE', $_ENV)
            && trim((string) $_ENV['JWT_COOKIE_SAMESITE']) !== '';
        $sameSiteSetting = trim((string) ($_ENV['JWT_COOKIE_SAMESITE'] ?? ''));
        $sameSite = ucfirst(strtolower($sameSiteSetting !== '' ? $sameSiteSetting : ($cookieIsCrossSite ? 'None' : 'Lax')));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }

        $secureSettingPresent = array_key_exists('JWT_COOKIE_SECURE', $_ENV)
            && trim((string) $_ENV['JWT_COOKIE_SECURE']) !== '';
        $secureSetting = trim((string) ($_ENV['JWT_COOKIE_SECURE'] ?? ''));
        $secure = $secureSettingPresent
            ? filter_var($secureSetting, FILTER_VALIDATE_BOOLEAN)
            : ($domain !== '' || strtolower($request->getUri()->getScheme()) === 'https' || $cookieIsCrossSite);
        // SameSite=None is rejected by browsers unless the cookie is Secure.
        $secure = $secure || $sameSite === 'None';
        if ($secure) {
            $cookie .= '; Secure';
        }

        $cookie .= '; HttpOnly';
        $cookie .= '; SameSite=' . $sameSite;

        return $cookie;
    }
}

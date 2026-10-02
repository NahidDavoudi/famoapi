<?php
declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface;

final class ClientIp
{
    public static function fromRequest(ServerRequestInterface $request): ?string
    {
        $remote = trim((string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''));
        $header = trim((string) ($_ENV['TRUSTED_PROXY_HEADER'] ?? ''));
        $trusted = self::trustedProxies();

        $client = $remote;
        if ($header !== '' && $remote !== '' && self::isTrusted($remote, $trusted)) {
            $first = trim((string) (explode(',', $request->getHeaderLine($header))[0] ?? ''));
            if ($first !== '') {
                $client = $first;
            }
        }

        if ($client === '' || filter_var($client, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $client;
    }

    /** @return list<string> */
    private static function trustedProxies(): array
    {
        $raw = (string) ($_ENV['TRUSTED_PROXIES'] ?? '');
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($v) => $v !== ''));
    }

    private static function isTrusted(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bitsRaw] = explode('/', $cidr, 2);
        $bits = (int) $bitsRaw;
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($subnet);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin) || $bits < 0 || $bits > strlen($ipBin) * 8) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }
}

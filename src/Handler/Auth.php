<?php
declare(strict_types=1);

namespace MyTDX;

class AuthHandler
{
    private const MAX_TRIES = 5;          // allowed failures per window
    private const WINDOW_SEC = 300;       // 5 minute window
    private const LOCKOUT_SEC = 900;      // 15 minute lockout

    public static function login(): void
    {
        $password = $_POST['password'] ?? '';
        $needAuth = (Config::get('password_hash') !== '') ? 1 : 0;

        if (!$needAuth) {
            jsonExit(['logged' => 0, 'requires_2fa' => 0, 'disabled' => 1]);
        }

        $storedHash = Config::get('password_hash', '');
        if ($storedHash === '') {
            jsonExit(['logged' => 0, 'requires_2fa' => 0]);
        }

        $ip = self::clientIp();
        if (($lockRemaining = self::isLocked($ip)) > 0) {
            jsonExit(['logged' => 0, 'requires_2fa' => 0, 'locked' => 1, 'retryAfter' => $lockRemaining]);
        }

        if (password_verify($password, $storedHash)) {
            self::resetAttempts($ip);
            session_regenerate_id(true);
            $_SESSION['logged'] = 1;

            if (is_2fa_required()) {
                $_SESSION['2fa_pending'] = true;
                $_SESSION['2fa_verified'] = false;
                jsonExit(['logged' => 0, 'requires_2fa' => 1]);
            }

            $_SESSION['2fa_verified'] = true;
            $_SESSION['2fa_pending'] = false;
            jsonExit(['logged' => 1, 'requires_2fa' => 0]);
        }

        // Record failure; throttle
        self::recordFailure($ip);
        if (self::isLocked($ip) > 0) {
            jsonExit(['logged' => 0, 'requires_2fa' => 0, 'locked' => 1]);
        }
        usleep(200000); // 200ms delay
        jsonExit(['logged' => 0, 'requires_2fa' => 0]);
    }

    private static function throttleDir(): string
    {
        return dirname(__DIR__, 2) . '/tmp/login';
    }

    private static function throttleFile(string $ip): string
    {
        return self::throttleDir() . '/' . hash('sha256', $ip) . '.json';
    }

    private static function clientIp(): string
    {
        // Only trust REMOTE_ADDR (never unvalidated proxy headers) to prevent spoofing.
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private static function readAttempts(string $ip): array
    {
        $file = self::throttleFile($ip);
        if (!is_file($file)) return ['count' => 0, 'first' => 0, 'lockUntil' => 0];
        $raw = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : null;
        if (!is_array($data)) return ['count' => 0, 'first' => 0, 'lockUntil' => 0];
        return $data;
    }

    private static function writeAttempts(string $ip, array $data): void
    {
        $dir = self::throttleDir();
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        $file = self::throttleFile($ip);
        $tmp = $file . '.tmp';
        $ok = @file_put_contents($tmp, json_encode($data), LOCK_EX);
        if ($ok !== false) { @rename($tmp, $file); }
    }

    /** Returns seconds remaining in lockout, or 0 if not locked. */
    private static function isLocked(string $ip): int
    {
        $data = self::readAttempts($ip);
        $lockUntil = (int)($data['lockUntil'] ?? 0);
        $now = time();
        if ($lockUntil > $now) {
            return $lockUntil - $now;
        }
        // Window expired -> reset unless lock is active
        $first = (int)($data['first'] ?? 0);
        if ($first > 0 && ($now - $first) > self::WINDOW_SEC) {
            self::writeAttempts($ip, ['count' => 0, 'first' => 0, 'lockUntil' => 0]);
            return 0;
        }
        return 0;
    }

    private static function recordFailure(string $ip): void
    {
        $data = self::readAttempts($ip);
        $now = time();
        $first = (int)($data['first'] ?? 0);
        if ($first === 0 || ($now - $first) > self::WINDOW_SEC) {
            $first = $now;
            $data['count'] = 0;
        }
        $data['count'] = (int)($data['count'] ?? 0) + 1;
        $data['first'] = $first;
        if ($data['count'] >= self::MAX_TRIES) {
            $data['lockUntil'] = $now + self::LOCKOUT_SEC;
        }
        self::writeAttempts($ip, $data);
    }

    private static function resetAttempts(string $ip): void
    {
        $file = self::throttleFile($ip);
        if (is_file($file)) { @unlink($file); }
    }

    public static function verify2fa(): void
    {
        $code = $_POST['code'] ?? '';
        if (!is_2fa_pending()) {
            jsonExit(['verified' => 0, 'logged' => 0]);
        }

        $secret = Config::get('totp_secret', '');
        if ($secret === '') {
            jsonExit(['verified' => 0, 'logged' => 0]);
        }

        if (\TOTP::verify($secret, $code)) {
            session_regenerate_id(true);
            $_SESSION['2fa_verified'] = true;
            $_SESSION['2fa_pending'] = false;
            jsonExit(['verified' => 1, 'logged' => 1]);
        }

        jsonExit(['verified' => 0, 'logged' => 0]);
    }

    public static function logout(): void
    {
        $_SESSION['logged'] = false;
        $_SESSION['2fa_pending'] = false;
        $_SESSION['2fa_verified'] = false;
        session_regenerate_id(true);
        jsonExit(['logged' => 0]);
    }

    public static function generate2faSecret(): void
    {
        $needAuth = (Config::get('password_hash') !== '') ? 1 : 0;
        if ($needAuth && !is_logged()) {
            jsonExit(['error' => 'Not authenticated']);
        }

        $secret = \TOTP::generateSecret();
        $uri = \TOTP::getProvisioningUri(
            Config::get('title', 'myTDX'),
            'user',
            $secret
        );

        jsonExit(['secret' => $secret, 'uri' => $uri]);
    }

    public static function save2faSettings(): void
    {
        $needAuth = (Config::get('password_hash') !== '') ? 1 : 0;
        if ($needAuth && !is_logged()) {
            jsonExit(['error' => 'Not authenticated']);
        }

        $enabled = (int)($_POST['totp_enabled'] ?? 0);
        $secret = $_POST['totp_secret'] ?? '';

        if ($enabled && $secret === '') {
            jsonExit(['error' => 'Secret is required when enabling 2FA']);
        }

        Config::set('totp_enabled', $enabled ? 1 : 0);
        Config::set('totp_secret', $secret);
        Config::save();

        jsonExit(['saved' => 1, 'totp_enabled' => $enabled]);
    }
}

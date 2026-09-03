<?php
declare(strict_types=1);

namespace MyTDX;

class Csrf
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    public static function generate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = $token;
        return $token;
    }

    public static function token(): string
    {
        if (empty($_SESSION[self::TOKEN_NAME])) {
            return self::generate();
        }
        return $_SESSION[self::TOKEN_NAME];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars(self::token()) . '" />';
    }

    public static function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        $sessionToken = $_SESSION[self::TOKEN_NAME] ?? '';
        if ($sessionToken === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    public static function check(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[self::TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!self::verify($token)) {
                http_response_code(403);
                exit(json_encode(['error' => 'Invalid CSRF token']));
            }
        }
    }
}

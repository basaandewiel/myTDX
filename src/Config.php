<?php
declare(strict_types=1);

namespace MyTDX;

class Config
{
    private static array $config = [];
    private static string $path = '';
    private static array $defaults = [
        'db' => 'sqlite',
        'mysql.host' => 'localhost',
        'mysql.db' => 'mytinytodo',
        'mysql.user' => 'user',
        'mysql.password' => '',
        'prefix' => 'mtt_',
        'url' => '',
        'title' => 'myTDX',
        'lang' => 'en',
        'password_hash' => '',
        'totp_enabled' => 0,
        'totp_secret' => '',
        'smartsyntax' => 1,
        'markdown' => 0,
        'timezone' => 'UTC',
        'autotag' => 1,
        'firstdayofweek' => 1,
        'clock' => 24,
        'dateformat' => 'j M Y',
        'dateformat2' => 'Y-m-d',
        'dateformatshort' => 'j M',
        'template' => 'default',
        'showdate' => 1,
        'alientags' => 1,
        'taskxrefs' => 1,
        'dbbackup' => 30,
    ];

    public static function load(string $path): void
    {
        self::$path = $path;
        if (file_exists($path)) {
            $json = file_get_contents($path);
            $data = json_decode($json, true);
            if (is_array($data)) {
                self::$config = $data;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$config)) {
            return self::$config[$key];
        }
        if (array_key_exists($key, self::$defaults)) {
            return self::$defaults[$key];
        }
        return $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$config[$key] = $value;
    }

    public static function save(): bool
    {
        $dir = dirname(self::$path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $tmp = self::$path . '.tmp';
        $fp = fopen($tmp, 'w');
        if ($fp === false) {
            return false;
        }
        fwrite($fp, json_encode(self::$config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fclose($fp);
        rename($tmp, self::$path);
        chmod(self::$path, 0640);
        return true;
    }

    public static function all(): array
    {
        return self::$config + self::$defaults;
    }

    public static function isInstalled(): bool
    {
        return file_exists(self::$path);
    }
}

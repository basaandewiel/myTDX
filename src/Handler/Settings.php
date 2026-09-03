<?php
declare(strict_types=1);

namespace MyTDX;

class SettingsHandler
{
    private static function requireAuth(): void
    {
        $needAuth = (Config::get('password_hash') !== '') ? 1 : 0;
        if (!$needAuth && !is_logged()) {
            return;
        }
        if (!is_logged()) {
            jsonExit(['error' => 'Not authenticated']);
        }
    }

    public static function handleSave(): void
    {
        self::requireAuth();

        $fields = [
            'title' => 's',
            'lang' => 's',
            'password' => 's',
            'smartsyntax' => 'i',
            'markdown' => 'i',
            'timezone' => 's',
            'autotag' => 'i',
            'firstdayofweek' => 'i',
            'clock' => 'i',
            'dateformat' => 's',
            'dateformat2' => 's',
            'dateformatshort' => 's',
            'template' => 's',
            'showdate' => 'i',
            'alientags' => 'i',
            'taskxrefs' => 'i',
            'dbbackup' => 'i',
            'totp_enabled' => 'i',
        ];

        foreach ($fields as $field => $type) {
            if (!isset($_POST[$field])) continue;

            $value = $_POST[$field];

            if ($type === 'i') {
                $value = (int)$value;
                Config::set($field, $value);
            } else {
                $value = trim((string)$value);
                if ($field === 'template') {
                    // Whitelist template names to prevent path traversal
                    $allowedTemplates = ['default'];
                    $templateDir = dirname(__DIR__) . '/themes';
                    foreach (glob($templateDir . '/*', GLOB_ONLYDIR) as $dir) {
                        $allowedTemplates[] = basename($dir);
                    }
                    if (!in_array($value, $allowedTemplates, true)) {
                        $value = 'default';
                    }
                    Config::set($field, $value);
                } elseif ($field === 'lang') {
                    // Whitelist language files
                    $langFile = dirname(__DIR__) . '/lang/' . $value . '.php';
                    if (!preg_match('/^[a-z-]+$/i', $value) || !file_exists($langFile)) {
                        $value = 'en';
                    }
                    Config::set($field, $value);
                } else {
                    // Strip control characters except newlines, escape single quotes
                    $value = str_replace(["\0", "\r"], '', $value);
                    Config::set($field, $value);
                }
            }
        }

        // Handle password
        if (isset($_POST['password'])) {
            $newPass = (string)($_POST['password'] ?? '');
            $newPass = rtrim($newPass, " \t");
            if ($newPass !== '') {
                Config::set('password_hash', password_hash($newPass, PASSWORD_DEFAULT));
            } elseif (isset($_POST['clearpassword']) && (int)$_POST['clearpassword'] === 1) {
                Config::set('password_hash', '');
            }
        }

        $ok = Config::save();
        jsonExit(['saved' => $ok ? 1 : 0]);
    }

    public static function getLanguageOptions(): array
    {
        $dir = dirname(__DIR__) . '/lang';
        $options = [];
        foreach (glob($dir . '/*.php') as $file) {
            $name = basename($file, '.php');
            if ($name === 'class.default') continue;
            $options[$name] = $name;
        }
        return $options;
    }

    public static function getTimezoneOptions(): array
    {
        $list = timezone_identifiers_list();
        $current = Config::get('timezone', 'UTC');
        $options = [];
        foreach ($list as $tz) {
            $options[$tz] = $tz;
        }
        return $options;
    }
}
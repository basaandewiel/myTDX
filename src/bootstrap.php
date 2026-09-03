<?php
declare(strict_types=1);

namespace MyTDX;

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/global_helpers.php';

// Load API handlers
foreach (glob(__DIR__ . '/Handler/*.php') as $handlerFile) {
    require_once $handlerFile;
}

// Load TOTP
require_once dirname(__DIR__) . '/class.totp.php';

// Load language
$langDir = dirname(__DIR__) . '/lang/';
$lang = Config::get('lang', 'en');
if (!preg_match('/^[a-z-]+$/i', $lang)) {
    $lang = 'en';
}
$langClassFile = $langDir . 'class.default.php';
if (file_exists($langClassFile)) {
    require_once $langClassFile;
}
$langFile = $langDir . $lang . '.php';
if (file_exists($langFile)) {
    require_once $langFile;
}

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    $savePath = dirname(__DIR__) . '/tmp/sessions';
    if (is_dir($savePath)) {
        session_save_path($savePath);
    }

    session_name('mtt-session');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '86400'); // 24 hours
    ini_set('session.cookie_lifetime', '0'); // Until browser closes
    session_start();
}

// CSRF protection for state-changing requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    // Skip CSRF for login (no session yet) and verify_2fa (during 2FA flow)
    if (!in_array($action, ['login', 'verify_2fa', 'logout'], true)) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::verify($token)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }
}

$needAuth = (Config::get('password_hash') !== '') ? 1 : 0;

function is_logged(): bool
{
    if (!isset($_SESSION['logged']) || !$_SESSION['logged']) return false;
    if (is_2fa_required() && !is_2fa_verified()) return false;
    return true;
}

function is_readonly(): bool
{
    $needAuth = (Config::get('password_hash') !== '') ? 1 : 0;
    return $needAuth && !is_logged();
}

function is_2fa_required(): bool
{
    return Config::get('totp_enabled') && Config::get('totp_secret') !== '';
}

function is_2fa_pending(): bool
{
    return isset($_SESSION['2fa_pending']) && $_SESSION['2fa_pending'] === true;
}

function is_2fa_verified(): bool
{
    return isset($_SESSION['2fa_verified']) && $_SESSION['2fa_verified'] === true;
}

function getDB(): Database
{
    static $db = null;
    if ($db !== null) return $db;

    $type = Config::get('db', 'sqlite');
    if ($type === 'sqlite') {
        $path = dirname(__DIR__) . '/db/todolist.db';
        $db = new Database('sqlite:' . $path);
    } elseif ($type === 'mysql') {
        $host = Config::get('mysql.host', 'localhost');
        $dbName = Config::get('mysql.db', 'mytinytodo');
        $user = Config::get('mysql.user', 'user');
        $pass = Config::get('mysql.password', '');
        $db = new Database("mysql:host=$host;dbname=$dbName;charset=utf8", $user, $pass);
        $db->ex("SET NAMES utf8");
    } else {
        die('Not installed. Run <a href="setup.php">setup.php</a> first.');
    }
    $db->prefix = Config::get('prefix', 'mtt_');
    return $db;
}

function check_read_access(int $listId = 0): void
{
    if (!is_readonly()) return;
    if ($listId > 0) {
        $db = getDB();
        $row = $db->sqa("SELECT published FROM " . $db->table('lists') . " WHERE id=?", [$listId]);
        if ($row && $row['published']) return;
    }
    jsonExit(['total' => 0, 'list' => [], 'denied' => 1]);
}

function have_write_access(int $listId = 0): bool
{
    if (is_readonly()) return false;
    if ($listId > 0) {
        $db = getDB();
        $row = $db->sq("SELECT COUNT(*) FROM " . $db->table('lists') . " WHERE id=?", [$listId]);
        if (!$row) return false;
    }
    return true;
}

function check_write_access(int $listId = 0): void
{
    if (!have_write_access($listId)) {
        jsonExit(['total' => 0, 'list' => [], 'denied' => 1]);
    }
}

function __(string $key): string
{
    return \Lang::instance()->get($key);
}

function _e(string $key): void
{
    echo \Lang::instance()->get($key);
}

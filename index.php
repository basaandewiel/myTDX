<?php
declare(strict_types=1);

use MyTDX\Config;

define('MTTPATH', dirname(__FILE__) . '/');

require_once MTTPATH . 'src/Config.php';
Config::load(MTTPATH . 'db/config.json');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (!Config::isInstalled()) {
    header('Location: setup.php');
    exit;
}

date_default_timezone_set(Config::get('timezone', 'UTC'));

require_once MTTPATH . 'src/bootstrap.php';

// Base URL
$baseUrl = MyTDX\getBaseUrl();

$options = [
    'mttUrl' => $baseUrl,
    'templateUrl' => $baseUrl . 'themes/' . Config::get('template', 'default') . '/',
    'needAuth' => (Config::get('password_hash') !== '') ? true : false,
    'isLogged' => ($needAuth && MyTDX\is_logged()) ? true : false,
    'isReadonly' => MyTDX\is_readonly(),
    'csrfToken' => MyTDX\Csrf::token(),
    'showdate' => Config::get('showdate', 1) ? true : false,
    'alientags' => Config::get('alientags', 1) ? true : false,
    'taskxrefs' => Config::get('taskxrefs', 1) ? true : false,
    'autotag' => Config::get('autotag', 1) ? true : false,
    'markdown' => Config::get('markdown', 0) ? true : false,
    'smartsyntax' => Config::get('smartsyntax', 1) ? true : false,
    'totp_enabled' => MyTDX\is_2fa_required() ? true : false,
    'currency' => null,
];

// External search from URL
$externalSearch = '';
if (isset($_GET['i'])) {
    $externalSearch = '#' . (int)$_GET['i'];
} elseif (isset($_GET['s'])) {
    $externalSearch = (string)$_GET['s'];
}

// Open specific list
$openList = isset($_GET['list']) ? (int)$_GET['list'] : null;

$title = Config::get('title', 'myTDX');
$themeDir = $baseUrl . 'themes/' . Config::get('template', 'default') . '/';

$pda = isset($_GET['pda']);

$langData = \Lang::instance()->makeJS();

require_once MTTPATH . 'themes/' . Config::get('template', 'default') . '/index.php';
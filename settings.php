<?php
declare(strict_types=1);

use MyTDX\Config;

define('MTTPATH', dirname(__FILE__) . '/');

require_once MTTPATH . 'src/Config.php';
Config::load(MTTPATH . 'db/config.json');

require_once MTTPATH . 'src/bootstrap.php';
require_once MTTPATH . 'src/Handler/Settings.php';

// The settings UI is rendered client-side (js/app.js -> loadSettingsPage()).
// This endpoint only handles POST saves and redirects GET requests to the app.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['save'] ?? '') === 'ajax') {
        MyTDX\SettingsHandler::handleSave();
    }
    // Bootstrap already validated the CSRF token for POST requests.
    MyTDX\jsonExit(['saved' => 0]);
}

header('Location: index.php');
exit;
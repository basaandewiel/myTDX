<?php
declare(strict_types=1);

use MyTDX\Config;
use MyTDX\ListsHandler;
use MyTDX\TasksHandler;
use MyTDX\TagsHandler;
use MyTDX\AuthHandler;
use MyTDX\SettingsHandler;

define('MTTPATH', dirname(__FILE__) . '/');

// Load config
require_once MTTPATH . 'src/Config.php';
Config::load(MTTPATH . 'db/config.json');

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; connect-src \'self\'');

// Check if installed
if (!Config::isInstalled()) {
    MyTDX\jsonExit(['error' => 'Not installed. Run setup.php first.']);
}

// Bootstrap (session, auth, lang, db)
require_once MTTPATH . 'src/bootstrap.php';

// Add CSRF token to responses for AJAX requests
if (isset($_GET['csrf'])) {
    MyTDX\jsonExit(['csrf_token' => MyTDX\Csrf::token()]);
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // Lists
        case 'loadLists':
            ListsHandler::loadLists();
            break;
        case 'addList':
            ListsHandler::addList();
            break;
        case 'renameList':
            ListsHandler::renameList();
            break;
        case 'deleteList':
            ListsHandler::deleteList();
            break;
        case 'setSort':
            ListsHandler::setSort();
            break;
        case 'publishList':
            ListsHandler::publishList();
            break;
        case 'changeListOrder':
            ListsHandler::changeListOrder();
            break;
        case 'setShowNotesInList':
            ListsHandler::setShowNotesInList();
            break;
        case 'setHideList':
            ListsHandler::setHideList();
            break;
        case 'clearCompletedInList':
            ListsHandler::clearCompletedInList();
            break;

        // Tasks
        case 'loadTasks':
            TasksHandler::loadTasks();
            break;
        case 'newTask':
            TasksHandler::newTask();
            break;
        case 'fullNewTask':
            TasksHandler::fullNewTask();
            break;
        case 'editTask':
            TasksHandler::editTask();
            break;
        case 'deleteTask':
            TasksHandler::deleteTask();
            break;
        case 'completeTask':
            TasksHandler::completeTask();
            break;
        case 'editNote':
            TasksHandler::editNote();
            break;
        case 'setPrio':
            TasksHandler::setPrio();
            break;
        case 'setDuedate':
            TasksHandler::setDuedate();
            break;
        case 'changeOrder':
            TasksHandler::changeOrder();
            break;
        case 'moveTask':
            TasksHandler::moveTask();
            break;
        case 'parseTaskStr':
            TasksHandler::parseTaskStr();
            break;

        // Tags
        case 'tagCloud':
            TagsHandler::tagCloud();
            break;
        case 'suggestTags':
            TagsHandler::suggestTags();
            break;

        // Auth
        case 'login':
            AuthHandler::login();
            break;
        case 'verify_2fa':
            AuthHandler::verify2fa();
            break;
        case 'logout':
            AuthHandler::logout();
            break;
        case 'generate_2fa_secret':
            AuthHandler::generate2faSecret();
            break;
        case 'save_2fa_settings':
            AuthHandler::save2faSettings();
            break;

        // Settings
        case '_settings':
            SettingsHandler::handleSave();
            break;

        default:
            MyTDX\jsonExit(['error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    MyTDX\jsonExit(['error' => 'Internal server error']);
}
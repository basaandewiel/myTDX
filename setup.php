<?php
declare(strict_types=1);

use MyTDX\Config;

define('MTTPATH', dirname(__FILE__) . '/');

require_once MTTPATH . 'src/Config.php';

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$configPath = MTTPATH . 'db/config.json';
$dbPath = MTTPATH . 'db/todolist.db';

// Legacy config migration
if (!file_exists($configPath) && file_exists(MTTPATH . 'db/config.php')) {
    include MTTPATH . 'db/config.php';
    if (isset($config) && is_array($config)) {
        $mapped = [];
        foreach ($config as $k => $v) {
            if ($k === 'password') {
                if ($v !== '') {
                    $mapped['password_hash'] = password_hash($v, PASSWORD_DEFAULT);
                }
                continue;
            }
            $mapped[$k] = $v;
        }
        if (!isset($mapped['markdown'])) $mapped['markdown'] = 0;
        if (!isset($mapped['showdate'])) $mapped['showdate'] = 1;
        if (!isset($mapped['alientags'])) $mapped['alientags'] = 1;
        if (!isset($mapped['taskxrefs'])) $mapped['taskxrefs'] = 1;
        $mapped['template'] = 'default';
        file_put_contents($configPath, json_encode($mapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        chmod($configPath, 0640);
    }
}

Config::load($configPath);

// Guard: if already installed with a password, block unauthenticated reconfiguration.
$installedWithPassword = Config::isInstalled() && Config::get('password_hash', '') !== '';
if ($installedWithPassword && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_name('mtt-session');
    session_start();
    $authed = !empty($_SESSION['logged'])
        && (empty($_SESSION['2fa_pending'])
            || (Config::get('totp_secret', '') === '' && Config::get('totp_enabled', 0) == 0)
            || !empty($_SESSION['2fa_verified']));
    if (!$authed) {
        http_response_code(403);
        exit('Access denied: reconfiguration requires an active admin session.');
    }
}

$errors = [];
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$configInstalled = Config::isInstalled();
$dbExists = file_exists($dbPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? 'myTDX');
    $lang = trim($_POST['lang'] ?? 'en');
    $password = (string)($_POST['password'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'UTC');

    if ($title === '') $title = 'myTDX';
    if (!preg_match('/^[a-z-]+$/i', $lang) || !file_exists(MTTPATH . "lang/$lang.php")) {
        $lang = 'en';
    }
    $validTz = in_array($timezone, timezone_identifiers_list(), true);
    if (!$validTz) $timezone = 'UTC';

    $newConfig = [
        'db' => 'sqlite',
        'prefix' => (Config::get('prefix', 'mtt_') ?: 'mtt_'),
        'title' => $title,
        'lang' => $lang,
        'timezone' => $timezone,
        'smartsyntax' => 1,
        'markdown' => 0,
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

    if ($password !== '') {
        $newConfig['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    } else {
        $newConfig['password_hash'] = (Config::get('password_hash', '') ?: '');
    }

    // Merge with existing if present
    foreach (Config::all() as $k => $v) {
        if (!array_key_exists($k, $newConfig)) {
            $newConfig[$k] = $v;
        }
    }

    // Write the merged configuration to db/config.json
    $ok = file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($ok === false) {
        $errors[] = 'Failed to write config file. Please check permissions on db/ directory.';
    } else {
        chmod($configPath, 0640);

        // Ensure the config was saved
        // (Re)build database schema
        try {
            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $prefix = $newConfig['prefix'];

            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array($prefix . 'lists', $tables)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}lists (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CHAR(36) NOT NULL DEFAULT '',
                    ow INTEGER NOT NULL DEFAULT 0,
                    name VARCHAR(50) NOT NULL DEFAULT '',
                    d_created INTEGER UNSIGNED NOT NULL DEFAULT 0,
                    d_edited INTEGER UNSIGNED NOT NULL DEFAULT 0,
                    sorting TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    published TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    taskview INTEGER UNSIGNED NOT NULL DEFAULT 0
                )");
                $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS {$prefix}lists_uuid ON {$prefix}lists(uuid)");
            }

            if (!in_array($prefix . 'todolist', $tables)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}todolist (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CHAR(36) NOT NULL DEFAULT '',
                    list_id INTEGER UNSIGNED NOT NULL DEFAULT 0,
                    d_created INTEGER UNSIGNED NOT NULL DEFAULT 0,
                    d_completed INTEGER UNSIGNED NOT NULL DEFAULT 0,
                    d_edited INTEGER UNSIGNED NOT NULL DEFAULT 0,
                    compl TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    title VARCHAR(250) NOT NULL,
                    note TEXT,
                    prio TINYINT NOT NULL DEFAULT 0,
                    ow INTEGER NOT NULL DEFAULT 0,
                    tags VARCHAR(600) NOT NULL DEFAULT '',
                    tags_ids VARCHAR(250) NOT NULL DEFAULT '',
                    duedate DATE DEFAULT NULL
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS {$prefix}todo_list_id ON {$prefix}todolist(list_id)");
                $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS {$prefix}todo_uuid ON {$prefix}todolist(uuid)");
            }

            if (!in_array($prefix . 'tags', $tables)) {
                $pdo->exec("CREATE TABLE {$prefix}tags (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(50) NOT NULL COLLATE NOCASE
                )");
                $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS {$prefix}tags_name ON {$prefix}tags(name COLLATE NOCASE)");
            }

            if (!in_array($prefix . 'tag2task', $tables)) {
                $pdo->exec("CREATE TABLE {$prefix}tag2task (
                    tag_id INTEGER NOT NULL,
                    task_id INTEGER NOT NULL,
                    list_id INTEGER NOT NULL
                )");
                $pdo->exec("CREATE INDEX IF NOT EXISTS {$prefix}tag2task_tag_id ON {$prefix}tag2task(tag_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS {$prefix}tag2task_task_id ON {$prefix}tag2task(task_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS {$prefix}tag2task_list_id ON {$prefix}tag2task(list_id)");
            }

            // Ensure default list exists
            $listCount = (int)$pdo->query("SELECT COUNT(*) FROM {$prefix}lists")->fetchColumn();
            if ($listCount === 0) {
                $now = time();
                $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
                $stmt = $pdo->prepare("INSERT INTO {$prefix}lists (uuid, ow, name, d_created, d_edited, sorting, published, taskview) VALUES (?,?,?,?,?,0,0,0)");
                $stmt->execute([$uuid, 1, 'Todo', $now, $now]);
            }

            $step = 3;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$langs = [];
foreach (glob(MTTPATH . 'lang/*.php') as $file) {
    $name = basename($file, '.php');
    if ($name !== 'class.default') {
        $langs[] = $name;
    }
}

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>myTDX Setup</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f0f0; margin: 0; padding: 0; }
.container { max-width: 700px; margin: 40px auto; background: #fff; padding: 30px; border: 1px solid #ddd; border-radius: 6px; }
h1 { font-size: 1.4em; margin-top: 0; color: #333; }
h2 { font-size: 1.1em; color: #555; margin-top: 30px; }
input[type=text], input[type=password], select { width: 100%; padding: 6px; margin: 4px 0 14px; box-sizing: border-box; font-size: 1em; }
label { font-weight: bold; display: block; margin-top: 8px; }
button { padding: 10px 24px; font-size: 1em; background: #316AC5; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #23509a; }
.error { background: #fff0f0; border: 1px solid #f88; color: #900; padding: 10px; margin: 10px 0; }
.ok { background: #f0fff0; border: 1px solid #8c8; color: #060; padding: 10px; margin: 10px 0; }
.note { font-size: 0.85em; color: #666; }
</style>
</head>
<body>
<div class="container">
<h1>myTDX Setup</h1>

<?php foreach ($errors as $err): ?>
<div class="error"><?php echo e($err); ?></div>
<?php endforeach; ?>

<?php if ($step >= 3): ?>
<div class="ok"><strong>Installation complete!</strong></div>
<p>Your myTDX installation is ready.</p>
<ul>
<li><a href="index.php">Go to myTDX</a></li>
</ul>
<p class="note"><strong>Security:</strong> For maximum safety, delete this <code>setup.php</code> file after installation.</p>
<?php else: ?>
<form method="post" action="setup.php">
<h2>Application settings</h2>
<label for="title">Title</label>
<input type="text" id="title" name="title" value="<?php echo e(Config::get('title', 'myTDX')); ?>" maxlength="100">

<label for="lang">Language</label>
<select id="lang" name="lang">
<?php foreach ($langs as $l): ?>
<option value="<?php echo e($l); ?>" <?php echo Config::get('lang', 'en') === $l ? 'selected' : ''; ?>><?php echo e($l); ?></option>
<?php endforeach; ?>
</select>

<label for="password">Admin password <?php echo Config::get('password_hash') ? '(existing password kept if left blank)' : ''; ?></label>
<input type="password" id="password" name="password" autocomplete="new-password">
<div class="note">Leave blank to disable password protection (not recommended).</div>

<label for="timezone">Time zone</label>
<select id="timezone" name="timezone">
<?php foreach (timezone_identifiers_list() as $tz): ?>
<option value="<?php echo e($tz); ?>" <?php echo Config::get('timezone', 'UTC') === $tz ? 'selected' : ''; ?>><?php echo e($tz); ?></option>
<?php endforeach; ?>
</select>

<button type="submit">Install / Update</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
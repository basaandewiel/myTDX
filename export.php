<?php
declare(strict_types=1);

use MyTDX\Config;

define('MTTPATH', dirname(__FILE__) . '/');

require_once MTTPATH . 'src/Config.php';
Config::load(MTTPATH . 'db/config.json');

require_once MTTPATH . 'src/bootstrap.php';
require_once MTTPATH . 'src/Handler/Export.php';

MyTDX\ExportHandler::export();
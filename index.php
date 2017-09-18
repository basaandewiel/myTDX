<?php
if($_SERVER["SERVER_ADDR"]!='::1' && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on'))
{
    header('Location: https://www.tecrd.com/td');
    exit;
}
/*
	(C) Copyright 2009-2010 myTinyTodo by Max Pozdeev <maxpozdeev@gmail.com>
	(C) Copyright 2017      fork myTDX by Jérémie FRANCOIS <jeremie.francois@gmail.com>
	Licensed under the GNU GPL v2 license. See file COPYRIGHT for details.
*/

require_once('./init.php');

$lang = Lang::instance();

if($lang->rtl()) Config::set('rtl', 1);

if(!is_int(Config::get('firstdayofweek')) || Config::get('firstdayofweek')<0 || Config::get('firstdayofweek')>6) Config::set('firstdayofweek', 1);

define('TEMPLATEPATH', MTTPATH. 'themes/'.Config::get('template').'/');

require(TEMPLATEPATH. 'index.php');


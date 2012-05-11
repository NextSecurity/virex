<?php

define('VIREX_APP_PATH', realpath(dirname(__FILE__) . '/../..'));
define('VIREX_CONFIG_PATH', VIREX_APP_PATH . '/protected/config/config.inc.php');

if (!is_file(VIREX_CONFIG_PATH)) {
    die('VIREX is not yet configured!');
}

require_once(VIREX_CONFIG_PATH);
require_once(VIREX_YII_PATH);

$config = VIREX_APP_PATH . '/protected/config/console.php';
Yii::createConsoleApplication($config)->run();
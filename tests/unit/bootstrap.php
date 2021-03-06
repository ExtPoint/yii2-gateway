<?php

// ensure we get report on all possible php errors
error_reporting(-1);

$_SERVER['SCRIPT_NAME'] = '/' . __DIR__;
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

define('YII_ENABLE_ERROR_HANDLER', false);

// require composer autoloader if available
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../vendor/yiisoft/yii2/Yii.php');


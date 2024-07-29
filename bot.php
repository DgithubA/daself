<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

use danog\MadelineProto\Settings;
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$settings = new Settings();
$settings->setAppInfo((new Settings\AppInfo())->setApiId($_ENV['API_ID'])->setApiHash($_ENV['API_HASH'])->setAppVersion($_ENV['VERSION'])->setDeviceModel($_ENV['DEVICE_MODEL']));

!is_dir('./sessions') && mkdir('./sessions');
APP\botHandler::startAndLoop('./sessions/daself.madeline',$settings);
<?php

require_once 'vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$settings = new Settings();
$settings->setAppInfo((new Settings\AppInfo())->setApiId((int)$_ENV['API_ID'])->setApiHash($_ENV['API_HASH'])->setAppVersion($_ENV['VERSION'])->setDeviceModel($_ENV['DEVICE_MODEL']));
//$settings->setDb((new Settings\Database\Postgres())->setUri('tcp://postgres')->setDatabase('postgres')->setPassword('postgres')->setUsername('postgres'));
$settings->setDb((new Settings\Database\Redis())->setUri('redis://redis'));


$session_dir = $_ENV['SESSION_DIR'];
str_ends_with($session_dir , '/') && $session_dir = substr($session_dir, 0, -1);
str_starts_with($session_dir , '/') && $session_dir = substr($session_dir, 1);
str_starts_with($session_dir , './') && $session_dir = substr($session_dir, 2);
$session_dir = './'.$session_dir;

!is_dir($session_dir) && mkdir($session_dir);

$session_file = $session_dir . DIRECTORY_SEPARATOR . 'daself.madeline';

try {
    $api = new API($session_file,$settings);
}catch (\Exception $e){
    echo $e;
    exit(1);
}

if($api->getAuthorization() !==  API::LOGGED_IN || (isset($argv[1]) and $argv[1] == '--just-login')) {
    $get_self = $api->start();
}

if(!$api->isSelfUser()){
    echo "Pleace login as user.";
    $api->logout();
    rmdir($session_dir);
    exit(1);
}else echo "Successfully login in as user.";
unset($api);
APP\botHandler::startAndLoop($session_file,$settings);
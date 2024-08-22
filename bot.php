<?php

require_once 'vendor/autoload.php';

use danog\MadelineProto\Settings;
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$settings = new Settings();
$settings->setAppInfo((new Settings\AppInfo())->setApiId((int)$_ENV['API_ID'])->setApiHash($_ENV['API_HASH'])->setAppVersion($_ENV['VERSION'])->setDeviceModel($_ENV['DEVICE_MODEL']));
$settings->setDb((new Settings\Database\Postgres())->setUri('tcp://127.0.0.1')->setDatabase('postgres')->setPassword('postgres')->setUsername('postgres'));

$session_dir = $_ENV['SESSION_DIR'];
str_ends_with($session_dir , '/') && $session_dir = substr($session_dir, 0, -1);
str_starts_with($session_dir , '/') && $session_dir = substr($session_dir, 1);
str_starts_with($session_dir , './') && $session_dir = substr($session_dir, 2);
$session_dir = './'.$session_dir;

!is_dir($session_dir) && mkdir($session_dir);

$session_file = $session_dir . DIRECTORY_SEPARATOR . 'daself.madeline';

if(isset($argv[1]) and $argv[1] == '--just-login') {
    try {
        $api = new \danog\MadelineProto\API($session_file,$settings);
        $get_self = $api->start();
        if(!$api->isSelfUser()){
            echo "Pleace login as user.";
            $api->logout();
            rmdir($session_dir);
        }else echo "Successfully logged in as user.";
    }catch (\Exception $e){
        echo $e;
    }
    exit(0);
}

APP\botHandler::startAndLoop($session_file,$settings);
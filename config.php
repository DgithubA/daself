<?php
date_default_timezone_set("Asia/Tehran");

require_once 'vendor/autoload.php';


use danog\MadelineProto\Settings;
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$settings = new Settings();
$settings->setAppInfo((new Settings\AppInfo())->setApiId((int)$_ENV['API_ID'])->setApiHash($_ENV['API_HASH'])->setAppVersion($_ENV['VERSION'])->setDeviceModel($_ENV['DEVICE_MODEL']));


$def_host = gethostbyname($_ENV['DB_HOST']) !== $_ENV['DB_HOST'] ? $_ENV['DB_HOST'] : '127.0.0.1';
if ($_ENV['DB_CONNECTION'] == 'redis') {
    $db = (new Settings\Database\Redis())->setUri('tcp://' . $def_host . ':' . $_ENV['DB_PORT']);
    if (!empty($_ENV['DB_PASSWORD'])) $db->setPassword($_ENV['DB_PASSWORD']);
} elseif ($_ENV['DB_CONNECTION'] == 'postgres') {
    $db = (new Settings\Database\Postgres())->setUri('tcp://' . $def_host . ':' . $_ENV['DB_PORT'])->setDatabase($_ENV['DB_DATABASE'] ?? 'postgres')->setPassword($_ENV['DB_PASSWORD'] ?? 'postgres')->setUsername($_ENV['DB_USERNAME'] ?? 'postgres');
} elseif ($_ENV['DB_CONNECTION'] == 'mysql') {
    $db = (new Settings\Database\Mysql())->setUri('tcp://' . $def_host . ':' . $_ENV['DB_PORT'])->setDatabase($_ENV['DB_DATABASE'] ?? 'daself')->setPassword($_ENV['DB_PASSWORD'] ?? '')->setUsername($_ENV['DB_USERNAME'] ?? 'root');
} else $db = (new Settings\Database\Memory());
if(!$db instanceof Settings\Database\Memory) $db->setEphemeralFilesystemPrefix('sessions');

$settings->setDb($db);
$session_dir = $_ENV['SESSION_DIR'];
str_ends_with($session_dir, '/') && $session_dir = substr($session_dir, 0, -1);
str_starts_with($session_dir, '/') && $session_dir = substr($session_dir, 1);
str_starts_with($session_dir, './') && $session_dir = substr($session_dir, 2);
$session_dir = './' . $session_dir;

!is_dir($session_dir) && mkdir($session_dir);

$session_file = $session_dir . DIRECTORY_SEPARATOR . 'daself.madeline';


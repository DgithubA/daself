<?php


require_once 'vendor/autoload.php';


use danog\MadelineProto\Settings;


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$settings = new Settings();
$settings->setAppInfo((new Settings\AppInfo())->setApiId((int)$_ENV['API_ID'])->setApiHash($_ENV['API_HASH'])->setAppVersion($_ENV['VERSION'])->setDeviceModel($_ENV['DEVICE_MODEL']));

if ($_ENV['DB'] == 'redis') {
    $def_host = gethostbyname('redis') !== 'redis' ? 'redis' : '127.0.0.1';
    $db = (new Settings\Database\Redis())->setUri('redis://' . $_ENV['REDIS_HOST'] . ':' . $_ENV['REDIS_PORT']);
    if (!empty($_ENV['REDIS_PASSWORD'])) $db->setPassword($_ENV['REDIS_PASSWORD']);
} elseif ($_ENV['DB'] == 'postgres') {
    $def_host = gethostbyname('postgres') !== 'postgres' ? 'postgres' : '127.0.0.1';
    $db = (new Settings\Database\Postgres())->setUri('tcp://' . ($_ENV['DB_HOST'] ?? $def_host))->setDatabase($_ENV['DB_DATABASE'] ?? 'postgres')->setPassword($_ENV['DB_PASSWORD'] ?? 'postgres')->setUsername($_ENV['DB_USERNAME'] ?? 'postgres');
} elseif ($_ENV['DB'] == 'mysql') {
    $def_host = gethostbyname('mysql') !== 'mysql' ? 'mysql' : '127.0.0.1';
    $db = (new Settings\Database\Mysql())->setUri('tcp://' . ($_ENV['DB_HOST'] ?? $def_host))->setDatabase($_ENV['DB_DATABASE'] ?? 'daself')->setPassword($_ENV['DB_PASSWORD'] ?? '')->setUsername($_ENV['DB_USERNAME'] ?? 'root');
} else $db = (new Settings\Database\Memory());

$settings->setDb($db);
$session_dir = $_ENV['SESSION_DIR'];
str_ends_with($session_dir, '/') && $session_dir = substr($session_dir, 0, -1);
str_starts_with($session_dir, '/') && $session_dir = substr($session_dir, 1);
str_starts_with($session_dir, './') && $session_dir = substr($session_dir, 2);
$session_dir = './' . $session_dir;

!is_dir($session_dir) && mkdir($session_dir);

$session_file = $session_dir . DIRECTORY_SEPARATOR . 'daself.madeline';


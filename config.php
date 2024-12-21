<?php

use danog\MadelineProto\Settings;

require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set("Asia/Tehran");

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$settings = new Settings;
$settings->setAppInfo(
    (new Settings\AppInfo)
        ->setApiId((int)$_ENV['API_ID'])
        ->setApiHash($_ENV['API_HASH'])
        ->setAppVersion($_ENV['VERSION'])
        ->setDeviceModel($_ENV['DEVICE_MODEL'])
);

$def_host = gethostbyname($_ENV['DB_HOST']) !== $_ENV['DB_HOST']
    ? $_ENV['DB_HOST']
    : '127.0.0.1';
$db = match ($_ENV['DB_CONNECTION']) {
    'redis'    => (new Settings\Database\Redis)
        ->setUri(\sprintf('tcp://%s:%s', $def_host, $_ENV['DB_PORT'])),

    'mysql'    => (new Settings\Database\Mysql)
        ->setUri(\sprintf('tcp://%s:%s', $def_host, $_ENV['DB_PORT']))
        ->setDatabase($_ENV['DB_DATABASE'] ?? 'daself')
        ->setUsername($_ENV['DB_USERNAME'] ?? 'root')
        ->setPassword($_ENV['DB_PASSWORD'] ?? ''),

    'postgres' => (new Settings\Database\Postgres)
        ->setUri(\sprintf('tcp://%s:%s', $def_host, $_ENV['DB_PORT']))
        ->setDatabase($_ENV['DB_DATABASE'] ?? 'postgres')
        ->setUsername($_ENV['DB_USERNAME'] ?? 'postgres')
        ->setPassword($_ENV['DB_PASSWORD'] ?? 'postgres'),

    default    => new Settings\Database\Memory,
};

if ($_ENV['DB_CONNECTION'] === 'redis' && !empty($_ENV['DB_PASSWORD']))
    $db->setPassword($_ENV['DB_PASSWORD']);

if (!$db instanceof Settings\Database\Memory)
    $db->setEphemeralFilesystemPrefix('sessions');

$settings->setDb($db);

$session_dir = $_ENV['SESSION_DIR'];
str_ends_with($session_dir, '/') && $session_dir = substr($session_dir, 0, -1);
str_starts_with($session_dir, '/') && $session_dir = substr($session_dir, 1);
str_starts_with($session_dir, './') || $session_dir = "./$session_dir";

is_dir($session_dir) || mkdir($session_dir, recursive: true);
$session_dir = realpath($session_dir);
$session_file = "$session_dir/daself.madeline";

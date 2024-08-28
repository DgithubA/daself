<?php
require 'vendor/autoload.php';


use Amp\Postgres\PostgresConnectionPool;
use Amp\Postgres\PostgresConfig;
use function Amp\Redis\createRedisClient;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

if($_ENV['DB'] == 'redis'){
    echo "database is redis\n";
    $def_host = gethostbyname('redis') !== 'redis' ? 'redis' : '127.0.0.1';
    $redis = createRedisClient('tcp://'.$def_host);
    echo $redis->echo('connecting successfully.');
    $redis->ping();
}elseif($_ENV['DB'] == 'postgres'){
    echo "database is postgres\n";
    $def_host = gethostbyname('postgres') !== 'postgres' ? 'postgres' : '127.0.0.1';
    $config = new PostgresConfig($def_host, user:($_ENV['DB_USERNAME'] ?? 'postgres'),password: $_ENV['DB_PASSWORD'] ?? 'postgres');
    $postgresConnectionPool = new PostgresConnectionPool($config);
    $res = $postgresConnectionPool->query('SELECT datname FROM pg_database;');
    var_dump($res->fetchRow());
}elseif($_ENV['DB'] == 'mysql'){
    echo "database is mysql\n";
    $def_host = gethostbyname('mysql') !== 'mysql' ? 'mysql' : '127.0.0.1';
    $config = new \Amp\Mysql\MysqlConfig($def_host,user: $_ENV['DB_USERNAME'] ?? 'mysql',password: $_ENV['DB_PASSWORD'] ?? 'password');
    $connectionPool = new \Amp\Mysql\MysqlConnectionPool($config);
    $res = $connectionPool->query('show DATABASES;');
    var_dump($res->fetchRow());
}else exit('env.DB not support');



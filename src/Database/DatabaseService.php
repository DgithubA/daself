<?php

namespace App\Database;

use App\Database\AbstractDatabase;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\DatabaseAbstract;

class DatabaseService
{
    private AbstractDatabase $database;

    public function __construct(DatabaseAbstract $database)
    {
        if ($database instanceof Settings\Database\Mysql) {
            $config = Mysql::getConfigInstance(
                $database->getUri(),
                user: $database->getUsername(),
                password: $database->getPassword(),
                database: $database->getDatabase(),
            );
            $this->database = new Mysql($config);
        } elseif ($database instanceof Settings\Database\Postgres) {
            $config = Postgres::getConfigInstance(
                $database->getUri(),
                user: $database->getUsername(),
                password: $database->getPassword(),
                database: $database->getDatabase(),
            );
            $this->database = new Postgres($config);
        } elseif ($database instanceof Settings\Database\Redis) {
            $config = Redis::getConfigInstance(
                $database->getUri(),
                password: $database->getPassword(),
                database: $database->getDatabase(),
            );
            $this->database = new Redis($config);
        }
    }

    public function execute(string $query, array $params = [])
    {
        return $this->database->execute($query, $params);
    }
}

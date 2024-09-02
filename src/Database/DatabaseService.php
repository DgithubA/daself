<?php

namespace APP\Database;
use APP\Database\databases\DatabaseAbs;
use APP\Database\databases\Mysql;
use APP\Database\databases\Postgres;
use APP\Database\databases\redis;
use danog\MadelineProto\Settings\DatabaseAbstract;
use danog\MadelineProto\Settings;

class DatabaseService{
    private DatabaseAbs $database;
    public function __construct(DatabaseAbstract $database){
        if($database instanceof Settings\Database\Mysql){
            $config = Mysql::getConfigInstance($database->getUri(),user: $database->getUsername(), password: $database->getPassword(),database: $database->getDatabase());
            $this->database = new Mysql($config);
        }elseif ($database instanceof Settings\Database\Postgres){
            $config = Postgres::getConfigInstance($database->getUri(),user: $database->getUsername(), password: $database->getPassword(),database: $database->getDatabase());
            $this->database = new Postgres($config);
        }elseif ($database instanceof Settings\Database\Redis){
            $config = Redis::getConfigInstance($database->getUri(),password:$database->getPassword(),database: $database->getDatabase());
            $this->database = new Redis($config);
        }
    }
    public function execute(string $query, array $params = []){
        return $this->database->execute($query, $params);
    }
}
<?php

namespace APP\Database\databases;

use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Mysql\MysqlResult;

class Mysql extends DatabaseAbs {

    private MysqlConnectionPool $connectionPool;

    public function __construct(MysqlConfig $config){
        $this->connectionPool = new MysqlConnectionPool($config);
    }

    public function execute(string $query,array $params = []) : MysqlResult{
        return $this->connectionPool->execute($query,$params);
    }

    public static function getConfigInstance(string $host,int $port = MysqlConfig::DEFAULT_PORT,string $user = null,string $password = null,string $database = null):MysqlConfig{
        return new MysqlConfig($host,$port,$user,$password,$database);
    }
}
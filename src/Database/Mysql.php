<?php

namespace App\Database;

use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlResult;
use Amp\Mysql\MysqlConnectionPool;

class Mysql extends AbstractDatabase
{
    private MysqlConnectionPool $connectionPool;

    public function __construct(MysqlConfig $config)
    {
        $this->connectionPool = new MysqlConnectionPool($config);
    }

    public function execute(string $query, array $params = []): MysqlResult
    {
        return $this->connectionPool->execute($query, $params);
    }

    public static function getConfigInstance(
        string $host,
        int $port = MysqlConfig::DEFAULT_PORT,
        string $user = null,
        string $password = null,
        string $database = null,
    ): MysqlConfig {
        return new MysqlConfig($host, $port, $user, $password, $database);
    }
}

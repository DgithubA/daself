<?php

namespace App\Database;

use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresResult;
use Amp\Postgres\PostgresConnectionPool;

class Postgres extends AbstractDatabase
{
    private PostgresConnectionPool $connectionPool;

    public function __construct(PostgresConfig $config)
    {
        $this->connectionPool = new PostgresConnectionPool($config);
    }

    public function execute(string $query, array $params = []): PostgresResult
    {
        return $this->connectionPool->execute($query, $params);
    }

    public static function getConfigInstance(
        string $host,
        int $port = PostgresConfig::DEFAULT_PORT,
        string $user = null,
        string $password = null,
        string $database = null,
    ): PostgresConfig {
        return new PostgresConfig($host, $port, $user, $password, $database);
    }
}

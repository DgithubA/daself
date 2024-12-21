<?php

namespace App\Database;

use Amp\Redis\RedisClient;
use Amp\Redis\RedisConfig;
use function Amp\Redis\createRedisClient;

class Redis extends AbstractDatabase
{
    private RedisClient $client;

    public function __construct(RedisConfig $config)
    {
        $this->client = createRedisClient($config);
    }

    public static function getConfigInstance(
        string $host,
        int $port = RedisConfig::DEFAULT_PORT,
        string $user = null,
        string $password = null,
        string $database = null,
    ): RedisConfig {
        $config = RedisConfig::fromUri($host);
        if (!empty($database)) {
            $config = $config->withDatabase((int)$database);
        }
        if (!empty($password)) {
            $config = $config->withPassword($password);
        }
        return $config;
    }

    public function execute(string $query, array $params = []): mixed
    {
        return $this->client->execute($query, ...$params);
    }
}

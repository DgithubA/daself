<?php

namespace App\Database;

abstract class AbstractDatabase
{
    abstract public function execute(string $query, array $params = []);

    abstract public static function getConfigInstance(
        string $host,
        int $port,
        string $user,
        string $password,
        string $database,
    );
}

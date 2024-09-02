<?php

namespace APP\Database\databases;

abstract class DatabaseAbs {
    abstract public static function getConfigInstance(string $host,int $port ,string $user,string $password,string $database);
    abstract public function execute(string $query);
}
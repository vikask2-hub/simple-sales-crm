<?php

declare(strict_types=1);

final class Database
{
    private PDO $pdo;

    /** @param array{dsn:string, username?:?string, password?:?string} $config */
    public function __construct(array $config)
    {
        $this->pdo = new PDO(
            $config['dsn'],
            $config['username'] ?? null,
            $config['password'] ?? null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}

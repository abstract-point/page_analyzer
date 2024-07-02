<?php

namespace App\Database;

final class Connection
{
    /**
     * Connection
     * тип @var
     */
    private static ?Connection $conn = null;

    /**
     * Подключение к базе данных и возврат экземпляра объекта \PDO
     *
     * @return \PDO
     * @throws \Exception
     */
    public function connect()
    {
        $databaseUrl = getenv('DATABASE_URL') ?: '';

        $username = parse_url($databaseUrl, PHP_URL_USER) ?: '';
        $password = parse_url($databaseUrl, PHP_URL_PASS) ?: '';
        $host = parse_url($databaseUrl, PHP_URL_HOST) ?: '';
        $port = parse_url($databaseUrl, PHP_URL_PORT) ?: '5432';
        $path = parse_url($databaseUrl, PHP_URL_PATH) ?: '';
        $dbName = ltrim($path, '/');

        $conStr = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            $host,
            $port,
            $dbName
        );

        $pdo = new \PDO($conStr, $username, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /**
     * Возврат экземпляра объекта Connection
     *
     * @return Connection
     */
    public static function get()
    {
        if (null === static::$conn) {
            static::$conn = new self();
        }

        return static::$conn;
    }

    protected function __construct()
    {
    }
}

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
        $params = parse_url($databaseUrl);

        if ($params === false) {
            throw new \Exception("Error reading database configuration file");
        }

        $username = $params['user'];
        $password = $params['pass'];
        $host = $params['host'];
        $dbName = ltrim($params['path'], '/');
        
        $conStr = sprintf(
            "pgsql:host=%s;dbname=%s;user=%s;password=%s",
            $host,
            $dbName,
            $username,
            $password
        );

        $pdo = new \PDO($conStr);
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

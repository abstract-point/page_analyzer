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
        
        $params = parse_url(getenv('DATABASE_URL'));
        if ($params === false) {
            throw new \Exception("Error reading database configuration file");
        }

        $conStr = sprintf(
            "pgsql:host=%s;dbname=%s;user=%s;password=%s",
            $params['host'],
            ltrim($params['path'], '/'),
            $params['user'],
            $params['pass']
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

<?php
namespace Siagraph\Database;

use mysqli;
use Exception;

class Database
{
    private static ?mysqli $connection = null;

    public static function initialize(array $config): void
    {
        self::$connection = mysqli_init();
        if (isset($config['connect_timeout'])) self::$connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, (int) $config['connect_timeout']);
        if (isset($config['read_timeout'])) self::$connection->options(MYSQLI_OPT_READ_TIMEOUT, (int) $config['read_timeout']);
        self::$connection->real_connect(
            $config['servername'],
            $config['username'],
            $config['password'],
            $config['database']
        );

        if (self::$connection->connect_errno) {
            throw new Exception('Failed to connect to MySQL: ' . self::$connection->connect_error);
        }
    }

    public static function getConnection(): mysqli
    {
        if (!self::$connection) {
            throw new Exception('Database connection not initialized.');
        }
        return self::$connection;
    }

    public static function query(string $query)
    {
        $conn = self::getConnection();
        $result = $conn->query($query);
        if ($result === false) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        return $result;
    }
}


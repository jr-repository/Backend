<?php
namespace App\Core;

use App\Config\DatabaseConfig;
use mysqli;
use Exception;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $config = DatabaseConfig::get();
        
        try {
            $this->connection = new mysqli(
                $config['host'],
                $config['user'],
                $config['pass'],
                $config['name']
            );

            $this->connection->set_charset($config['charset']);
            
        } catch (\mysqli_sql_exception $e) {
            throw new Exception('Connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
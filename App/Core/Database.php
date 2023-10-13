<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    protected PDO $pdo;

    public function __construct() {
        try {
            $config = Config::get()['database'] ?? null;

            if (!$config) {
                die("Database configs not found");
            }

            $host = $config['host'];
            $dbname = $config['database'];
            $user = $config['username'];
            $pass = $config['password'];

            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }
}
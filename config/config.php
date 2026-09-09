<?php

class Database
{
    private $host = 'localhost';
    private $user = 'root';
    private $db_name = 'sbcdb';
    private $db_pass = '';

    public $conn;

    public function __construct()
    {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->user,
                $this->db_pass
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            #echo "Connection success";
        } catch (PDOException $e) {
            die("Connection Failed " . $e->getMessage());
        }

    }

    public function getConnection()
    {
        return $this->conn;
    }
}


?>
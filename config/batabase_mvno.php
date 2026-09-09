<?php
require_once __DIR__ . '/db_credentials.php';

class SimpleEventDB {
    private $pdo;

    public function __construct() {
        try {
            $host = DB_HOST;
            $dbname = 'pricetag_mvno';  // DB명 확인
            $username = DB_USER;
            $password = DB_PASS;
            
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("DB Connection Error: " . $e->getMessage());
        }
    }
    
    public function fetch($query, $params = []) {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function fetchAll($query, $params = []) {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

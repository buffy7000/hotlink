<?php
class SimpleEventDB {
    private $pdo;
    
    public function __construct() {
        try {
            $host = 'localhost';
            $dbname = 'pricetag_mvno';  // DB명 확인
            $username = 'pricetag_pricetag';  // 사용자명 확인
            $password = '***REMOVED***';  // 비밀번호 입력
            
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

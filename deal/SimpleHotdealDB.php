<?php
/**
 * 핫딜 데이터베이스 클래스
 * 파일명: SimpleHotdealDB.php
 */

require_once __DIR__ . '/../config/db_credentials.php';

class SimpleHotdealDB {
    private $pdo;

    public function __construct() {
        $this->pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=3306;dbname=pricetag_hotdeal;charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchColumn($sql, $params = []) {
        return $this->query($sql, $params)->fetchColumn();
    }
}
?>

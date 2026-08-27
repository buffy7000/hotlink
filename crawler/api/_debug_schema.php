<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
try {
    $db = new Database();
    $out = [];
    foreach (['crawl_logs', 'posts'] as $table) {
        $stmt = $db->query("DESCRIBE $table");
        $out[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
header('Content-Type: application/json; charset=utf-8');

// 1회성 스키마 마이그레이션: hotdeals.category 컬럼 추가.
// 재실행해도 안전(이미 있으면 "Duplicate column" 오류만 삼키고 넘어감).
$secretFile = __DIR__ . '/../../crawler/config/crawler_api_key.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
require $secretFile; // defines CRAWLER_API_KEY constant

$receivedKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
if (!hash_equals(CRAWLER_API_KEY, $receivedKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../SimpleHotdealDB.php';

try {
    $db = new SimpleHotdealDB();
    $result = ['added_column' => false, 'added_index' => false];

    try {
        $db->query("ALTER TABLE hotdeals ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'etc' AFTER store_name");
        $result['added_column'] = true;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) throw $e;
    }

    try {
        $db->query("ALTER TABLE hotdeals ADD INDEX idx_category (category)");
        $result['added_index'] = true;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key') === false && strpos($e->getMessage(), 'already exists') === false) throw $e;
    }

    echo json_encode(['success' => true] + $result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

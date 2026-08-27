<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$secretFile = __DIR__ . '/../crawler/config/crawler_api_key.php';
require $secretFile;
if (!hash_equals(CRAWLER_API_KEY, $_GET['key'] ?? '')) {
    http_response_code(403);
    exit('Unauthorized');
}

$keywords = explode(',', $_GET['keywords'] ?? '');
$db = new Database();
$result = [];

foreach ($keywords as $kw) {
    $kw = trim($kw);
    if ($kw === '') continue;
    $like = '%' . $kw . '%';
    $stmt = $db->query(
        "SELECT COUNT(*) AS cnt, COUNT(DISTINCT community_id) AS communities,
                SUM(CASE WHEN created_at >= NOW() - INTERVAL 3 DAY THEN 1 ELSE 0 END) AS last3d,
                MAX(created_at) AS last_post
         FROM posts WHERE title LIKE ?",
        [$like]
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $result[$kw] = $row;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

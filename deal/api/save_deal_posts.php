<?php
header('Content-Type: application/json; charset=utf-8');

// API 키 검증 (crawler/api/save_posts.php, coupang/api/save_goldbox.php와 동일한 공용 키 사용)
$secretFile = __DIR__ . '/../../crawler/config/crawler_api_key.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
require $secretFile; // defines CRAWLER_API_KEY constant

$receivedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals(CRAWLER_API_KEY, $receivedKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = file_get_contents('php://input');
$payload = json_decode($body, true);

$source = $payload['source'] ?? null;
$status = $payload['status'] ?? 'error';
$errorMessage = $payload['error_message'] ?? null;
$items = $payload['items'] ?? [];

$crawlerClasses = [
    'eomisae' => ['file' => 'EomisaeHotdealCrawler.php', 'class' => 'EomisaeCrawler'],
    'ruliweb' => ['file' => 'RuliwebHotdealCrawler.php', 'class' => 'RuliwebCrawler'],
];

if (!isset($crawlerClasses[$source])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "지원하지 않는 source: {$source}"]);
    exit;
}

if ($status !== 'success' || empty($items)) {
    echo json_encode([
        'success' => true,
        'saved' => 0,
        'message' => $status !== 'success' ? ('크롤링 실패 보고: ' . $errorMessage) : '저장할 아이템 없음',
    ]);
    exit;
}

require_once __DIR__ . '/../' . $crawlerClasses[$source]['file'];

try {
    $className = $crawlerClasses[$source]['class'];
    $crawler = new $className();

    // saveItem()은 원래 수동 실행용으로 만들어져 진행 상황을 echo로 출력한다.
    // 이 응답은 JSON API라 그 출력이 섞이면 안 되므로 버퍼링해 버린다.
    ob_start();
    $newCount = 0;
    $updatedCount = 0;
    try {
        foreach ($items as $item) {
            if (empty($item['original_id']) || empty($item['title'])) continue;
            if ($crawler->saveItem($item)) {
                $newCount++;
            } else {
                $updatedCount++;
            }
        }
    } finally {
        ob_end_clean();
    }

    echo json_encode([
        'success' => true,
        'new' => $newCount,
        'updated' => $updatedCount,
        'saved' => $newCount + $updatedCount,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

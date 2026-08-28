<?php
header('Content-Type: application/json; charset=utf-8');

// API 키 검증 (crawler/api/save_posts.php와 동일한 공용 키 사용)
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

require_once __DIR__ . '/../CoupangGoldboxCrawler.php';

$body = file_get_contents('php://input');
$payload = json_decode($body, true);

$status = $payload['status'] ?? 'error';
$errorMessage = $payload['error_message'] ?? null;
$products = $payload['products'] ?? [];

if ($status !== 'success' || empty($products)) {
    echo json_encode([
        'success' => true,
        'saved' => 0,
        'message' => $status !== 'success' ? ('크롤링 실패 보고: ' . $errorMessage) : '저장할 상품 없음',
    ]);
    exit;
}

try {
    $crawler = new CoupangGoldboxCrawler();

    // saveProducts() 내부(saveGoldboxProducts)는 원래 브라우저 수동 실행용으로 만들어져
    // 진행 상황을 echo로 출력한다. 이 응답은 JSON API라 그 출력이 섞이면 안 되므로 버퍼링해 버린다.
    // (예외가 나도 버퍼가 남지 않도록 finally에서 정리)
    ob_start();
    try {
        $result = $crawler->saveProducts($products);
    } finally {
        ob_end_clean();
    }

    echo json_encode([
        'success' => true,
        'new' => $result['new'],
        'updated' => $result['updated'],
        'saved' => $result['new'] + $result['updated'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

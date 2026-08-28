<?php
// GitHub Actions에서 실행: 쿠팡 골드박스 API를 호출해 원본 상품 목록을 JSON으로 stdout 출력.
// hotlink.kr 서버 IP(139.162.90.4, Linode/Akamai 대역)가 쿠팡 측에 전체 도메인 차단(Access denied)
// 되어 있어 서버 대신 Actions 러너(다른 IP 대역)에서 API만 호출한다.
// DB에는 접근하지 않는다 — 저장은 coupang/api/save_goldbox.php(서버, localhost DB)가 담당.

// PHP 경고/알림이 stdout(JSON)에 섞이지 않도록 stderr로 분리
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);

require_once __DIR__ . '/hmac_client.php';

$accessKey = '***REMOVED***';
$secretKey = '***REMOVED***';
$method = 'GET';
$url = 'https://api-gateway.coupang.com/v2/providers/affiliate_open_api/apis/openapi/products/goldbox';
$query = 'subId=hotlink1&imageSize=200x200';

fwrite(STDERR, "쿠팡 골드박스 API 호출: {$url}?{$query}\n");

$status = 'error';
$errorMessage = null;
$products = [];

try {
    $response = send_http_request($method, $url, $query, null, $accessKey, $secretKey);

    if (!is_array($response) || !isset($response['rCode'])) {
        $errorMessage = '응답이 올바른 JSON 형식이 아닙니다 (IP 차단 등으로 의심됨)';
    } elseif ($response['rCode'] != '0') {
        $errorMessage = 'API 응답 오류: ' . ($response['rMessage'] ?? '(메시지 없음)');
    } else {
        $products = $response['data'] ?? [];
        $status = 'success';
        fwrite(STDERR, "성공: {$response['rMessage']}, 상품 " . count($products) . "개 수신\n");
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

if ($status === 'error') {
    fwrite(STDERR, "실패: {$errorMessage}\n");
}

echo json_encode([
    'status'        => $status,
    'error_message' => $errorMessage,
    'products'      => $products,
], JSON_UNESCAPED_UNICODE);

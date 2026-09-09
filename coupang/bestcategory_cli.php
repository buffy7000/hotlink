<?php
// GitHub Actions에서 실행: 쿠팡 베스트카테고리 API(식품/생활용품/가전디지털)를 호출해
// 원본 상품 목록을 JSON으로 stdout 출력. goldbox_cli.php와 동일한 이유로
// (hotlink.kr 서버 IP가 쿠팡 전체 도메인에서 차단) Actions 러너에서 호출한다.
// 응답 상품 스키마가 골드박스와 동일해 저장은 coupang/api/save_goldbox.php를 그대로 재사용한다.

ini_set('display_errors', 'stderr');
error_reporting(E_ALL);

require_once __DIR__ . '/hmac_client.php';
require_once __DIR__ . '/coupang_credentials.php';

$accessKey = COUPANG_ACCESS_KEY;
$secretKey = COUPANG_SECRET_KEY;
$method = 'GET';

// categoryId는 실제 API 호출로 검증된 값 (1012=로켓프레시/식품, 1015=생활용품, 1016=가전디지털)
$categories = [
    1012 => '식품(로켓프레시)',
    1015 => '생활용품',
    1016 => '가전디지털',
];

$allProducts = [];
$errors = [];

foreach ($categories as $categoryId => $label) {
    $url = "https://api-gateway.coupang.com/v2/providers/affiliate_open_api/apis/openapi/products/bestcategories/{$categoryId}";
    $query = 'limit=20&subId=hotlink1&imageSize=200x200';

    fwrite(STDERR, "[{$label}] 카테고리 {$categoryId} 호출: {$url}?{$query}\n");

    try {
        $response = send_http_request($method, $url, $query, null, $accessKey, $secretKey);

        if (!is_array($response) || !isset($response['rCode'])) {
            $errors[] = "{$label}: 응답이 올바른 JSON 형식이 아님(IP 차단 등 의심)";
            continue;
        }
        if ($response['rCode'] != '0') {
            $errors[] = "{$label}: API 응답 오류 - " . ($response['rMessage'] ?? '(메시지 없음)');
            continue;
        }

        $products = $response['data'] ?? [];
        fwrite(STDERR, "[{$label}] 상품 " . count($products) . "개 수신\n");
        $allProducts = array_merge($allProducts, $products);
    } catch (Exception $e) {
        $errors[] = "{$label}: " . $e->getMessage();
    }

    sleep(1); // 카테고리별 호출 간 살짝 텀
}

// 하나라도 성공해서 상품을 모았으면 success로 취급 (일부 카테고리만 실패해도 나머지는 저장)
$status = !empty($allProducts) ? 'success' : 'error';
$errorMessage = !empty($errors) ? implode(' | ', $errors) : null;

if ($status === 'error') {
    fwrite(STDERR, "전체 실패: {$errorMessage}\n");
}

echo json_encode([
    'status'        => $status,
    'error_message' => $errorMessage,
    'products'      => $allProducts,
], JSON_UNESCAPED_UNICODE);

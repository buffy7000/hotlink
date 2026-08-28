<?php
// GitHub Actions 러너 IP에서 쿠팡 골드박스 API가 차단되는지 확인하는 1회성 테스트 스크립트.
// DB 저장 없이 raw 응답만 stdout에 출력한다. (hotlink.kr 서버 IP가 Akamai에 차단되어 있어
// 다른 IP 대역인 GitHub Actions에서도 동일하게 막히는지 확인하기 위함)

require_once __DIR__ . '/hmac_client.php';

$access_key = '***REMOVED***';
$secret_key = '***REMOVED***';
$method = 'GET';
$url = 'https://api-gateway.coupang.com/v2/providers/affiliate_open_api/apis/openapi/products/goldbox';
$query = 'subId=hotlink1&imageSize=200x200';

echo "=== Actions 러너 발신 IP ===\n";
$ch = curl_init('https://api.ipify.org?format=json');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
echo curl_exec($ch) . "\n\n";
curl_close($ch);

echo "=== 쿠팡 골드박스 API 호출 ===\n";
$authorization = generate_hmac($method, $url, $query, $access_key, $secret_key);
$fullUrl = $url . '?' . $query;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: $authorization", "Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP CODE: $httpCode\n";
echo "CURL ERROR: $err\n";

$decoded = json_decode($response, true);
if ($decoded && isset($decoded['rCode'])) {
    echo "결과: 정상 JSON 응답 수신 (rCode={$decoded['rCode']}, 상품 " . count($decoded['data'] ?? []) . "개)\n";
    echo "=> 이 IP는 차단되지 않았습니다.\n";
} else {
    echo "결과: JSON 파싱 실패 (차단 페이지 등 비정상 응답)\n";
    echo "=> 이 IP도 차단되었을 가능성이 높습니다.\n";
}

echo "\n=== RAW RESPONSE (앞 1500자) ===\n";
echo substr((string)$response, 0, 1500) . "\n";

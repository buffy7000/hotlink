<?php
// 임시 진단 스크립트 — 쿠팡 차단 원인 조사용. 확인 후 삭제 예정.
require_once(__DIR__ . '/../deal/SimpleHotdealDB.php');

header('Content-Type: text/plain; charset=utf-8');

$db = new SimpleHotdealDB();

echo "=== source_id=99 (쿠팡) 최근 저장 이력 ===\n";
$row = $db->fetch("SELECT COUNT(*) as cnt, MAX(crawled_at) as last_crawled, MIN(crawled_at) as first_crawled FROM hotdeals WHERE source_id = 99");
print_r($row);

echo "\n=== 최근 5건 ===\n";
$rows = $db->fetchAll("SELECT id, title, crawled_at, original_created_at FROM hotdeals WHERE source_id = 99 ORDER BY id DESC LIMIT 5");
print_r($rows);

echo "\n=== 서버 발신 IP 확인 ===\n";
$ch = curl_init('https://api.ipify.org?format=json');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
echo curl_exec($ch) . "\n";
curl_close($ch);

echo "\n=== www.coupang.com 접근 테스트 (일반 도메인, 비교용) ===\n";
$ch = curl_init('https://www.coupang.com/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP CODE: $code\n";
echo "Access denied 포함 여부: " . (strpos($resp, 'Access denied') !== false ? 'YES' : 'NO') . "\n";
echo "길이: " . strlen($resp) . " bytes\n";

echo "\n=== api-gateway.coupang.com 다른 엔드포인트(존재하지 않는 경로) 테스트 ===\n";
$ch = curl_init('https://api-gateway.coupang.com/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp2 = curl_exec($ch);
$code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP CODE: $code2\n";
echo "Access denied 포함 여부: " . (strpos($resp2, 'Access denied') !== false ? 'YES' : 'NO') . "\n";

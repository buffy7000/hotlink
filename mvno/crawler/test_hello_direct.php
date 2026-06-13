<?php
/**
 * 헬로모바일 API 직접 테스트
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== 헬로모바일 API 직접 테스트 ===\n\n";

$apiUrl = 'https://direct.lghellovision.net/event/ajaxEventList.do';

$postData = array(
    'openyn' => 'Y',
    'flag' => 'B107',
    'startRow' => '1',
    'endRow' => '12',
    'idxOfEvent' => '',
    'nPage' => '',
    'pgNum' => '',
    'isMobile' => 'false',
    'eventGubun' => '00',
    'status' => 'Y',
    'newPage' => 'Y',
    'telecom' => 'USIM'
);

echo "API URL: $apiUrl\n";
echo "POST Data:\n";
foreach ($postData as $key => $value) {
    echo "  $key: " . ($value === '' ? '(빈값)' : $value) . "\n";
}
echo "\n";

$ch = curl_init();

curl_setopt_array($ch, array(
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With: XMLHttpRequest',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
        'Origin: https://direct.lghellovision.net',
        'Referer: https://direct.lghellovision.net/event/viewEventList.do?returnTab=allli&category=USIM'
    ),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_VERBOSE => false
));

echo "요청 전송 중...\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$error = curl_error($ch);

echo "=== 응답 결과 ===\n";
echo "HTTP 상태: $httpCode\n";
echo "최종 URL: $effectiveUrl\n";

if (!empty($error)) {
    echo "cURL 오류: $error\n";
    curl_close($ch);
    exit;
}

curl_close($ch);

if ($httpCode !== 200) {
    echo "\n✗ HTTP 오류 $httpCode\n";
    echo "응답 내용 (처음 500자):\n";
    echo str_repeat("-", 50) . "\n";
    echo substr($response, 0, 500) . "\n";
    echo str_repeat("-", 50) . "\n";
    exit;
}

echo "응답 길이: " . strlen($response) . " bytes\n\n";

// JSON 파싱
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "✗ JSON 파싱 실패: " . json_last_error_msg() . "\n";
    echo "응답 내용 (처음 1000자):\n";
    echo str_repeat("-", 50) . "\n";
    echo substr($response, 0, 1000) . "\n";
    echo str_repeat("-", 50) . "\n";
    exit;
}

echo "✓ JSON 파싱 성공!\n\n";

// 응답 구조 출력
echo "=== 응답 구조 ===\n";
echo "최상위 키: " . implode(', ', array_keys($data)) . "\n\n";

if (isset($data['nTotCnt'])) {
    echo "전체 개수: " . $data['nTotCnt'] . "\n";
}

if (isset($data['list']) && is_array($data['list'])) {
    $count = count($data['list']);
    echo "이벤트 개수: $count\n\n";
    
    if ($count > 0) {
        echo "=== 첫 번째 이벤트 샘플 ===\n";
        $event = $data['list'][0];
        echo "ID: " . $event['idxOfEvent'] . "\n";
        echo "제목: " . strip_tags($event['title']) . "\n";
        echo "상태: " . $event['status'] . "\n";
        echo "텔레콤: " . $event['telecom'] . "\n";
        echo "시작일: " . $event['sDate'] . "\n";
        echo "종료일: " . $event['eDate'] . "\n";
        echo "조회수: " . number_format($event['hitCount']) . "\n";
        echo "썸네일: " . $event['listFileName'] . "\n\n";
        
        echo "✅ API 정상 작동!\n";
    }
} else {
    echo "⚠️  'list' 키가 없거나 배열이 아닙니다.\n";
    echo "\n전체 응답:\n";
    echo str_repeat("-", 50) . "\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
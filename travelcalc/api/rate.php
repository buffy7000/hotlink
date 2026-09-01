<?php
header('Content-Type: application/json; charset=utf-8');

// 지원 통화 화이트리스트 (추후 국가 추가시 여기에 추가)
$supported = ['VND'];
$from = strtoupper($_GET['from'] ?? 'VND');
$to = 'KRW';

if (!in_array($from, $supported, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'unsupported currency']);
    exit;
}

$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . "/rate_{$from}_{$to}.json";
$maxAge = 86400; // 24시간에 1번만 갱신

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $maxAge) {
    echo file_get_contents($cacheFile);
    exit;
}

$apiUrl = "https://open.er-api.com/v6/latest/{$from}";
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$data = ($httpCode === 200 && $response) ? json_decode($response, true) : null;

if ($data && ($data['result'] ?? '') === 'success' && isset($data['rates'][$to])) {
    $result = [
        'success'    => true,
        'from'       => $from,
        'to'         => $to,
        'rate'       => $data['rates'][$to],
        'updated_at' => $data['time_last_update_utc'] ?? gmdate('D, d M Y H:i:s') . ' +0000',
        'source'     => 'open.er-api.com',
        'stale'      => false,
    ];
    file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// API 호출 실패 시 오래된 캐시라도 있으면 반환 (완전히 끊기지 않도록)
if (file_exists($cacheFile)) {
    $stale = json_decode(file_get_contents($cacheFile), true);
    $stale['stale'] = true;
    echo json_encode($stale, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(502);
echo json_encode(['success' => false, 'error' => '환율 정보를 가져올 수 없습니다']);

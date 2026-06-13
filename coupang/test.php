<?php
// 간단한 테스트 파일
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// PHP 에러 표시 활성화 (디버깅용)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 현재 시간 테스트
    $datetime = date("Ymd") . 'T' . date("His") . 'Z';
    
    // 간단한 응답
    $response = [
        'success' => true,
        'message' => 'PHP 파일이 정상 작동합니다',
        'current_time' => $datetime,
        'gmt_time' => gmdate("Ymd") . 'T' . gmdate("His") . 'Z',
        'php_version' => phpversion(),
        'curl_available' => function_exists('curl_init') ? 'Yes' : 'No'
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $error_response = [
        'success' => false,
        'error' => $e->getMessage(),
        'file' => __FILE__,
        'line' => __LINE__
    ];
    
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
}
?>
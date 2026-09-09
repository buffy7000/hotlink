<?php
// 쿠팡 골드박스 API - PHP 백엔드 (쿠팡 공식 예시 기반)
// CORS 헤더 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GMT+0 시간대 설정 (쿠팡 공식 예시와 동일)
date_default_timezone_set("GMT+0");

// API 설정
require_once __DIR__ . '/coupang_credentials.php';
$ACCESS_KEY = COUPANG_ACCESS_KEY;
$SECRET_KEY = COUPANG_SECRET_KEY;
$SUB_ID = "hotlink1";
$IMAGE_SIZE = "80x80";

/**
 * 에러 응답 함수
 */
function sendErrorResponse($message, $debug = null) {
    $response = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($debug) {
        $response['debug'] = $debug;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * 성공 응답 함수
 */
function sendSuccessResponse($data, $debug = null) {
    $response = [
        'success' => true,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($debug) {
        $response['debug'] = $debug;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// 메인 로직
try {
    // GET 요청만 허용
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendErrorResponse('Only GET method is allowed');
    }
    
    // GMT 시간 사용 (중요!)
    $datetime = gmdate("Ymd").'T'.gmdate("His").'Z';
    $method = "GET";
    $path = "/v2/providers/affiliate_open_api/apis/openapi/v1/products/goldbox";

    // 쿠팡 공식 예시와 동일한 메시지 생성 방식
    $message = $datetime.$method.str_replace("?", "", $path);

    $algorithm = "HmacSHA256";
    $signature = hash_hmac('sha256', $message, $SECRET_KEY);

    // 쿠팡 공식 예시와 동일한 Authorization 헤더 생성
    $authorization = "CEA algorithm=HmacSHA256, access-key=".$ACCESS_KEY.", signed-date=".$datetime.", signature=".$signature;

    // 골드박스 API URL with 쿼리 파라미터
    $url = 'https://api-gateway.coupang.com'.$path;
    
    // 쿼리 파라미터 추가
    $queryParams = [];
    if (!empty($SUB_ID)) {
        $queryParams['subId'] = $SUB_ID;
    }
    if (!empty($IMAGE_SIZE)) {
        $queryParams['imageSize'] = $IMAGE_SIZE;
    }
    
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    // 쿠팡 공식 예시와 동일한 cURL 설정
    $curl = curl_init();        
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json;charset=UTF-8", 
        "Authorization:".$authorization
    ));        
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    
    $result = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    
    curl_close($curl);

    // cURL 오류 처리
    if ($result === false) {
        sendErrorResponse('cURL Error: ' . $curl_error, [
            'url' => $url,
            'method' => $method,
            'authorization' => $authorization,
            'message' => $message,
            'datetime' => $datetime
        ]);
    }

    // HTTP 응답 코드 확인
    if ($httpcode !== 200) {
        $errorResponse = json_decode($result, true);
        sendErrorResponse('HTTP Error: ' . $httpcode, [
            'url' => $url,
            'method' => $method,
            'authorization' => $authorization,
            'message' => $message,
            'datetime' => $datetime,
            'response' => $errorResponse,
            'current_time' => gmdate("Ymd").'T'.gmdate("His").'Z'
        ]);
    }

    // JSON 응답 파싱
    $responseData = json_decode($result, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendErrorResponse('Invalid JSON response', [
            'raw_response' => substr($result, 0, 500)
        ]);
    }

    // 쿠팡 API 응답 검증
    if (!isset($responseData['rCode'])) {
        sendErrorResponse('Invalid API response format', [
            'response' => $responseData
        ]);
    }
    
    if ($responseData['rCode'] !== '0') {
        sendErrorResponse(
            'Coupang API Error: ' . ($responseData['rMessage'] ?? 'Unknown error'),
            [
                'rCode' => $responseData['rCode'],
                'rMessage' => $responseData['rMessage'] ?? '',
                'datetime' => $datetime
            ]
        );
    }

    // 상품 데이터 가공
    $products = $responseData['data'] ?? [];
    $processedProducts = [];
    
    foreach ($products as $product) {
        $processedProducts[] = [
            'productId' => $product['productId'] ?? 0,
            'productName' => $product['productName'] ?? '',
            'productPrice' => $product['productPrice'] ?? 0,
            'productImage' => $product['productImage'] ?? '',
            'productUrl' => $product['productUrl'] ?? '',
            'categoryName' => $product['categoryName'] ?? '',
            'isRocket' => $product['isRocket'] ?? false,
            'isFreeShipping' => $product['isFreeShipping'] ?? false
        ];
    }
    
    // 통계 정보 계산
    $stats = [
        'totalProducts' => count($processedProducts),
        'rocketCount' => count(array_filter($processedProducts, function($p) { return $p['isRocket']; })),
        'freeShippingCount' => count(array_filter($processedProducts, function($p) { return $p['isFreeShipping']; })),
        'avgPrice' => count($processedProducts) > 0 ? 
            round(array_sum(array_column($processedProducts, 'productPrice')) / count($processedProducts)) : 0,
        'minPrice' => count($processedProducts) > 0 ? min(array_column($processedProducts, 'productPrice')) : 0,
        'maxPrice' => count($processedProducts) > 0 ? max(array_column($processedProducts, 'productPrice')) : 0
    ];
    
    // 성공 응답
    sendSuccessResponse([
        'products' => $processedProducts,
        'stats' => $stats,
        'updateTime' => '매일 오전 7:30 업데이트'
    ], [
        'datetime' => $datetime,
        'message' => $message,
        'signature' => $signature,
        'totalApiProducts' => count($products)
    ]);
    
} catch (Exception $e) {
    sendErrorResponse('Server Error: ' . $e->getMessage());
}
?>
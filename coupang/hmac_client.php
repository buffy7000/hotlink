<?php
/**
 * HMAC Client for Coupang Open API
 * Provides authentication and HTTP request functionality
 */

/**
 * Send HTTP request with HMAC authentication
 * 
 * @param string $method HTTP method (GET, POST, etc)
 * @param string $url API endpoint URL
 * @param string|null $query Query string (optional)
 * @param array|null $body Request body (optional)
 * @param string $access_key Coupang API access key
 * @param string $secret_key Coupang API secret key
 * @return array Response data as associative array
 */
function send_http_request($method, $url, $query = null, $body = null, $access_key = null, $secret_key = null) {
    $authorization = generate_hmac($method, $url, $query, $access_key, $secret_key);
    
    // Append query string to URL if present
    if ($query) {
        $url = $url . '?' . $query;
    }
    
    // Convert body to JSON if present
    $json_body = $body ? json_encode($body) : null;
    
    // Setup curl options
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = [
        'Authorization: ' . $authorization,
        'Content-Type: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($json_body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);
    }
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: $error");
    }
    
    curl_close($ch);
    
    // Debug information for troubleshooting
    if ($http_code >= 400) {
        $decoded_response = json_decode($response, true);
        error_log("API Error - HTTP Code: $http_code, Response: " . print_r($decoded_response, true));
    }

    if (isset($_GET['debug_raw'])) {
        echo "\n[DEBUG] Server time (gmdate): " . gmdate('Y-m-d H:i:s') . " UTC\n";
        echo "[DEBUG] HTTP Code: $http_code\n";
        echo "[DEBUG] Raw Response: " . substr((string)$response, 0, 3000) . "\n";
    }

    // Parse and return JSON response
    return json_decode($response, true);
}

/**
 * Generate HMAC signature for authentication
 * 
 * @param string $method HTTP method
 * @param string $path API endpoint path
 * @param string|null $query Query string (optional)
 * @param string $access_key Coupang API access key
 * @param string $secret_key Coupang API secret key
 * @return string Authorization header value
 */
function generate_hmac($method, $path, $query, $access_key, $secret_key) {
    // Remove base URL if present
    $path = str_replace('https://api-gateway.coupang.com', '', $path);
    
    // 🔥 중요: UTC 시간을 정확히 사용해야 함!
    $datetime_utc = gmdate("ymd").'T'.gmdate("His").'Z';
    
    // Create message to sign - 쿼리 파라미터 처리 개선
    $message = $datetime_utc . $method . $path;
    if ($query) {
        $message .= $query;
    }
    
    // Generate signature
    $signature = hash_hmac('sha256', $message, $secret_key);
    
    // Format and return authorization header
    return "CEA algorithm=HmacSHA256, access-key={$access_key}, signed-date={$datetime_utc}, signature={$signature}";
}

/**
 * 디버깅용 HMAC 생성 함수
 * 
 * @param string $method HTTP method
 * @param string $path API endpoint path  
 * @param string|null $query Query string
 * @param string $access_key Coupang API access key
 * @param string $secret_key Coupang API secret key
 * @return array 디버깅 정보
 */
function debug_hmac($method, $path, $query, $access_key, $secret_key) {
    $path = str_replace('https://api-gateway.coupang.com', '', $path);
    $datetime_utc = gmdate("ymd").'T'.gmdate("His").'Z';
    $message = $datetime_utc . $method . $path;
    if ($query) {
        $message .= $query;
    }
    $signature = hash_hmac('sha256', $message, $secret_key);
    
    return [
        'datetime_utc' => $datetime_utc,
        'path' => $path,
        'query' => $query,
        'message' => $message,
        'signature' => $signature,
        'authorization' => "CEA algorithm=HmacSHA256, access-key={$access_key}, signed-date={$datetime_utc}, signature={$signature}"
    ];
}

// 골드박스 API 테스트 함수
function test_goldbox_api() {
    $access_key = '***REMOVED***';
    $secret_key = '***REMOVED***';
    
    $method = 'GET';
    $url = 'https://api-gateway.coupang.com/v2/providers/affiliate_open_api/apis/openapi/products/goldbox';
    $query = 'subId=hotlink1&imageSize=80x80';
    
    echo "=== HMAC 디버깅 정보 ===\n";
    $debug_info = debug_hmac($method, $url, $query, $access_key, $secret_key);
    print_r($debug_info);
    
    echo "\n=== API 호출 테스트 ===\n";
    try {
        $response = send_http_request($method, $url, $query, null, $access_key, $secret_key);
        print_r($response);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// 테스트 실행 (주석 해제하여 사용)
// test_goldbox_api();
?>
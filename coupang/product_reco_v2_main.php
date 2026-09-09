<?php
/**
 * Coupang OpenAPI Product Recommendation V2 Example
 * 
 * This example demonstrates how to use the Coupang OpenAPI to get product recommendations
 */

// Include HMAC client
require_once 'hmac_client.php';
require_once __DIR__ . '/coupang_credentials.php';

// Define the request body
$request = [
    'site' => [
        'id' => '123',
        'domain' => 'www.domain.com',
        'page' => 'https://www.domain.com/page/123'
    ],
    'affiliate' => [
        'subId' => 'sub-id-123'
    ],
    'user' => [
        'puid' => 'puid-123'
    ],
    'device' => [
        'id' => 'd41d8cd-98f0204-e980-0998-xxxxxxxxxx',
        'lmt' => 0,
        'ip' => '127.0.0.1',
        'ua' => 'Mozilla/5.0 (Linux; Android 6.0;) ...'
    ],
    'imp' => [
        'imageSize' => '200x200',
        'adType' => 2,
        'pos' => 4
    ]
];

// API credentials
$access_key = COUPANG_ACCESS_KEY;
$secret_key = COUPANG_SECRET_KEY;

try {
    // Send request to Coupang API
    $response = send_http_request(
        'POST',
        'https://api-gateway.coupang.com/v2/providers/affiliate_open_api/apis/openapi/v2/products/reco',
        null, // No query parameters
        $request,
        $access_key,
        $secret_key
    );
    
    // Print response
    echo "Response:\n";
    print_r($response);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

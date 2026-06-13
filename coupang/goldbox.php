<?php
/**
 * 골드박스 상품 조회
 * hmac_client.php를 활용해서 골드박스 API 호출
 */

// hmac_client.php 파일을 포함
require_once 'hmac_client.php';

// API 인증 정보
$access_key = '***REMOVED***';
$secret_key = '***REMOVED***';

// 골드박스 API 정보
$method = 'GET';
$url = 'https://api-gateway.coupang.com/v2/providers/affiliate_open_api/apis/openapi/products/goldbox';
$query = 'subId=hotlink1&imageSize=80x80';

try {
    echo "<h2>🎁 쿠팡 골드박스 상품</h2>\n";
    
    // API 호출
    $response = send_http_request($method, $url, $query, null, $access_key, $secret_key);
    
    // 응답 확인
    if ($response['rCode'] == '0') {
        echo "<p style='color: #666;'>" . htmlspecialchars($response['rMessage']) . "</p>\n";
        echo "<div style='margin: 20px 0;'>\n";
        
        // 상품 목록 표시
        foreach ($response['data'] as $index => $product) {
            echo "<div style='border: 1px solid #ddd; margin: 15px 0; padding: 15px; border-radius: 8px; display: flex; align-items: center;'>\n";
            
            // 상품 이미지
            echo "<img src='" . htmlspecialchars($product['productImage']) . "' alt='상품이미지' style='width: 80px; height: 80px; margin-right: 15px; border-radius: 4px;'>\n";
            
            echo "<div style='flex: 1;'>\n";
            // 상품명
            echo "<h3 style='margin: 0 0 8px 0; color: #333;'>" . htmlspecialchars($product['productName']) . "</h3>\n";
            
            // 가격
            echo "<p style='margin: 5px 0; font-size: 18px; font-weight: bold; color: #e63946;'>" . number_format($product['productPrice']) . "원</p>\n";
            
            // 카테고리 및 배송 정보
            echo "<p style='margin: 5px 0; color: #666;'>카테고리: " . htmlspecialchars($product['categoryName']) . "</p>\n";
            
            $shipping = '';
            if ($product['isRocket']) {
                $shipping .= '🚀 로켓배송 ';
            }
            if ($product['isFreeShipping']) {
                $shipping .= '📦 무료배송';
            }
            if ($shipping) {
                echo "<p style='margin: 5px 0; color: #28a745;'>$shipping</p>\n";
            }
            
            // 상품 링크
            echo "<a href='" . htmlspecialchars($product['productUrl']) . "' target='_blank' style='background: #ff6b35; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 8px;'>상품 보러가기</a>\n";
            echo "</div>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
        
        echo "<p style='background: #f8f9fa; padding: 10px; border-radius: 4px; color: #666; font-size: 12px;'>";
        echo "* 이 포스팅은 쿠팡 파트너스 활동의 일환으로, 이에 따른 일정액의 수수료를 제공받습니다.";
        echo "</p>\n";
        
    } else {
        echo "<p style='color: red;'>❌ API 호출 실패: " . htmlspecialchars($response['rMessage']) . "</p>\n";
        echo "<pre>" . print_r($response, true) . "</pre>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>🚨 에러 발생: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// JSON 형태로 데이터만 출력하고 싶은 경우 (아래 주석 해제)
/*
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
*/
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>쿠팡 골드박스</title>
    <style>
        body { 
            font-family: 'Malgun Gothic', Arial, sans-serif; 
            max-width: 1000px; 
            margin: 0 auto; 
            padding: 20px;
            background: #f8f9fa;
        }
        h2 { color: #333; text-align: center; }
    </style>
</head>
<body>
    <!-- 위의 PHP 코드가 실행됩니다 -->
</body>
</html>
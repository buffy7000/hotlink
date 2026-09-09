<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 에러 표시 (개발용 - 운영시 제거)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db_credentials.php';

try {
    // 파라미터 받기
    $source = $_GET['source'] ?? 'all';
    $category = $_GET['category'] ?? 'all';
    $sort = $_GET['sort'] ?? 'latest';
    $time = $_GET['time'] ?? '24h';
    $id = $_GET['id'] ?? null;

    // DB 연결
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=3306;dbname=pricetag_hotdeal;charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // 개별 항목 조회인 경우
    if ($id) {
        $sql = "SELECT
                    public_id,
                    source_id,
                    original_id,
                    title,
                    original_url,
                    thumbnail_url,
                    author_name,
                    view_count,
                    comment_count,
                    like_count,
                    price,
                    store_name,
                    category,
                    original_created_at
                FROM hotdeals
                WHERE status = 'active' AND public_id = ?
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $hotdeals = $stmt->fetchAll();
        
        // 응답 데이터
        echo json_encode([
            'success' => true,
            'data' => $hotdeals,
            'count' => count($hotdeals),
            'type' => 'single'
        ], JSON_UNESCAPED_UNICODE);
        
        exit; // 개별 조회 완료 후 종료
    }

    // 시간 조건 변환
    $timeCondition = '';
    switch($time) {
        case '3h':
            $timeCondition = "AND original_created_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR)";
            break;
        case '6h':
            $timeCondition = "AND original_created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)";
            break;
        case '12h':
            $timeCondition = "AND original_created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)";
            break;
        case '24h':
            $timeCondition = "AND original_created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            break;
        case '3d':
            $timeCondition = "AND original_created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
            break;
        case '7d':
            $timeCondition = "AND original_created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        default:
            $timeCondition = "AND original_created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    }
    
    // 소스 조건 (퀘사이존 source_id = 2, 클리앙 source_id = 3)
// 소스 조건 (퀘사이존 source_id = 2, 클리앙 source_id = 3, 어미새 source_id = 5)
$sourceCondition = '';
if ($source !== 'all') {
    if ($source === 'quasarzone') {
        $sourceCondition = "AND source_id = 2";
    } elseif ($source === 'clien') {
        $sourceCondition = "AND source_id = 3";
    } elseif ($source === 'eomisae') {
        $sourceCondition = "AND source_id = 5";
    } elseif ($source === 'ruliweb') {
        $sourceCondition = "AND source_id = 6";    
    } elseif ($source === 'coupang') {
        $sourceCondition = "AND source_id = 99";
    }
} else {
    // 전체 선택시 모든 핫딜 사이트 (source_id = 2, 3, 5, 6, 99)
    $sourceCondition = "AND source_id IN (2, 3, 5, 6, 99)";
}

// 카테고리 조건 (deal/CategoryClassifier.php의 hotdeal_categories()와 코드가 일치해야 함)
$validCategories = ['game_app', 'giftcard', 'baby_pet', 'fashion_beauty', 'food', 'living_kitchen', 'digital', 'etc'];
$categoryCondition = '';
if ($category !== 'all' && in_array($category, $validCategories, true)) {
    $categoryCondition = "AND category = " . $pdo->quote($category);
}

    // 정렬 조건
    $orderBy = '';
    switch($sort) {
        case 'latest':
            $orderBy = "ORDER BY original_created_at DESC";
            break;
        case 'comments':
            $orderBy = "ORDER BY comment_count DESC, original_created_at DESC";
            break;
        case 'views':
            $orderBy = "ORDER BY view_count DESC, original_created_at DESC";
            break;
        case 'likes':
            $orderBy = "ORDER BY like_count DESC, original_created_at DESC";
            break;
        default:
            $orderBy = "ORDER BY original_created_at DESC";
    }
    

    
    // 쿼리 실행
    $sql = "SELECT
                public_id,
                source_id,
                original_id,
                title,
                original_url,
                thumbnail_url,
                author_name,
                view_count,
                comment_count,
                like_count,
                price,
                store_name,
                category,
                original_created_at
            FROM hotdeals
            WHERE status = 'active'
            {$sourceCondition}
            {$categoryCondition}
            {$timeCondition}
            {$orderBy}
            LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $hotdeals = $stmt->fetchAll();
    

// 응답 데이터
    echo json_encode([
        'success' => true,
        'data' => $hotdeals,
        'count' => count($hotdeals),
        'type' => 'list',
        'debug' => [
            'source' => $source,
            'category' => $category,
            'sort' => $sort,
            'time' => $time,
            'sql' => $sql
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'type' => 'database'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'general'
    ], JSON_UNESCAPED_UNICODE);
}
?>
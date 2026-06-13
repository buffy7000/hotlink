<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // DB 파일 경로
    $dbPath = '/home/pricetag/hotlink.kr/config/database_mvno.php';
    
    if (!file_exists($dbPath)) {
        throw new Exception('DB 파일을 찾을 수 없습니다: ' . $dbPath);
    }
    
    require_once($dbPath);
    
    $db = new SimpleEventDB();
    
    $carrier = $_GET['carrier'] ?? 'all';
    $sort = $_GET['sort'] ?? 'latest';
    $eventId = $_GET['event_id'] ?? null;
    
    // 특정 이벤트 조회
    if ($eventId) {
        $event = $db->fetch("
            SELECT * FROM event 
            WHERE event_id = ?
        ", [$eventId]);
        
        echo json_encode([
            'success' => true,
            'data' => $event ? [$event] : []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
// WHERE 조건 구성
$where = "1=1";
$params = [];

// 종료일이 오늘 이후인 이벤트만 (진행중 + 예정)
$where .= " AND end_date >= CURDATE()";

if ($carrier !== 'all') {
    $where .= " AND carrier = ?";
    $params[] = $carrier;
}

// 정렬 조건
$orderBy = $sort === 'ending' ? 'end_date ASC' : 'created_at DESC';

$events = $db->fetchAll("
    SELECT * FROM event 
    WHERE $where 
    ORDER BY $orderBy
    LIMIT 100
", $params);

    
    echo json_encode([
        'success' => true,
        'data' => $events
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

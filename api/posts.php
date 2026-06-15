<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

// ===== 단일 게시글 조회 (post_id 파라미터) - 먼저 체크 =====
if (isset($_GET['highlight'])) {  // ← post_id 대신 highlight
    $postId = intval($_GET['highlight']);
    
    try {
        $db = new Database();
        
        $stmt = $db->query("SELECT 
            p.id,
            p.title,
            p.url as originalUrl,
            p.author,
            p.comments_count as comments,
            p.views_count as views,
            p.likes_count as likes,
            p.created_at,
            p.community_id
        FROM posts p
        WHERE p.id = ?", [$postId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // community_id를 site 이름으로 변환
            $siteMap = [
                1 => 'ppomppu',
                2 => 'clien',
                3 => 'natepann',
                4 => 'ruliweb',
                5 => 'theqoo',
                6 => 'mlbpark',
                7 => 'bobaedream',
                8 => 'humoruniv',
                9 => 'todayhumor'
            ];
            
            $data = [
                'id' => intval($row['id']),
                'title' => $row['title'],
                'originalUrl' => $row['originalUrl'],
                'author' => $row['author'],
                'comments' => $row['comments'] ? intval($row['comments']) : null,
                'views' => intval($row['views']),
                'likes' => intval($row['likes']),
                'site' => $siteMap[$row['community_id']] ?? 'unknown',
                'timeAgo' => getTimeAgo($row['created_at'])
            ];
            
            echo json_encode([
                'success' => true,
                'data' => [$data]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'error' => '게시글을 찾을 수 없습니다.'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
// ===== 단일 게시글 조회 끝 =====

// ===== 파일 캐싱 추가 (여기부터) =====
$communities = $_GET['communities'] ?? 'all';
$sort = $_GET['sort'] ?? 'hot';
$time = $_GET['time'] ?? '24h';
$specificDate = $_GET['date'] ?? null;

// 캐시 키 생성
$cache_key = $specificDate ? "{$communities}_{$sort}_{$specificDate}" : "{$communities}_{$sort}_{$time}";
$cache_file = "/tmp/hotlink_cache_{$cache_key}.json";
$cache_time = 300; // 5분

// 캐시 확인
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    echo file_get_contents($cache_file);
    exit;
}
// ===== 파일 캐싱 추가 (여기까지) =====

try {
    $db = new Database();
    
    // GET 파라미터 받기
    $communities = $_GET['communities'] ?? 'all';
    $sort = $_GET['sort'] ?? 'hot';
    $time = $_GET['time'] ?? '24h';
    $specificDate = $_GET['date'] ?? null;
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 200);
    
    // 1. 시간 필터링 조건
    $timeCondition = '';
    
    if ($specificDate) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $specificDate)) {
            $timeCondition = "DATE(created_at) = '$specificDate'";
        } else {
            throw new Exception('잘못된 날짜 형식입니다. YYYY-MM-DD 형식으로 입력해주세요.');
        }
    } else {
        $timeConditions = [
            '3h' => 'created_at >= NOW() - INTERVAL 3 HOUR',
            '6h' => 'created_at >= NOW() - INTERVAL 6 HOUR',
            '12h' => 'created_at >= NOW() - INTERVAL 12 HOUR',
            '24h' => 'created_at >= NOW() - INTERVAL 24 HOUR',
            '3d' => 'created_at >= NOW() - INTERVAL 3 DAY',
        ];
        $timeCondition = $timeConditions[$time] ?? $timeConditions['24h'];
    }
    
    // 2. 커뮤니티 필터링
    $communityFilter = '';
    $params = [];
    
    if ($communities !== 'all') {
        $communityList = explode(',', $communities);
        $siteMapping = [
            'ppomppu' => 1,
            'clien' => 2,
            'natepann' => 3,
            'ruliweb' => 4,
            'theqoo' => 5,
            'mlbpark' => 6,
            'bobaedream' => 7,
            'humoruniv' => 8,
            'todayhumor' => 9,
            'inven' => 10,
            'slrclub' => 11
        ];
        
        $communityIds = [];
        foreach ($communityList as $community) {
            if (isset($siteMapping[$community])) {
                $communityIds[] = $siteMapping[$community];
            }
        }
        
        if (!empty($communityIds)) {
            $placeholders = str_repeat('?,', count($communityIds) - 1) . '?';
            $communityFilter = "AND community_id IN ($placeholders)";
            $params = array_merge($params, $communityIds);
        }
    }
    
    // 3. 정렬 조건
    $sortConditions = [
        'hot' => 'rank_score DESC',
        'latest' => 'created_at DESC',
        'comments' => 'comments_count DESC',
        'views' => 'views_count DESC'
    ];
    $sortCondition = $sortConditions[$sort] ?? $sortConditions['hot'];

    // group_limit 모드: 커뮤니티별 상위 N개 보장 (UNION ALL)
    if (isset($_GET['group_limit'])) {
        $groupLimit = min(max(intval($_GET['group_limit']), 1), 20);
        $groupCommunityIds = [1, 3, 4, 5, 6, 7, 8, 9, 10, 11]; // clien 제외

        $siteMap = [
            1 => 'ppomppu', 2 => 'clien', 3 => 'natepann', 4 => 'ruliweb',
            5 => 'theqoo', 6 => 'mlbpark', 7 => 'bobaedream', 8 => 'humoruniv',
            9 => 'todayhumor', 10 => 'inven', 11 => 'slrclub'
        ];

        $cols = "id, title, comments_count, created_at, views_count, author, community_id, url, rank_score";
        $unionParts = [];
        foreach ($groupCommunityIds as $cid) {
            $unionParts[] = "(SELECT $cols FROM posts WHERE community_id = $cid AND $timeCondition ORDER BY $sortCondition LIMIT $groupLimit)";
        }
        $sql = implode(' UNION ALL ', $unionParts);

        $stmt = $db->query($sql, []);
        $results = $stmt->fetchAll();

        $formattedData = [];
        foreach ($results as $index => $item) {
            $formattedData[] = [
                'id'          => intval($item['id']),
                'rank'        => $index + 1,
                'title'       => $item['title'],
                'comments'    => $item['comments_count'] ? intval($item['comments_count']) : null,
                'timestamp'   => date('Y-m-d H:i', strtotime($item['created_at'])),
                'timeAgo'     => getTimeAgo($item['created_at']),
                'views'       => intval($item['views_count']),
                'author'      => $item['author'],
                'site'        => $siteMap[$item['community_id']] ?? 'unknown',
                'originalUrl' => $item['url']
            ];
        }

        $response = json_encode(['success' => true, 'data' => $formattedData], JSON_UNESCAPED_UNICODE);
        file_put_contents($cache_file, $response);
        echo $response;
        exit;
    }

    // 4. WHERE 절 구성
    $whereClause = "WHERE $timeCondition";
    if ($communityFilter) {
        $whereClause .= " $communityFilter";
    }

    // 5. SQL 쿼리 구성
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT 
            id,
            title,
            comments_count,
            created_at,
            views_count,
            author,
            community_id,
            url,
            rank_score
        FROM posts 
        $whereClause
        ORDER BY $sortCondition
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $db->query($sql, $params);
    $results = $stmt->fetchAll();
    
    // 6. 프론트엔드 형태로 데이터 변환
    $formattedData = [];
    foreach ($results as $index => $item) {
        // community_id를 site 이름으로 변환
        $siteMap = [
            1 => 'ppomppu',
            2 => 'clien',
            3 => 'natepann',
            4 => 'ruliweb',
            5 => 'theqoo',
            6 => 'mlbpark',
            7 => 'bobaedream',
            8 => 'humoruniv',
            9 => 'todayhumor',
            10 => 'inven',
            11 => 'slrclub'
        ];
        
        $formattedData[] = [
            'id' => intval($item['id']),
            'rank' => $offset + $index + 1,
            'title' => $item['title'],
            'comments' => $item['comments_count'] ? intval($item['comments_count']) : null,
            'timestamp' => date('Y-m-d H:i', strtotime($item['created_at'])),
            'timeAgo' => getTimeAgo($item['created_at']),
            'views' => intval($item['views_count']),
            'author' => $item['author'],
            'site' => $siteMap[$item['community_id']] ?? 'unknown',
            'originalUrl' => $item['url']
        ];
    }

    // 성공 응답
    echo json_encode([
        'success' => true,
        'data' => $formattedData,
        'searchDate' => $specificDate
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // 에러 응답
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// 시간차이 계산 함수
function getTimeAgo($datetime) {
    $now = new DateTime();
    $created = new DateTime($datetime);
    $diff = $now->diff($created);
    
    if ($diff->days > 0) {
        return $diff->days . '일전';
    } elseif ($diff->h > 0) {
        return $diff->h . '시간전';
    } elseif ($diff->i > 0) {
        return $diff->i . '분전';
    } else {
        return '방금전';
    }
}
?>
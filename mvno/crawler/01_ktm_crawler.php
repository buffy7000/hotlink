<?php
/**
 * KT엠모바일 이벤트 크롤러 (순수 PHP cURL)
 * FastComet PHP 호스팅 환경에서 실행 가능
 * 
 * 사용법:
 * php ktm_crawler.php
 * 또는 웹 브라우저에서: https://hotlink.kr/mvno/crawler/01_ktm_crawler.php
 */

// DB 설정 (실제 값으로 변경 필요)
define('DB_HOST', 'localhost');
define('DB_NAME', 'pricetag_mvno');
define('DB_USER', 'pricetag_pricetag');
define('DB_PASS', '***REMOVED***');

// 크롤링 설정
define('API_URL', 'https://www.ktmmobile.com/event/eventListAjax.do');
define('BASE_URL', 'https://www.ktmmobile.com');
define('CARRIER', 'ktm');

// 수집할 카테고리 (01: 프로모션, 02: 요금제)
define('TARGET_CATEGORIES', ['01', '02']);

/**
 * API 호출 함수 (카테고리별)
 */
function fetchEventsFromAPI($pageNo = 1, $recordCount = 20, $category = '') {
    $postData = [
        'sbstCtg' => $category,  // 카테고리 필터 (빈 값 = 전체)
        'pageNo' => $pageNo,
        'recordCount' => $recordCount,
        'eventStatus' => 'ing',  // 진행 중인 이벤트만
        'eventBranch' => 'E',
        'rand' => round(microtime(true) * 1000)  // 타임스탬프 (밀리초)
    ];
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Referer: https://www.ktmmobile.com/event/eventBoardList.do'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: $error");
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: $httpCode");
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Decode Error: " . json_last_error_msg());
    }
    
    return $data;
}

/**
 * 이벤트 데이터 파싱
 */
function parseEventData($event) {
    // 날짜 변환: "2025-12-01 00:00:00" -> "2025-12-01"
    $startDate = !empty($event['eventStartDt']) 
        ? date('Y-m-d', strtotime($event['eventStartDt'])) 
        : null;
    $endDate = !empty($event['eventEndDt']) 
        ? date('Y-m-d', strtotime($event['eventEndDt'])) 
        : null;
    
    // 썸네일 URL (상대 경로를 절대 경로로 변환)
    $thumbnailUrl = null;
    if (!empty($event['listImg'])) {
        $thumbnailUrl = BASE_URL . $event['listImg'];
    }
    
    // 외부 링크 URL
    $linkUrl = !empty($event['linkUrlAdr']) ? $event['linkUrlAdr'] : null;
    $linkTarget = !empty($event['linkTarget']) ? $event['linkTarget'] : 'N';
    
    return [
        'carrier' => CARRIER,
        'event_id' => (string)$event['ntcartSeq'],
        'category' => !empty($event['eventCategory']) ? $event['eventCategory'] : null,
        'title' => $event['ntcartSubject'],
        'thumbnail_url' => $thumbnailUrl,
        'original_url' => BASE_URL . "/event/eventDetail.do?ntcartSeq=" . $event['ntcartSeq'] . "&sbstCtg=E",
        'start_date' => $startDate,
        'end_date' => $endDate,
        'view_count' => (int)$event['ntcartHitCnt'],
        'link_target' => $linkTarget,
        'link_url' => $linkUrl
    ];
}

/**
 * DB 연결
 */
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("DB Connection Error: " . $e->getMessage());
    }
}

/**
 * 이벤트 데이터 저장/업데이트
 * 
 * @return array ['action' => 'insert'|'update'|'none', 'changes' => [...]]
 */
function upsertEvent($pdo, $eventData) {
    // 기존 데이터 조회
    $checkSql = "SELECT title, view_count, end_date FROM event 
                 WHERE carrier = :carrier AND event_id = :event_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        'carrier' => $eventData['carrier'],
        'event_id' => $eventData['event_id']
    ]);
    $existing = $checkStmt->fetch();
    
    $sql = "INSERT INTO event (
        carrier, event_id, category, title, thumbnail_url, original_url,
        start_date, end_date, view_count, link_target, link_url, created_at, updated_at
    ) VALUES (
        :carrier, :event_id, :category, :title, :thumbnail_url, :original_url,
        :start_date, :end_date, :view_count, :link_target, :link_url, NOW(), NOW()
    ) ON DUPLICATE KEY UPDATE
        category = VALUES(category),
        title = VALUES(title),
        thumbnail_url = VALUES(thumbnail_url),
        original_url = VALUES(original_url),
        start_date = VALUES(start_date),
        end_date = VALUES(end_date),
        view_count = VALUES(view_count),
        link_target = VALUES(link_target),
        link_url = VALUES(link_url),
        updated_at = NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($eventData);
    
    // 결과 분석
    if (!$existing) {
        return ['action' => 'insert', 'changes' => []];
    }
    
    // 변경 사항 추적
    $changes = [];
    if ($existing['title'] !== $eventData['title']) {
        $changes[] = '제목';
    }
    if ((int)$existing['view_count'] !== (int)$eventData['view_count']) {
        $changes[] = sprintf('조회수(%s→%s)', 
            number_format($existing['view_count']), 
            number_format($eventData['view_count'])
        );
    }
    if ($existing['end_date'] !== $eventData['end_date']) {
        $changes[] = '종료일';
    }
    
    if (empty($changes)) {
        return ['action' => 'none', 'changes' => []];
    }
    
    return ['action' => 'update', 'changes' => $changes];
}

/**
 * 메인 크롤링 함수
 */
function crawlKTMEvents() {
    $startTime = microtime(true);
    $totalEvents = 0;
    $newEvents = 0;
    $updatedEvents = 0;
    $unchangedEvents = 0;
    $skippedEvents = 0;  // 카테고리 필터로 제외된 이벤트
    $errors = [];
    
    echo "=== KT엠모바일 이벤트 크롤링 시작 ===\n";
    echo "시작 시간: " . date('Y-m-d H:i:s') . "\n";
    echo "대상 카테고리: " . implode(', ', array_map(function($cat) {
        $names = ['01' => '프로모션', '02' => '요금제', '03' => '제휴', '04' => '결합'];
        return $names[$cat] ?? $cat;
    }, TARGET_CATEGORIES)) . "\n\n";
    
    try {
        // DB 연결
        $pdo = getDBConnection();
        echo "✓ DB 연결 성공\n\n";
        
        $allEvents = [];
        
        // 카테고리별로 API 호출
        foreach (TARGET_CATEGORIES as $category) {
            $categoryName = ['01' => '프로모션', '02' => '요금제'][$category] ?? $category;
            echo "[$categoryName] API 호출 중...\n";
            
            try {
                $response = fetchEventsFromAPI(1, 100, $category);
                
                if (!isset($response['eventList']) || !is_array($response['eventList'])) {
                    throw new Exception("Invalid API response structure");
                }
                
                $events = $response['eventList'];
                $count = count($events);
                echo "  ✓ {$count}개 이벤트 발견\n";
                
                $allEvents = array_merge($allEvents, $events);
                
            } catch (Exception $e) {
                echo "  ✗ 오류: " . $e->getMessage() . "\n";
                $errors[] = "[$categoryName] " . $e->getMessage();
            }
            
            // API 호출 간 짧은 대기 (서버 부하 방지)
            usleep(500000); // 0.5초
        }
        
        $totalEvents = count($allEvents);
        echo "\n총 {$totalEvents}개 이벤트 수집 완료\n\n";
        
        if ($totalEvents === 0) {
            echo "⚠️  수집된 이벤트가 없습니다.\n";
            return [
                'success' => false,
                'total' => 0,
                'new' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => $errors,
                'execution_time' => round(microtime(true) - $startTime, 2)
            ];
        }
        
        echo "=== DB 저장 시작 ===\n\n";
        
        // 각 이벤트 처리
        foreach ($allEvents as $index => $event) {
            try {
                // 카테고리 필터링 확인 (이중 체크)
                $eventCategory = $event['eventCategory'] ?? '';
                if (!in_array($eventCategory, TARGET_CATEGORIES)) {
                    $skippedEvents++;
                    continue;
                }
                
                $eventData = parseEventData($event);
                
                echo sprintf(
                    "[%d/%d] %s\n",
                    $index + 1,
                    $totalEvents,
                    mb_substr($eventData['title'], 0, 50) . (mb_strlen($eventData['title']) > 50 ? '...' : '')
                );
                echo "  ID: {$eventData['event_id']} | 카테고리: " . 
                     (['01' => '프로모션', '02' => '요금제'][$eventData['category']] ?? $eventData['category']) . 
                     " | 조회수: " . number_format($eventData['view_count']) . "\n";
                
                // DB에 저장
                $result = upsertEvent($pdo, $eventData);
                
                if ($result['action'] === 'insert') {
                    $newEvents++;
                    echo "  → ✨ 신규 등록\n";
                } elseif ($result['action'] === 'update') {
                    $updatedEvents++;
                    echo "  → 🔄 업데이트: " . implode(', ', $result['changes']) . "\n";
                } else {
                    $unchangedEvents++;
                    echo "  → ⚪ 변경 없음\n";
                }
                
            } catch (Exception $e) {
                $error = sprintf(
                    "이벤트 ID %s 처리 실패: %s",
                    $event['ntcartSeq'] ?? 'unknown',
                    $e->getMessage()
                );
                $errors[] = $error;
                echo "  ✗ $error\n";
            }
            
            echo "\n";
        }
        
    } catch (Exception $e) {
        echo "✗ 오류 발생: " . $e->getMessage() . "\n";
        $errors[] = $e->getMessage();
    }
    
    // 결과 요약
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "크롤링 완료\n";
    echo str_repeat("=", 50) . "\n";
    echo "종료 시간: " . date('Y-m-d H:i:s') . "\n";
    echo "실행 시간: {$executionTime}초\n\n";
    
    echo "【 처리 결과 】\n";
    echo "  총 수집: {$totalEvents}개\n";
    echo "  신규 등록: {$newEvents}개 ✨\n";
    echo "  업데이트: {$updatedEvents}개 🔄\n";
    echo "  변경 없음: {$unchangedEvents}개 ⚪\n";
    if ($skippedEvents > 0) {
        echo "  필터 제외: {$skippedEvents}개\n";
    }
    echo "  오류: " . count($errors) . "개" . (count($errors) > 0 ? " ⚠️" : "") . "\n";
    
    if (!empty($errors)) {
        echo "\n【 오류 목록 】\n";
        foreach ($errors as $error) {
            echo "  ✗ $error\n";
        }
    }
    
    echo "\n";
    
    return [
        'success' => count($errors) === 0,
        'total' => $totalEvents,
        'new' => $newEvents,
        'updated' => $updatedEvents,
        'unchanged' => $unchangedEvents,
        'skipped' => $skippedEvents,
        'errors' => $errors,
        'execution_time' => $executionTime
    ];
}

// 스크립트 실행
if (php_sapi_name() === 'cli') {
    // CLI에서 실행
    crawlKTMEvents();
} else {
    // 웹에서 실행
    header('Content-Type: text/plain; charset=utf-8');
    crawlKTMEvents();
}
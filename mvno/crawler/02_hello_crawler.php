<?php
/**
 * 헬로모바일 이벤트 크롤러 v1.0
 * API: ajaxEventList.do
 * 
 * 사용법:
 * php hello_crawler.php
 * 또는 웹 브라우저: https://yourdomain.com/crawlers/02_hello_crawler.php
 */

// 에러 표시 (디버깅용)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB 설정
require_once __DIR__ . '/../../config/db_credentials.php';
$DB_HOST = DB_HOST;
$DB_NAME = 'pricetag_mvno';
$DB_USER = DB_USER;
$DB_PASS = DB_PASS;

// 크롤링 설정
$API_URL = 'https://direct.lghellovision.net/event/ajaxEventList.do';
$BASE_URL = 'https://direct.lghellovision.net';
$CDN_URL = 'https://directcdn.lghellovision.net/upload/atcfile/board';
$CARRIER = 'hello';

// 수집할 카테고리 (telecom 필드)
$TARGET_CATEGORIES = array(
    'USIM' => '알뜰요금제'
);


/**
 * API 호출
 */
function fetchEvents($apiUrl, $telecom = '', $startRow = 1, $endRow = 100) {
    $postData = array(
        'openyn' => 'Y',
        'flag' => 'B107',
        'startRow' => $startRow,
        'endRow' => $endRow,
        'idxOfEvent' => '',
        'nPage' => '',
        'pgNum' => '',
        'isMobile' => 'false',
        'eventGubun' => '00',
        'status' => 'Y',
        'newPage' => 'Y',
        'telecom' => $telecom
    );
    
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Referer: https://direct.lghellovision.net/event/viewEventList.do'
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ));
    
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
function parseEventData($event, $cdnUrl, $carrier) {
    // 날짜 변환: "2025.12.03" -> "2025-12-03"
    $startDate = null;
    $endDate = null;
    
    if (!empty($event['sDate'])) {
        $startDate = str_replace('.', '-', $event['sDate']);
    }
    if (!empty($event['eDate'])) {
        $endDate = str_replace('.', '-', $event['eDate']);
    }
    
    // 썸네일 URL
    $thumbnailUrl = null;
    if (!empty($event['listFileName'])) {
        $thumbnailUrl = $cdnUrl . '/' . $event['listFileName'];
    }
    
    // 상세 페이지 URL
    $detailUrl = 'https://direct.lghellovision.net/event/viewEventDetail.do?idxOfEvent=' . $event['idxOfEvent'];
    
    // 외부 링크 URL (있는 경우)
    $linkUrl = null;
    if (!empty($event['urlDirectWeb'])) {
        $linkUrl = $event['urlDirectWeb'];
    } elseif (!empty($event['viewUrl'])) {
        $linkUrl = $event['viewUrl'];
    }
    
    // 카테고리 매핑
    $categoryMap = array(
        'USIM' => '01',      // 알뜰요금제
        'PHONE' => '02',     // 휴대폰
        'ETC' => '03'        // 기타
    );
    $category = isset($categoryMap[$event['telecom']]) ? $categoryMap[$event['telecom']] : null;
    
    // 제목에서 HTML 태그 제거
    $title = strip_tags($event['title']);
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return array(
        'carrier' => $carrier,
        'event_id' => (string)$event['idxOfEvent'],
        'category' => $category,
        'title' => $title,
        'thumbnail_url' => $thumbnailUrl,
        'original_url' => $detailUrl,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'view_count' => (int)$event['hitCount'],
        'link_target' => !empty($linkUrl) ? 'Y' : 'N',
        'link_url' => $linkUrl
    );
}

/**
 * DB 연결
 */
function getDBConnection($host, $name, $user, $pass) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            )
        );
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("DB Connection Error: " . $e->getMessage());
    }
}

/**
 * 이벤트 저장/업데이트
 */
function upsertEvent($pdo, $eventData) {
    // 기존 데이터 조회
    $checkSql = "SELECT title, view_count, end_date FROM event 
                 WHERE carrier = :carrier AND event_id = :event_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(array(
        'carrier' => $eventData['carrier'],
        'event_id' => $eventData['event_id']
    ));
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
        return array('action' => 'insert', 'changes' => array());
    }
    
    // 변경 사항 추적
    $changes = array();
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
        return array('action' => 'none', 'changes' => array());
    }
    
    return array('action' => 'update', 'changes' => $changes);
}

/**
 * 메인 크롤링
 */
function crawlHelloEvents($dbHost, $dbName, $dbUser, $dbPass, $apiUrl, $cdnUrl, $carrier, $categories) {
    $startTime = microtime(true);
    $totalEvents = 0;
    $newEvents = 0;
    $updatedEvents = 0;
    $unchangedEvents = 0;
    $skippedEvents = 0;
    $errors = array();
    
    echo "=== 헬로모바일 이벤트 크롤링 시작 ===\n";
    echo "시작 시간: " . date('Y-m-d H:i:s') . "\n";
    echo "대상 카테고리: " . implode(', ', array_values($categories)) . "\n\n";
    
    try {
        // DB 연결
        $pdo = getDBConnection($dbHost, $dbName, $dbUser, $dbPass);
        echo "✓ DB 연결 성공\n\n";
        
        $allEvents = array();
        
        // 카테고리별로 API 호출
        foreach ($categories as $telecom => $categoryName) {
            echo "[$categoryName] API 호출 중...\n";
            
            try {
                $response = fetchEvents($apiUrl, $telecom, 1, 100);
                
                if (!isset($response['list']) || !is_array($response['list'])) {
                    throw new Exception("Invalid API response structure");
                }
                
                $events = $response['list'];
                $count = count($events);
                $total = isset($response['nTotCnt']) ? $response['nTotCnt'] : $count;
                
                echo "  ✓ {$count}개 이벤트 발견 (전체: {$total}개)\n";
                
                // 활성 이벤트만 필터링
                foreach ($events as $event) {
                    if ($event['status'] === 'Y') {
                        $allEvents[] = $event;
                    } else {
                        $skippedEvents++;
                    }
                }
                
            } catch (Exception $e) {
                echo "  ✗ 오류: " . $e->getMessage() . "\n";
                $errors[] = "[$categoryName] " . $e->getMessage();
            }
            
            usleep(500000); // 0.5초 대기
        }
        
        $totalEvents = count($allEvents);
        echo "\n총 {$totalEvents}개 활성 이벤트 수집 완료\n";
        if ($skippedEvents > 0) {
            echo "  (비활성 {$skippedEvents}개 제외)\n";
        }
        echo "\n";
        
        if ($totalEvents === 0) {
            echo "⚠️  수집된 이벤트가 없습니다.\n";
            return array(
                'success' => false,
                'total' => 0,
                'new' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => $errors,
                'execution_time' => round(microtime(true) - $startTime, 2)
            );
        }
        
        echo "=== DB 저장 시작 ===\n\n";
        
        // 각 이벤트 처리
        foreach ($allEvents as $index => $event) {
            try {
                $eventData = parseEventData($event, $cdnUrl, $carrier);
                
                echo sprintf(
                    "[%d/%d] %s\n",
                    $index + 1,
                    $totalEvents,
                    mb_substr($eventData['title'], 0, 50, 'UTF-8') . 
                    (mb_strlen($eventData['title'], 'UTF-8') > 50 ? '...' : '')
                );
                
                $categoryName = $categories[$event['telecom']] ?? $event['telecom'];
                echo "  ID: {$eventData['event_id']} | 카테고리: {$categoryName} | 조회수: " . 
                     number_format($eventData['view_count']) . "\n";
                
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
                $eventId = isset($event['idxOfEvent']) ? $event['idxOfEvent'] : 'unknown';
                $error = sprintf("이벤트 ID %s 처리 실패: %s", $eventId, $e->getMessage());
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
        echo "  비활성 제외: {$skippedEvents}개\n";
    }
    echo "  오류: " . count($errors) . "개" . (count($errors) > 0 ? " ⚠️" : "") . "\n";
    
    if (!empty($errors)) {
        echo "\n【 오류 목록 】\n";
        foreach ($errors as $error) {
            echo "  ✗ $error\n";
        }
    }
    
    echo "\n";
    
    return array(
        'success' => count($errors) === 0,
        'total' => $totalEvents,
        'new' => $newEvents,
        'updated' => $updatedEvents,
        'unchanged' => $unchangedEvents,
        'skipped' => $skippedEvents,
        'errors' => $errors,
        'execution_time' => $executionTime
    );
}

// 스크립트 실행
if (php_sapi_name() === 'cli') {
    // CLI에서 실행
    crawlHelloEvents($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $API_URL, $CDN_URL, $CARRIER, $TARGET_CATEGORIES);
} else {
    // 웹에서 실행
    header('Content-Type: text/plain; charset=utf-8');
    crawlHelloEvents($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $API_URL, $CDN_URL, $CARRIER, $TARGET_CATEGORIES);
}
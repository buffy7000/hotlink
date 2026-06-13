<?php
/**
 * 이야기모바일 이벤트 크롤러 v1.0
 * HTML 파싱 방식
 * 
 * 사용법:
 * php eyagi_crawler.php
 * 또는 웹 브라우저: https://yourdomain.com/crawlers/03_eyagi_crawler.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 기존 DB 설정 파일 로드
require_once('/home/pricetag/hotlink.kr/config/database_mvno.php');

// 크롤링 설정
$BASE_URL = 'https://www.eyagi.co.kr';
$CARRIER = 'eyagi';

// 수집할 카테고리
$TARGET_CATEGORIES = array(
    'M' => '3사혜택',
    'P' => '제휴요금제'
);

/**
 * HTML 페이지 가져오기
 */
function fetchHTML($url) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        )
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
    
    return $response;
}

/**
 * HTML 파싱
 */
function parseEvents($html, $baseUrl) {
    $events = array();
    
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // li 태그 찾기
    $items = $xpath->query("//li[.//a[contains(@href, 'detail.php')]]");
    
    foreach ($items as $item) {
        try {
            // a 태그
            $links = $xpath->query(".//a[contains(@href, 'detail.php')]", $item);
            if ($links->length === 0) continue;
            
            $link = $links->item(0);
            $href = $link->getAttribute('href');
            
            // event_id 추출: detail.php?no=84424737
            if (preg_match('/no=(\d+)/', $href, $matches)) {
                $eventId = $matches[1];
            } else {
                continue;
            }
            
            // 썸네일 이미지
            $imgNodes = $xpath->query(".//img[@class='thumbnail']", $item);
            $thumbnailUrl = null;
            if ($imgNodes->length > 0) {
                $src = $imgNodes->item(0)->getAttribute('src');
                if (!empty($src)) {
                    $thumbnailUrl = $baseUrl . $src;
                }
            }
            
            // 제목
            $titleNodes = $xpath->query(".//h5[@class='title']", $item);
            $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : '';
            
            if (empty($title)) {
                continue;
            }
            
            // 날짜
            $dateNodes = $xpath->query(".//p[@class='date']", $item);
            $dateStr = $dateNodes->length > 0 ? trim($dateNodes->item(0)->textContent) : '';
            
            // 날짜 파싱
            $startDate = null;
            $endDate = null;
            
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $dateStr, $startMatches)) {
                $startDate = $startMatches[1];
            }
            
            if (preg_match('/~\s*(\d{4}-\d{2}-\d{2})/', $dateStr, $endMatches)) {
                $endDate = $endMatches[1];
            }
            
            $events[] = array(
                'event_id' => $eventId,
                'title' => $title,
                'thumbnail_url' => $thumbnailUrl,
                'original_url' => $baseUrl . '/shop/event/' . $href,
                'start_date' => $startDate,
                'end_date' => $endDate
            );
            
        } catch (Exception $e) {
            echo "  파싱 오류: " . $e->getMessage() . "\n";
        }
    }
    
    return $events;
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
function upsertEventToDB($db, $eventData) {
    // 기존 데이터 조회
    $existing = $db->fetch("
        SELECT title, end_date 
        FROM event 
        WHERE carrier = ? AND event_id = ?
    ", [$eventData['carrier'], $eventData['event_id']]);
    
    if (!$existing) {
        // 신규 등록 - fetchAll을 사용해서 INSERT 실행
        $db->fetchAll("
            INSERT INTO event (
                carrier, event_id, category, title, thumbnail_url, original_url,
                start_date, end_date, view_count, link_target, link_url, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ", [
            $eventData['carrier'],
            $eventData['event_id'],
            $eventData['category'],
            $eventData['title'],
            $eventData['thumbnail_url'],
            $eventData['original_url'],
            $eventData['start_date'],
            $eventData['end_date'],
            $eventData['view_count'],
            $eventData['link_target'],
            $eventData['link_url']
        ]);
        
        return array('action' => 'insert', 'changes' => array());
    }
    
    // 변경 사항 추적
    $changes = array();
    if ($existing['title'] !== $eventData['title']) {
        $changes[] = '제목';
    }
    if ($existing['end_date'] !== $eventData['end_date']) {
        $changes[] = '종료일';
    }
    
    if (empty($changes)) {
        return array('action' => 'none', 'changes' => array());
    }
    
    // 업데이트
    $db->fetchAll("
        UPDATE event SET
            category = ?,
            title = ?,
            thumbnail_url = ?,
            original_url = ?,
            start_date = ?,
            end_date = ?,
            link_target = ?,
            link_url = ?,
            updated_at = NOW()
        WHERE carrier = ? AND event_id = ?
    ", [
        $eventData['category'],
        $eventData['title'],
        $eventData['thumbnail_url'],
        $eventData['original_url'],
        $eventData['start_date'],
        $eventData['end_date'],
        $eventData['link_target'],
        $eventData['link_url'],
        $eventData['carrier'],
        $eventData['event_id']
    ]);
    
    return array('action' => 'update', 'changes' => $changes);
}

/**
 * 메인 크롤링
 */
function crawlEyagiEvents($baseUrl, $carrier, $categories) {
    $startTime = microtime(true);
    $totalEvents = 0;
    $newEvents = 0;
    $updatedEvents = 0;
    $unchangedEvents = 0;
    $errors = array();
    
    echo "=== 이야기모바일 이벤트 크롤링 시작 ===\n";
    echo "시작 시간: " . date('Y-m-d H:i:s') . "\n";
    echo "대상 카테고리: " . implode(', ', array_values($categories)) . "\n\n";
    
    try {
        // DB 연결 (database_mvno.php의 함수 사용)
        $db = new SimpleEventDB();
        echo "✓ DB 연결 성공\n\n";
        
        $allEvents = array();
        
        // 카테고리별로 페이지 호출
        foreach ($categories as $categoryCode => $categoryName) {
            echo "[$categoryName] 페이지 로딩 중...\n";
            
            $pageUrl = $baseUrl . '/shop/event/list.php?category=' . $categoryCode;
            
            try {
                $html = fetchHTML($pageUrl);
                echo "  ✓ HTML 다운로드 완료\n";
                
                $events = parseEvents($html, $baseUrl);
                $count = count($events);
                echo "  ✓ {$count}개 이벤트 발견\n\n";
                
                $allEvents = array_merge($allEvents, $events);
                
            } catch (Exception $e) {
                echo "  ✗ 오류: " . $e->getMessage() . "\n\n";
                $errors[] = "[$categoryName] " . $e->getMessage();
            }
            
            usleep(500000); // 0.5초 대기
        }
        
        $totalEvents = count($allEvents);
        echo "총 {$totalEvents}개 이벤트 수집 완료\n\n";
        
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
                $eventData = array(
                    'carrier' => $carrier,
                    'event_id' => $event['event_id'],
                    'category' => '01', // 알뜰요금제
                    'title' => $event['title'],
                    'thumbnail_url' => $event['thumbnail_url'],
                    'original_url' => $event['original_url'],
                    'start_date' => $event['start_date'],
                    'end_date' => $event['end_date'],
                    'view_count' => 0,
                    'link_target' => 'N',
                    'link_url' => null
                );
                
                echo sprintf(
                    "[%d/%d] %s\n",
                    $index + 1,
                    $totalEvents,
                    mb_substr($eventData['title'], 0, 50, 'UTF-8') . 
                    (mb_strlen($eventData['title'], 'UTF-8') > 50 ? '...' : '')
                );
                
                echo "  ID: {$eventData['event_id']} | 카테고리: 알뜰요금제\n";
                
                // DB에 저장
                $result = upsertEventToDB($db, $eventData);
                
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
                $eventId = $event['event_id'] ?? 'unknown';
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
        'errors' => $errors,
        'execution_time' => $executionTime
    );
}

// 스크립트 실행
if (php_sapi_name() === 'cli') {
    crawlEyagiEvents($BASE_URL, $CARRIER, $TARGET_CATEGORIES);
} else {
    header('Content-Type: text/plain; charset=utf-8');
    crawlEyagiEvents($BASE_URL, $CARRIER, $TARGET_CATEGORIES);
}
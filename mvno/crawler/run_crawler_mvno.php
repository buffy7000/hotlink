<?php
/**
 * MVNO 마스터 크롤러
 */

// 절대경로로 수정
require_once('/home/pricetag/hotlink.kr/config/database_mvno.php');

// 마스터 크롤러 실행 중임을 표시
define('MASTER_CRAWLER_RUNNING', true);

function writeLog($message) {
    $logFile = '/home/pricetag/hotlink.kr/logs/mvno_crawler.log';
    $logDir = '/home/pricetag/hotlink.kr/logs';
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

function runCrawler($name, $filePath) {
    writeLog("==================================================");
    writeLog("{$name} 크롤링 시작");
    writeLog("==================================================");
    
    try {
        // 파일 존재 확인
        if (!file_exists($filePath)) {
            throw new Exception("크롤러 파일을 찾을 수 없습니다: {$filePath}");
        }
        
        // 출력 버퍼링 시작
        ob_start();
        
        // 크롤러 실행
        include $filePath;
        
        $output = ob_get_clean();
        
        // 출력 표시
        echo $output;
        
        writeLog("==================================================");
        writeLog("{$name} 크롤링 완료 ✓");
        writeLog("==================================================");
        writeLog("");
        
        return true;
        
    } catch (Exception $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        writeLog("❌ {$name} 크롤링 오류: " . $e->getMessage());
        writeLog("");
        return false;
        
    } catch (Error $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        writeLog("❌ {$name} 크롤링 치명적 오류: " . $e->getMessage());
        writeLog("");
        return false;
    }
}

// ==========================================
// 메인 실행
// ==========================================

$startTime = microtime(true);

// 웹 실행 시 헤더 설정 (맨 처음에 한 번만)
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

writeLog("");
writeLog("##################################################");
writeLog("#                                                #");
writeLog("#         MVNO 통합 크롤러 시작                    #");
writeLog("#                                                #");
writeLog("##################################################");
writeLog("");
writeLog("실행 시간: " . date('Y-m-d H:i:s'));
writeLog("실행 환경: " . (php_sapi_name() === 'cli' ? 'CLI' : 'WEB'));
writeLog("");

$results = [];

// KTM모바일 크롤링
$results['KTM모바일'] = runCrawler(
    'KTM모바일', 
    '/home/pricetag/hotlink.kr/mvno/crawler/01_ktm_crawler.php'
);

// 헬로모바일 크롤링
$results['헬로모바일'] = runCrawler(
    '헬로모바일', 
    '/home/pricetag/hotlink.kr/mvno/crawler/02_hello_crawler.php'
);

// 이야기모바일 크롤링
$results['이야기모바일'] = runCrawler(
    '이야기모바일', 
    '/home/pricetag/hotlink.kr/mvno/crawler/03_eyagi_crawler.php'
);

$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

writeLog("");
writeLog("##################################################");
writeLog("#                                                #");
writeLog("#         MVNO 통합 크롤러 종료                    #");
writeLog("#                                                #");
writeLog("##################################################");
writeLog("");
writeLog("종료 시간: " . date('Y-m-d H:i:s'));
writeLog("총 실행 시간: {$executionTime}초");
writeLog("");

// 결과 요약
writeLog("【 크롤링 결과 요약 】");
foreach ($results as $name => $success) {
    $status = $success ? "✅ 성공" : "❌ 실패";
    writeLog("  {$name}: {$status}");
}

$successCount = count(array_filter($results));
$totalCount = count($results);
writeLog("");
writeLog("총 {$totalCount}개 크롤러 중 {$successCount}개 성공");

if ($successCount < $totalCount) {
    writeLog("");
    writeLog("⚠️  일부 크롤러가 실패했습니다. 상세 로그를 확인하세요.");
}

writeLog("");
writeLog("==================================================");
?>

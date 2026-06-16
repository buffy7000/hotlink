<?php
// 크론 진입점 - 모든 잡 크롤러를 순차 실행
// crontab: */30 * * * * php /home/pricetag/hotlink.kr/job/run_job_crawler.php >> /home/pricetag/hotlink.kr/logs/job_crawler.log 2>&1

define('JOB_CRAWLERS_DIR', __DIR__);

require_once JOB_CRAWLERS_DIR . '/BaseJobCrawler.php';
require_once JOB_CRAWLERS_DIR . '/crawler_designnine.php';
require_once JOB_CRAWLERS_DIR . '/crawler_uneedjob.php';
require_once JOB_CRAWLERS_DIR . '/crawler_okky.php';

function jobLog($message) {
    $logDir  = dirname(__DIR__) . '/logs';
    $logFile = $logDir . '/job_crawler.log';

    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

$crawlers = [
    'DesignNineCrawler' => '디자인그룹나인',
    'UneedJobCrawler'   => '유니드잡',
    'OkkyCrawler'       => 'OKKY',
];

$totalSaved = 0;
$errors     = [];

jobLog('===== 잡 크롤링 시작 =====');

foreach ($crawlers as $class => $name) {
    jobLog("{$name} 크롤링 중...");
    try {
        $crawler = new $class();
        $count   = $crawler->crawl();
        $totalSaved += $count;
        jobLog("{$name} 완료: {$count}개 저장");
    } catch (Exception $e) {
        $msg = "{$name} 오류: " . $e->getMessage();
        jobLog($msg);
        $errors[] = $msg;
    } catch (Error $e) {
        $msg = "{$name} 치명적 오류: " . $e->getMessage();
        jobLog($msg);
        $errors[] = $msg;
    }

    // 사이트 간 간격
    sleep(3);
}

jobLog("===== 잡 크롤링 완료: 총 {$totalSaved}개 저장 =====");

if (!empty($errors)) {
    jobLog("오류 목록:");
    foreach ($errors as $err) {
        jobLog("  - {$err}");
    }
}
?>

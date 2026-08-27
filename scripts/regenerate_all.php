<?php
/**
 * 이미 생성된 모든 토픽 페이지를 최신 템플릿/로직으로 일괄 재생성.
 *
 * topic_template.html의 구조(섹션 순서, 레이아웃)를 바꾼 뒤 이 스크립트를
 * 한 번 실행하면 topic/ 폴더에 이미 있는 모든 키워드 페이지에 반영된다.
 * (CSS/JS는 topic/assets/에 분리되어 있어 재생성 없이도 즉시 반영됨 - 이건
 *  구조 자체가 바뀌었을 때만 필요)
 *
 * 사용법:
 *   CLI: php regenerate_all.php
 *   HTTP: /scripts/regenerate_all.php?key=<CRAWLER_API_KEY>
 */

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $secretFile = __DIR__ . '/../crawler/config/crawler_api_key.php';
    if (!file_exists($secretFile)) {
        http_response_code(500);
        exit('API key not configured');
    }
    require $secretFile;
    if (!hash_equals(CRAWLER_API_KEY, $_GET['key'] ?? '')) {
        http_response_code(403);
        exit('Unauthorized');
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/topic_generator.php';
$communityMeta = require __DIR__ . '/community_meta.php';

$topicDir = __DIR__ . '/../topic';
$files = is_dir($topicDir) ? glob($topicDir . '/*.html') : [];

if (empty($files)) {
    echo "재생성할 토픽 페이지가 없습니다 (topic/ 폴더가 비어있음).\n";
    exit(0);
}

echo "=== 전체 토픽 페이지 재생성 시작 (" . count($files) . "개) ===\n";

$db = new Database();
$success = 0;
$skipped = 0;

foreach ($files as $file) {
    $keyword = basename($file, '.html');
    $result = generate_topic_page($db, $communityMeta, $keyword);

    if ($result === null) {
        echo "- {$keyword}: 관련 게시글 없음, 건너뜀\n";
        $skipped++;
        continue;
    }

    echo "- {$keyword}: 글 {$result['postCount']}개, 커뮤니티 {$result['communityCount']}개 완료\n";
    $success++;
}

echo "=== 완료: 성공 {$success}개, 건너뜀 {$skipped}개 ===\n";

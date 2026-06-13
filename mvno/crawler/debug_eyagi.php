<?php
/**
 * 이야기모바일 HTML 구조 확인 스크립트
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$PAGE_URL = 'https://www.eyagi.co.kr/shop/event/list.php';

echo "=== 이야기모바일 HTML 구조 분석 ===\n\n";

// HTML 가져오기
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => $PAGE_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    )
));

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP 상태: $httpCode\n";
echo "HTML 길이: " . strlen($html) . " bytes\n\n";

// detail.php 링크 찾기
preg_match_all('/detail\.php\?no=\d+/', $html, $matches);
echo "발견된 detail.php 링크: " . count($matches[0]) . "개\n";
if (count($matches[0]) > 0) {
    echo "샘플:\n";
    foreach (array_slice($matches[0], 0, 3) as $match) {
        echo "  - $match\n";
    }
}
echo "\n";

// li 태그 안의 detail.php 패턴 찾기
preg_match_all('/<li[^>]*>.*?detail\.php\?no=(\d+).*?<\/li>/s', $html, $liMatches, PREG_SET_ORDER);
echo "li 태그 안의 이벤트: " . count($liMatches) . "개\n\n";

if (count($liMatches) > 0) {
    echo "첫 번째 항목 샘플:\n";
    echo str_repeat("-", 70) . "\n";
    $sample = substr($liMatches[0][0], 0, 500);
    echo $sample . "...\n";
    echo str_repeat("-", 70) . "\n\n";
}

// 제목 패턴 찾기
preg_match_all('/<h5[^>]*class="title"[^>]*>(.*?)<\/h5>/s', $html, $titleMatches);
echo "발견된 제목 (h5.title): " . count($titleMatches[1]) . "개\n";
if (count($titleMatches[1]) > 0) {
    echo "샘플:\n";
    foreach (array_slice($titleMatches[1], 0, 5) as $title) {
        echo "  - " . trim(strip_tags($title)) . "\n";
    }
}
echo "\n";

// 3사혜택, 제휴요금제 포함된 제목 찾기
$targetTitles = array();
foreach ($titleMatches[1] as $title) {
    $cleanTitle = trim(strip_tags($title));
    if (mb_strpos($cleanTitle, '3사혜택') !== false || mb_strpos($cleanTitle, '제휴요금제') !== false) {
        $targetTitles[] = $cleanTitle;
    }
}
echo "대상 카테고리 포함된 제목: " . count($targetTitles) . "개\n";
if (count($targetTitles) > 0) {
    foreach ($targetTitles as $title) {
        echo "  ✓ $title\n";
    }
}
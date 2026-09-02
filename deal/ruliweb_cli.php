<?php
// GitHub Actions에서 실행: 루리웹 핫딜 게시판을 크롤링해 JSON으로 stdout 출력.
// hotlink.kr 서버에서 bbs.ruliweb.com으로의 연결 자체가 타임아웃되어(네트워크 레벨 차단)
// 서버 대신 Actions 러너에서 크롤링한다. DB에는 접근하지 않는다 —
// 저장은 deal/api/save_deal_posts.php(서버, localhost DB)가 담당.

ini_set('display_errors', 'stderr');
error_reporting(E_ALL);

$baseUrl = 'https://bbs.ruliweb.com';
$hotdealUrl = 'https://bbs.ruliweb.com/news/board/1020?page=1&view=thumbnail';
$sourceId = 6;

function ruliweb_deal_fetch($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => 'gzip, deflate',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.8',
        ],
    ]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return [$html, $code, $error];
}

function ruliweb_deal_remove_url_params($url) {
    $parsed = parse_url($url);
    $clean = ($parsed['scheme'] ?? '') ? $parsed['scheme'] . '://' : '';
    $clean .= $parsed['host'] ?? '';
    $clean .= $parsed['path'] ?? '';
    return $clean ?: ($parsed['path'] ?? $url);
}

function ruliweb_deal_parse_date($dateText) {
    if (empty($dateText)) return date('Y-m-d H:i:s');
    $now = time();
    $today = date('Y-m-d');

    if (preg_match('/^(\d{2}):(\d{2})$/', $dateText, $m)) {
        return $today . ' ' . $m[1] . ':' . $m[2] . ':00';
    }
    if (preg_match('/(\d+)시간\s*전/', $dateText, $m)) {
        return date('Y-m-d H:i:s', $now - ($m[1] * 3600));
    }
    if (preg_match('/(\d+)분\s*전/', $dateText, $m)) {
        return date('Y-m-d H:i:s', $now - ($m[1] * 60));
    }
    if (preg_match('/(\d+)일\s*전/', $dateText, $m)) {
        return date('Y-m-d H:i:s', $now - ($m[1] * 86400));
    }
    if (preg_match('/(\d{2})\.(\d{2})/', $dateText, $m)) {
        return date('Y') . '-' . $m[1] . '-' . $m[2] . ' 00:00:00';
    }
    return date('Y-m-d H:i:s');
}

function ruliweb_deal_clean_title($title) {
    $title = preg_replace('/\s*\(\d+\)\s*$/', '', $title);
    $title = preg_replace('/\s+/', ' ', $title);
    return trim(strip_tags($title));
}

function ruliweb_deal_extract_store_name($title) {
    if (preg_match('/\[([^\]]+)\]/', $title, $m)) {
        $store = trim($m[1]);
        if (mb_strlen($store) <= 15) return $store;
    }
    return null;
}

function ruliweb_deal_parse_item($xpath, $node, $baseUrl, $sourceId) {
    $idInput = $xpath->query('.//input[@class="info_article_id"]', $node)->item(0);
    if (!$idInput) return null;
    $originalId = $idInput->getAttribute('value');

    $subjectLink = $xpath->query('.//a[contains(@class, "subject_link")]', $node)->item(0);
    if (!$subjectLink) return null;

    $title = trim($subjectLink->textContent);
    $url = $subjectLink->getAttribute('href');
    if (empty($title) || empty($url)) return null;

    $cleanUrl = ruliweb_deal_remove_url_params($url);
    if (strpos($cleanUrl, 'http') !== 0) {
        $cleanUrl = $baseUrl . $cleanUrl;
    }

    $titleWrapper = $xpath->query('.//div[contains(@class, "title_wrapper")]', $node)->item(0);
    $category = null;
    if ($titleWrapper && preg_match('/^\s*\[([^\]]+)\]/', $titleWrapper->textContent, $m)) {
        $category = trim($m[1]);
    }

    $nickLink = $xpath->query('.//div[contains(@class, "nick")]//a', $node)->item(0);
    $authorName = $nickLink ? trim($nickLink->textContent) : '';

    if ($authorName === '핫딜관리자' || $category === '업체핫딜' || $category === 'BEST') {
        return null;
    }

    $thumbnailUrl = null;
    $thumbnail = $xpath->query('.//a[@class="thumbnail col_12"]', $node)->item(0);
    if ($thumbnail) {
        $style = $thumbnail->getAttribute('style');
        if (preg_match('/url\(["\']?(https:\/\/[^)"\']+\.(?:jpg|png))["\']?\)/', $style, $m)) {
            $thumbnailUrl = $m[1];
        }
    }

    $hitStrong = $xpath->query('.//span[@class="hit"]//strong', $node)->item(0);
    $viewCount = $hitStrong ? (int)preg_replace('/\D/', '', $hitStrong->textContent) : 0;

    $replySpan = $xpath->query('.//span[@class="num_reply"]', $node)->item(0);
    $commentCount = 0;
    if ($replySpan && preg_match('/\((\d+)\)/', $replySpan->textContent, $m)) {
        $commentCount = (int)$m[1];
    }

    $recomdStrong = $xpath->query('.//span[@class="recomd"]//strong', $node)->item(0);
    $likeCount = $recomdStrong ? (int)preg_replace('/\D/', '', $recomdStrong->textContent) : 0;

    $timeSpan = $xpath->query('.//span[@class="time"]', $node)->item(0);
    $dateText = $timeSpan ? str_replace('날짜', '', $timeSpan->textContent) : '';
    $createdAt = ruliweb_deal_parse_date(trim($dateText));

    return [
        'original_id' => $originalId,
        'title' => ruliweb_deal_clean_title($title),
        'original_url' => $cleanUrl,
        'thumbnail_url' => $thumbnailUrl,
        'author_name' => $authorName,
        'view_count' => $viewCount,
        'comment_count' => $commentCount,
        'like_count' => $likeCount,
        'price' => null,
        'store_name' => ruliweb_deal_extract_store_name($title),
        'original_created_at' => $createdAt,
        'source_id' => $sourceId,
    ];
}

function ruliweb_deal_parse_items($html, $baseUrl, $sourceId) {
    $items = [];
    $processedIds = [];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $articleNodes = $xpath->query('//div[@class="article col_12"]');
    fwrite(STDERR, "루리웹에서 {$articleNodes->length}개 아이템 발견\n");

    $count = 0;
    foreach ($articleNodes as $node) {
        if ($count >= 30) break;
        try {
            $item = ruliweb_deal_parse_item($xpath, $node, $baseUrl, $sourceId);
            if ($item && !in_array($item['original_id'], $processedIds)) {
                $items[] = $item;
                $processedIds[] = $item['original_id'];
                $count++;
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "아이템 파싱 오류: " . $e->getMessage() . "\n");
            continue;
        }
    }

    return $items;
}

[$html, $code, $error] = ruliweb_deal_fetch($hotdealUrl);

$status = 'error';
$errorMessage = null;
$items = [];

if (!$html || $code !== 200) {
    $errorMessage = "HTTP {$code}" . ($error ? " / curl 오류: {$error}" : '');
    fwrite(STDERR, "실패: {$errorMessage}\n");
} else {
    $items = ruliweb_deal_parse_items($html, $baseUrl, $sourceId);
    if (count($items) > 0) {
        $status = 'success';
        fwrite(STDERR, "총 " . count($items) . "개 파싱 완료\n");
    } else {
        $errorMessage = '파싱된 아이템이 없습니다 (선택자 변경 가능성)';
        fwrite(STDERR, "실패: {$errorMessage}\n");
    }
}

echo json_encode([
    'source' => 'ruliweb',
    'status' => $status,
    'error_message' => $errorMessage,
    'items' => $items,
], JSON_UNESCAPED_UNICODE);

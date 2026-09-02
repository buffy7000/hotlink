<?php
// GitHub Actions에서 실행: 어미새 핫딜 게시판을 크롤링해 JSON으로 stdout 출력.
// eomisae.co.kr이 hotlink.kr 서버 IP를 차단(HTTP 403)하고 있어 서버 대신
// Actions 러너(다른 IP 대역)에서 크롤링한다. DB에는 접근하지 않는다 —
// 저장은 deal/api/save_deal_posts.php(서버, localhost DB)가 담당.

ini_set('display_errors', 'stderr');
error_reporting(E_ALL);
// GitHub Actions 러너는 기본 UTC라, date()가 서버(KST)와 9시간 어긋나
// 방금 저장한 글이 "3시간 이내" 필터에서 누락되는 문제가 있었다.
date_default_timezone_set('Asia/Seoul');

$baseUrl = 'https://eomisae.co.kr';
$hotdealUrl = 'https://eomisae.co.kr/fs';
$sourceId = 5;

function eomisae_fetch($url) {
    $cookieJar = tempnam(sys_get_temp_dir(), 'eomisae_cookie_');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Referer: https://eomisae.co.kr/',
            'sec-ch-ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
        ],
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
    ]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    @unlink($cookieJar);
    return [$html, $code, $error];
}

function eomisae_clean_title($title) {
    return trim(strip_tags($title));
}

function eomisae_extract_price($title) {
    if (preg_match('/(\d{1,3}(?:,\d{3})*)\s*원/', $title, $m)) {
        return number_format((int)str_replace(',', '', $m[1])) . '원';
    }
    if (preg_match('/(\d+)\s*%\s*할인/', $title, $m)) {
        return $m[1] . '%할인';
    }
    if (preg_match('/\$\s*([\d,.]+)/', $title, $m)) {
        return '$' . $m[1];
    }
    return null;
}

function eomisae_extract_store_name($title, $category = '') {
    $stores = [
        '배달의민족', '배민', '쿠팡', '11번가', '옥션', 'G마켓', '위메프', '티몬',
        '인터파크', '네이버쇼핑', '다나와', '롯데온', 'SSG', '하이마트', '전자랜드',
        '아마존', '알리익스프레스', '타오바오', '이베이', '마켓컬리', '올리브영',
        '스타벅스', '맥도날드', 'KFC', '버거킹', '도미노피자', '피자헛',
    ];
    foreach ($stores as $store) {
        if (strpos($title, $store) !== false) return $store;
    }
    if (!empty($category) && $category !== '기타국내') return $category;
    return null;
}

function eomisae_normalize_image_url($src, $baseUrl) {
    if (strpos($src, '//') === 0) return 'https:' . $src;
    if (strpos($src, '/') === 0) return $baseUrl . $src;
    return $src;
}

function eomisae_parse_date($dateText) {
    if (empty($dateText)) return date('Y-m-d H:i:s');
    $currentTime = date('H:i:s');
    if (preg_match('/(\d{2})\.(\d{2})\.(\d{2})/', $dateText, $m)) {
        return '20' . $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $currentTime;
    }
    if (preg_match('/(\d{4})\.(\d{2})\.(\d{2})/', $dateText, $m)) {
        return "{$m[1]}-{$m[2]}-{$m[3]} {$currentTime}";
    }
    return date('Y-m-d H:i:s');
}

function eomisae_parse_item($xpath, $node, $baseUrl, $sourceId) {
    $titleNode = $xpath->query('.//h3/a', $node)->item(0);
    if (!$titleNode) return null;

    $title = trim($titleNode->textContent);
    $href = $titleNode->getAttribute('href');
    if (empty($title)) return null;

    if (strpos($href, 'http') !== 0) {
        $href = (strpos($href, '/') === 0) ? $baseUrl . $href : $baseUrl . '/' . $href;
    }

    preg_match('/\/fs\/(\d+)/', $href, $m);
    $originalId = $m[1] ?? null;
    if (!$originalId) return null;

    $thumbnailUrl = null;
    $thumbnailNode = $xpath->query('.//img[@class="tmb"]', $node)->item(0);
    if ($thumbnailNode) {
        $src = $thumbnailNode->getAttribute('src');
        if (!empty($src)) $thumbnailUrl = eomisae_normalize_image_url($src, $baseUrl);
    }

    $authorName = '';
    $authorNode = $xpath->query('.//div[@class="info"]//span//div[contains(@class, "member_")]', $node)->item(0);
    if ($authorNode) {
        $authorName = trim(preg_replace('/^\d+/', '', trim($authorNode->textContent)));
    }

    $dateNode = $xpath->query('.//p/span[last()]', $node)->item(0);
    $createdAt = eomisae_parse_date($dateNode ? trim($dateNode->textContent) : '');

    $viewCount = 0;
    $viewNode = $xpath->query('.//span[i[@class="ion-ios-eye"]]', $node)->item(0);
    if ($viewNode) $viewCount = (int)preg_replace('/[^0-9]/', '', trim($viewNode->textContent));

    $commentCount = 0;
    $commentNode = $xpath->query('.//span[i[@class="ion-ios-chatbubble"]]', $node)->item(0);
    if ($commentNode) $commentCount = (int)preg_replace('/[^0-9]/', '', trim($commentNode->textContent));

    $likeCount = 0;
    $likeNode = $xpath->query('.//span[i[@class="ion-ios-heart"]]', $node)->item(0);
    if ($likeNode) $likeCount = (int)preg_replace('/[^0-9]/', '', trim($likeNode->textContent));

    $category = '';
    $categoryNode = $xpath->query('.//span[@class="cate"]', $node)->item(0);
    if ($categoryNode) $category = trim(str_replace(',', '', $categoryNode->textContent));

    $cleanedTitle = eomisae_clean_title($title);
    if (strlen($cleanedTitle) < 3) return null;

    return [
        'original_id' => $originalId,
        'title' => $cleanedTitle,
        'original_url' => $href,
        'thumbnail_url' => $thumbnailUrl,
        'author_name' => $authorName,
        'view_count' => $viewCount,
        'comment_count' => $commentCount,
        'like_count' => $likeCount,
        'price' => eomisae_extract_price($title),
        'store_name' => eomisae_extract_store_name($title, $category),
        'original_created_at' => $createdAt,
        'source_id' => $sourceId,
    ];
}

function eomisae_parse_items($html, $baseUrl, $sourceId) {
    $items = [];
    $processedIds = [];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $articleNodes = $xpath->query('//div[@class="rt_area is_tmb"]');
    fwrite(STDERR, "어미새에서 {$articleNodes->length}개 아이템 발견\n");

    $count = 0;
    foreach ($articleNodes as $node) {
        if ($count >= 20) break;
        try {
            $item = eomisae_parse_item($xpath, $node, $baseUrl, $sourceId);
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

[$html, $code, $error] = eomisae_fetch($hotdealUrl);

$status = 'error';
$errorMessage = null;
$items = [];

if (!$html || $code !== 200) {
    $errorMessage = "HTTP {$code}" . ($error ? " / curl 오류: {$error}" : '');
    fwrite(STDERR, "실패: {$errorMessage}\n");
} else {
    $items = eomisae_parse_items($html, $baseUrl, $sourceId);
    if (count($items) > 0) {
        $status = 'success';
        fwrite(STDERR, "총 " . count($items) . "개 파싱 완료\n");
    } else {
        $errorMessage = '파싱된 아이템이 없습니다 (선택자 변경 가능성)';
        fwrite(STDERR, "실패: {$errorMessage}\n");
    }
}

echo json_encode([
    'source' => 'eomisae',
    'status' => $status,
    'error_message' => $errorMessage,
    'items' => $items,
], JSON_UNESCAPED_UNICODE);

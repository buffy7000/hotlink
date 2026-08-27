<?php
// GitHub Actions에서 실행: 오늘의유머 베스트 게시글을 크롤링해 JSON으로 stdout 출력
// hotlink.kr 서버에서 요청 시 Cloudflare JS 챌린지("Just a moment...")에 막혀 403이 남.
// Actions 러너 IP는 챌린지 없이 통과되는지 확인하기 위해 시도.

$baseUrl   = 'https://m.todayhumor.co.kr';
$targetUrl = 'https://m.todayhumor.co.kr/list.php?table=todaybest';
$limit     = 30;
$pages     = 2;

$cookieJar = tempnam(sys_get_temp_dir(), 'todayhumor_cookie_');

function todayhumor_fetch($url, $cookieJar) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_ENCODING       => 'gzip,deflate',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
        ],
    ]);
    $html  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    return [$html, $code, $errno, $error];
}

function todayhumor_clean_title($title) {
    $title = strip_tags($title);
    $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $title = preg_replace('/\s+/', ' ', $title);
    return trim($title);
}

function todayhumor_parse_time($timeString) {
    if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})$/', $timeString, $m)) {
        return $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[3], 2, '0', STR_PAD_LEFT)
             . ' ' . str_pad($m[4], 2, '0', STR_PAD_LEFT) . ':' . $m[5] . ':00';
    }
    return date('Y-m-d H:i:s');
}

function todayhumor_extract_item($item, $xpath, $baseUrl) {
    $href = $item->getAttribute('href');
    $url = strpos($href, 'http') === 0 ? $href : $baseUrl . '/' . $href;
    if (preg_match('/table=([^&]+)&no=([^&]+)/', $href, $m)) {
        $url = $baseUrl . '/view.php?table=' . $m[1] . '&no=' . $m[2];
    }

    $titleNode = $xpath->query('.//h2[@class="listSubject"]', $item)->item(0);
    $title = '';
    if ($titleNode) {
        $titleClone = $titleNode->cloneNode(true);
        foreach ($xpath->query('.//span[@class="list_comment_count"]', $titleClone) as $node) {
            $node->parentNode->removeChild($node);
        }
        $title = trim($titleClone->nodeValue);
    }
    if (strpos($title, '공지') !== false) return null;

    $author = '오유회원';
    $writerNode = $xpath->query('.//span[@class="list_writer"]', $item)->item(0);
    if ($writerNode) $author = trim($writerNode->nodeValue) ?: $author;

    $createdAt = date('Y-m-d H:i:s');
    $dateNode = $xpath->query('.//span[@class="listDate"]', $item)->item(0);
    if ($dateNode) {
        $time = trim($dateNode->nodeValue);
        if (!empty($time)) $createdAt = todayhumor_parse_time($time);
    }

    $commentsCount = 0;
    $commentNode = $xpath->query('.//span[@class="memo_count"]', $item)->item(0);
    if ($commentNode && preg_match('/\[(\d+)\]/', trim($commentNode->nodeValue), $m)) {
        $commentsCount = intval($m[1]);
    }

    $viewsCount = 0;
    $viewNode = $xpath->query('.//span[@class="list_viewCount"]', $item)->item(0);
    if ($viewNode) $viewsCount = intval(trim($viewNode->nodeValue));

    $likesCount = 0;
    $likeNode = $xpath->query('.//span[@class="list_okNokCount"]', $item)->item(0);
    if ($likeNode) $likesCount = intval(trim($likeNode->nodeValue));

    return [
        'community_id'   => 9,
        'title'          => todayhumor_clean_title($title),
        'url'            => $url,
        'author'         => $author,
        'comments_count' => $commentsCount,
        'views_count'    => $viewsCount,
        'likes_count'    => $likesCount,
        'created_at'     => $createdAt,
    ];
}

$allPosts = [];
$lastError = null;

for ($page = 1; $page <= $pages; $page++) {
    $url = $targetUrl . ($page > 1 ? "&page={$page}" : '');
    fwrite(STDERR, "{$page}페이지 요청: {$url}\n");

    [$html, $code, $errno, $error] = todayhumor_fetch($url, $cookieJar);

    if (!$html || $code !== 200) {
        $lastError = "HTTP {$code}" . ($error ? " / curl 오류 [{$errno}]: {$error}" : '');
        fwrite(STDERR, "  실패: {$lastError}\n");
        continue;
    }
    if (strlen($html) < 1000) {
        $lastError = 'HTML 응답이 너무 짧습니다 (' . strlen($html) . '바이트)';
        fwrite(STDERR, "  실패: {$lastError}\n");
        continue;
    }
    if (strpos($html, 'Just a moment') !== false || strpos($html, 'challenges.cloudflare.com') !== false) {
        $lastError = 'Cloudflare JS 챌린지 페이지 (봇 차단)';
        fwrite(STDERR, "  실패: {$lastError}\n");
        continue;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $items = $xpath->query('//a[div[contains(@class, "listLineBox")]]');
    fwrite(STDERR, "  찾은 항목: " . ($items ? $items->length : 0) . "개\n");

    foreach ($items as $item) {
        if (count($allPosts) >= $limit) break;
        $post = todayhumor_extract_item($item, $xpath, $baseUrl);
        if (!$post) continue;
        if (empty($post['title']) || strlen($post['title']) < 2) continue;
        foreach (['공지', '필독', '안내'] as $kw) {
            if (strpos($post['title'], $kw) !== false) continue 2;
        }
        $allPosts[] = $post;
    }

    if ($page < $pages) sleep(2);
}

@unlink($cookieJar);

fwrite(STDERR, "총 " . count($allPosts) . "개 파싱 완료\n");

$status = count($allPosts) > 0 ? 'success' : 'error';
$errorMessage = $status === 'error' ? ($lastError ?: '파싱된 게시글이 없습니다') : null;

echo json_encode([
    'community_id'  => 9,
    'status'        => $status,
    'error_message' => $errorMessage,
    'posts'         => $allPosts,
], JSON_UNESCAPED_UNICODE);

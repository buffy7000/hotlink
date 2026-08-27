<?php
// GitHub Actions에서 실행: 루리웹 베스트 게시글을 크롤링해 JSON으로 stdout 출력
// hotlink.kr 서버에서 bbs.ruliweb.com으로의 연결 자체가 타임아웃되어(네트워크 레벨 차단)
// 서버 대신 Actions 러너에서 크롤링한다.

$baseUrl   = 'https://bbs.ruliweb.com';
$targetUrl = 'https://bbs.ruliweb.com/best/all';
$orderby   = 'replycount';
$range     = '1h';
$limit     = 50;
$pages     = 3;

$cookieJar = tempnam(sys_get_temp_dir(), 'ruliweb_cookie_');

function ruliweb_fetch($url, $cookieJar) {
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

function ruliweb_clean_title($title) {
    $title = strip_tags($title);
    $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $title = preg_replace('/\s+/', ' ', $title);
    return trim($title);
}

function ruliweb_parse_time($timeString) {
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $m)) {
        return date('Y-m-d') . ' ' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2] . ':00';
    }
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $timeString, $m)) {
        return '20' . $m[1] . '-' . $m[2] . '-' . $m[3] . ' 00:00:00';
    }
    return date('Y-m-d H:i:s');
}

function ruliweb_extract_row($row, $xpath, $baseUrl) {
    $linkNode = $xpath->query('.//td[@class="subject"]//a[@class="subject_link deco flex center"]', $row)->item(0);
    if (!$linkNode) return null;

    $href = $linkNode->getAttribute('href');
    $url  = $baseUrl . preg_replace('/\?.*$/', '', $href);

    $titleNode = $xpath->query('.//span[@class="text_over"]', $linkNode)->item(0);
    $title = $titleNode ? trim($titleNode->nodeValue) : '';

    $commentsCount = 0;
    $commentNode = $xpath->query('.//span[@class="num_reply flex_item_1"]', $linkNode)->item(0);
    if ($commentNode && preg_match('/\((\d+)\)/', trim($commentNode->nodeValue), $m)) {
        $commentsCount = intval($m[1]);
    }

    $writerNode = $xpath->query('.//td[@class="writer text_over screen_out"]', $row)->item(0);
    $author = $writerNode ? trim($writerNode->nodeValue) : '루리웹';

    $recomdNode = $xpath->query('.//td[@class="recomd"]', $row)->item(0);
    $likesCount = $recomdNode ? intval(trim($recomdNode->nodeValue)) : 0;

    $hitNode = $xpath->query('.//td[@class="hit"]', $row)->item(0);
    $viewsCount = $hitNode ? intval(trim($hitNode->nodeValue)) : 0;

    $createdAt = date('Y-m-d H:i:s');
    $timeNode = $xpath->query('.//td[@class="time"]', $row)->item(0);
    if ($timeNode) {
        $timeClone = $timeNode->cloneNode(true);
        foreach ($xpath->query('.//input', $timeClone) as $input) {
            $input->parentNode->removeChild($input);
        }
        $time = trim($timeClone->nodeValue);
        if (!empty($time)) {
            $createdAt = ruliweb_parse_time($time);
        }
    }

    return [
        'community_id'   => 4,
        'title'          => ruliweb_clean_title($title),
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
    $url = $targetUrl . "?orderby={$orderby}&range={$range}&page={$page}";
    fwrite(STDERR, "{$page}페이지 요청: {$url}\n");

    [$html, $code, $errno, $error] = ruliweb_fetch($url, $cookieJar);

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

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $rows = $xpath->query('//tr[@class="table_body blocktarget mode_list"]');
    fwrite(STDERR, "  찾은 행: " . ($rows ? $rows->length : 0) . "개\n");

    foreach ($rows as $row) {
        $post = ruliweb_extract_row($row, $xpath, $baseUrl);
        if (!$post || empty($post['title']) || empty($post['url']) || strlen($post['title']) < 2) continue;
        $allPosts[] = $post;
    }

    if ($page < $pages) sleep(2);
}

@unlink($cookieJar);

fwrite(STDERR, "총 " . count($allPosts) . "개 파싱 완료\n");

$status = count($allPosts) > 0 ? 'success' : 'error';
$errorMessage = $status === 'error' ? ($lastError ?: '파싱된 게시글이 없습니다') : null;

echo json_encode([
    'community_id'  => 4,
    'status'        => $status,
    'error_message' => $errorMessage,
    'posts'         => $allPosts,
], JSON_UNESCAPED_UNICODE);

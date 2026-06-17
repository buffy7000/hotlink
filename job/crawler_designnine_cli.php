<?php
// GitHub Actions에서 실행: 크롤링 결과를 JSON으로 stdout 출력

$baseUrl   = 'https://www.designnine.co.kr:40000';
$targetUrl = $baseUrl . '/project/project_list1.html?mode1=search&search_gubun=103&search_part=112';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL             => $targetUrl,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_TIMEOUT         => 30,
    CURLOPT_CONNECTTIMEOUT  => 15,
    CURLOPT_SSL_VERIFYPEER  => false,
    CURLOPT_SSL_VERIFYHOST  => false,
    CURLOPT_USERAGENT       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
    CURLOPT_ENCODING        => 'gzip,deflate',
]);
$html  = curl_exec($ch);
$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);
curl_close($ch);

if (!$html || $code !== 200) {
    fwrite(STDERR, "크롤링 실패: HTTP {$code}\n");
    fwrite(STDERR, "curl 오류 [{$errno}]: {$error}\n");
    echo json_encode([]);
    exit(0); // 실패해도 워크플로우는 계속 진행
}

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$rows = $xpath->query("//tr[count(.//td[@class='text00']) >= 5]");
$jobs = [];

foreach ($rows as $row) {
    $tds = $xpath->query("./td[@class='text00']", $row);
    if ($tds->length < 5) continue;

    $linkNode = $xpath->query(".//a", $tds->item(2))->item(0);
    if (!$linkNode) continue;

    $title = trim(strip_tags($linkNode->textContent));
    $href  = $linkNode->getAttribute('href');
    if (empty($title) || empty($href)) continue;

    $url = $baseUrl . $href;
    if (preg_match('/num=(\d+)/', $url, $m)) {
        $url = $baseUrl . '/project/project_view2.html?num=' . $m[1];
    }

    $category = trim(strip_tags($tds->item(1)->textContent));
    $period   = trim(strip_tags($tds->item(3)->textContent));
    $status   = trim(strip_tags($tds->item(4)->textContent));

    $jobs[] = [
        'site_id'  => 1,
        'title'    => $title,
        'url'      => $url,
        'category' => $category,
        'period'   => $period,
        'status'   => $status,
    ];

    fwrite(STDERR, "  파싱: [{$status}] {$title}\n");
}

fwrite(STDERR, "총 " . count($jobs) . "개 파싱 완료\n");
echo json_encode($jobs, JSON_UNESCAPED_UNICODE);

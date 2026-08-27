<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookieJar = tempnam(sys_get_temp_dir(), 'th_cookie_');
$url = 'https://m.todayhumor.co.kr/list.php?table=todaybest';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
    ],
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
@unlink($cookieJar);

echo "<pre>1차 요청(쿠키 사용) HTTP: {$code}, 길이: " . strlen((string)$html) . ", 에러: {$err}\n";
echo "본문 앞부분:\n" . htmlspecialchars(substr((string)$html, 0, 500)) . "\n</pre>";

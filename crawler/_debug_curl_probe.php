<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$urls = [
    'https://bbs.ruliweb.com/best/all?orderby=replycount&range=1h&page=1',
    'https://bbs.ruliweb.com/best/all?orderby=replycount&range=1h&page=2',
    'https://bbs.ruliweb.com/best/all?orderby=replycount&range=1h&page=3',
];

$userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
];

echo "<pre>\n";
foreach ($urls as $i => $url) {
    file_put_contents(__DIR__ . '/debug_curlprobe_trace.log', "start {$i}: {$url}\n", FILE_APPEND);
    echo "요청 " . ($i+1) . ": {$url}\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => $userAgents[array_rand($userAgents)],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => 'gzip,deflate',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ]
    ]);

    usleep(rand(500000, 1000000));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    file_put_contents(__DIR__ . '/debug_curlprobe_trace.log', "done {$i}: http={$httpCode} len=" . strlen((string)$response) . " err={$err}\n", FILE_APPEND);
    echo "  -> HTTP {$httpCode}, 길이 " . strlen((string)$response) . " 바이트, 에러: '{$err}'\n";

    if ($i < count($urls) - 1) sleep(2);
}
echo "완료\n";
echo "</pre>\n";

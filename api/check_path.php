<?php
echo "현재 디렉토리: " . __DIR__ . "<br>";
echo "상위 디렉토리: " . dirname(__DIR__) . "<br>";
echo "<br>파일 목록:<br>";

// 상위 디렉토리의 파일/폴더 목록
$files = scandir(dirname(__DIR__));
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $path = dirname(__DIR__) . '/' . $file;
        echo $file . (is_dir($path) ? ' (폴더)' : ' (파일)') . "<br>";
    }
}

echo "<br>config 폴더 경로들 확인:<br>";
$paths = [
    dirname(__DIR__) . '/config/database_mvno.php',
    __DIR__ . '/../config/database_mvno.php',
    '/home/pricetag/hotlink.kr/config/database_mvno.php',
];

foreach ($paths as $path) {
    echo $path . ' : ' . (file_exists($path) ? '존재함 ✓' : '없음 ✗') . "<br>";
}

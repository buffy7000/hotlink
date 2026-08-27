<?php
$files = [
    'debug_ruliweb_page1.html',
    'debug_ruliweb_page2.html',
    'debug_ruliweb_page3.html',
    'debug_ruliweb_trace.log',
    'debug_curlprobe_trace.log',
];
foreach ($files as $f) {
    $p = __DIR__ . '/' . $f;
    if (file_exists($p)) {
        unlink($p);
        echo "deleted: {$f}\n";
    } else {
        echo "not found: {$f}\n";
    }
}

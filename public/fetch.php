<?php
$url = 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Logo_of_East_Java.svg/200px-Logo_of_East_Java.svg.png';
$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
    ]
]);
$data = file_get_contents($url, false, $context);
file_put_contents(__DIR__ . '/images/logo_jatim.png', $data);
echo "OK!";

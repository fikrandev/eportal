<?php
$url = 'https://raw.githubusercontent.com/t0k4rt/phpqrcode/master/phpqrcode.php';
$contents = file_get_contents($url);
if ($contents) {
    file_put_contents('phpqrcode.php', $contents);
    echo "OK";
} else {
    echo "FAILED";
}

<?php

declare(strict_types=1);

/*
 * Exercises images/resize.php through a loopback PHP server in a disposable
 * document root. Fixture images are generated here; no site data is read.
 */

$root = dirname(__DIR__);
$assertions = 0;
function red_resize_assert(bool $condition, string $message): void
{
    global $assertions;
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
    $assertions++;
}
if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD is required for the thumbnail self-test.\n");
    exit(1);
}

$web = sys_get_temp_dir() . '/redcms-resize-' . bin2hex(random_bytes(6));
mkdir($web . '/images/articles', 0700, true);
mkdir($web . '/images/gallery', 0700, true);
mkdir($web . '/includes', 0700, true);
copy($root . '/images/resize.php', $web . '/images/resize.php');
file_put_contents($web . '/includes/secret.txt', 'synthetic secret');
$photo = imagecreatetruecolor(400, 200);
imagejpeg($photo, $web . '/images/articles/photo one.jpg');
imagepng($photo, $web . '/images/gallery/shot.png');
imagejpeg($photo, $web . '/includes/outside.jpg');
file_put_contents($web . '/images/articles/fake.jpg', '<?php echo 1;');
imagedestroy($photo);
symlink($web . '/includes/outside.jpg', $web . '/images/articles/link.jpg');

$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $web],
    [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);

function red_resize_get(int $port, string $query): array
{
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
    $body = @file_get_contents('http://127.0.0.1:' . $port . '/images/resize.php' . $query, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#\AHTTP/\S+ (\d{3})#', $line, $m) === 1) {
            $status = (int) $m[1];
        }
    }
    return [$status, is_string($body) ? $body : ''];
}

try {
    for ($i = 0; $i < 100; $i++) {
        if (@fsockopen('127.0.0.1', $port) !== false) {
            break;
        }
        usleep(50000);
    }

    [$status, $body] = red_resize_get($port, '?w=180&h=110&img=/images/articles/photo%20one.jpg');
    $size = @getimagesizefromstring($body);
    red_resize_assert($status === 200 && is_array($size) && $size[0] === 180 && $size[1] === 110 && $size['mime'] === 'image/jpeg', 'article JPEG thumbnail keeps exact requested size');

    [$status, $body] = red_resize_get($port, '?w=100&img=/images/gallery/shot.png');
    $size = @getimagesizefromstring($body);
    red_resize_assert($status === 200 && is_array($size) && $size[0] === 100 && $size[1] === 50 && $size['mime'] === 'image/png', 'gallery PNG keeps aspect ratio from width only');

    [$status, $body] = red_resize_get($port, '?w=&img=/images/articles/photo%20one.jpg');
    $size = @getimagesizefromstring($body);
    red_resize_assert($status === 200 && is_array($size) && $size[0] === 400, 'empty width falls back to the original size like before');

    [$status, $body] = red_resize_get($port, '?w=99999&h=99999&img=/images/articles/photo%20one.jpg');
    $size = @getimagesizefromstring($body);
    red_resize_assert($status === 200 && is_array($size) && $size[0] === 2000 && $size[1] === 2000, 'dimensions are capped at 2000 pixels');

    foreach ([
        '' => 'missing image is refused',
        '?img=/includes/outside.jpg' => 'images outside articles and gallery are refused',
        '?img=/images/articles/../../includes/outside.jpg' => 'path traversal is refused',
        '?img=/images/articles/link.jpg' => 'symlinks leaving the directory are refused',
        '?img=/includes/secret.txt' => 'non-image files are refused',
        '?img=/images/articles/fake.jpg' => 'files that are not real images are refused',
        '?img=/images/articles/missing.jpg' => 'missing files are refused',
        '?w=-5&img=/images/articles/photo%20one.jpg' => 'negative width is refused',
        '?w=1e9&img=/images/articles/photo%20one.jpg' => 'non-integer width is refused',
        '?w[]=1&img=/images/articles/photo%20one.jpg' => 'array width is refused',
        '?img[]=/images/articles/photo%20one.jpg' => 'array image is refused',
        '?img=images/articles/photo%20one.jpg' => 'relative image path is refused',
        '?img=/images/resize.php' => 'the endpoint itself is refused',
    ] as $query => $message) {
        [$status, $body] = red_resize_get($port, $query);
        red_resize_assert($status === 404 && $body === '', $message);
    }
} finally {
    proc_terminate($server);
    proc_close($server);
    exec('rm -rf ' . escapeshellarg($web));
}

echo "Legacy image resize self-test passed: {$assertions} assertions.\n";

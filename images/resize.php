<?php

/*
 * RED-CMS thumbnail endpoint.
 *
 * Based on "Simple PHP Image Resizer", Copyright (c) 2012 Ryan Fait,
 * www.ryanfait.com. The redistribution notice above is retained.
 *
 * Usage (unchanged): /images/resize.php?w=100&h=200&img=/images/articles/photo.jpg
 *
 * Hardened for 5.1.2: only JPEG or PNG files that really live in
 * images/articles or images/gallery are served; width and height are
 * integers from 0 to 2000; oversized source images are refused. Any refusal
 * is a bodyless 404, so the endpoint cannot be used to probe the file system.
 */

const RED_RESIZE_MAX_EDGE = 2000;
const RED_RESIZE_MAX_SOURCE_PIXELS = 40000000;
const RED_RESIZE_ALLOWED_DIRECTORIES = ['articles', 'gallery'];

function red_resize_refuse(): void
{
    http_response_code(404);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

function red_resize_dimension(string $name): int
{
    if (!isset($_GET[$name])) {
        return 0;
    }
    $value = $_GET[$name];
    if ($value === '') {
        return 0;
    }
    if (!is_string($value) || preg_match('/\A[0-9]{1,5}\z/D', $value) !== 1) {
        red_resize_refuse();
    }
    $number = (int) $value;
    return $number > RED_RESIZE_MAX_EDGE ? RED_RESIZE_MAX_EDGE : $number;
}

function red_resize_source(): string
{
    $image = $_GET['img'] ?? null;
    if (!is_string($image)
        || strlen($image) > 512
        || preg_match('#\A/images/([a-z]+)/([^/\\\\\x00-\x1F\x7F]+)\z#D', $image, $match) !== 1
        || !in_array($match[1], RED_RESIZE_ALLOWED_DIRECTORIES, true)
        || $match[2] === '.'
        || $match[2] === '..'
    ) {
        red_resize_refuse();
    }
    $directory = realpath(__DIR__ . '/' . $match[1]);
    $file = realpath(__DIR__ . '/' . $match[1] . '/' . $match[2]);
    if (!is_string($directory)
        || !is_string($file)
        || !str_starts_with($file, $directory . DIRECTORY_SEPARATOR)
        || !is_file($file)
    ) {
        red_resize_refuse();
    }
    return $file;
}

function red_resize_thumbnail(string $image, int $width, int $height): void
{
    $properties = @getimagesize($image);
    if (!is_array($properties)) {
        red_resize_refuse();
    }
    $imageWidth = (int) $properties[0];
    $imageHeight = (int) $properties[1];
    $type = $properties['mime'] ?? '';
    if ($imageWidth < 1
        || $imageHeight < 1
        || $imageWidth * $imageHeight > RED_RESIZE_MAX_SOURCE_PIXELS
        || !in_array($type, ['image/jpeg', 'image/png'], true)
    ) {
        red_resize_refuse();
    }
    $ratio = $imageWidth / $imageHeight;

    if ($width === 0 && $height === 0) {
        $width = min($imageWidth, RED_RESIZE_MAX_EDGE);
        $height = (int) round($width / $ratio);
    } elseif ($width === 0) {
        $width = (int) round($height * $ratio);
    } elseif ($height === 0) {
        $height = (int) round($width / $ratio);
    }
    $width = max(1, min($width, RED_RESIZE_MAX_EDGE));
    $height = max(1, min($height, RED_RESIZE_MAX_EDGE));

    $source = $type === 'image/jpeg'
        ? @imagecreatefromjpeg($image)
        : @imagecreatefrompng($image);
    if ($source === false) {
        red_resize_refuse();
    }
    $thumbnail = imagecreatetruecolor($width, $height);
    if ($type === 'image/png') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    imagecopyresampled(
        $thumbnail,
        $source,
        0,
        0,
        0,
        0,
        $width,
        $height,
        $imageWidth,
        $imageHeight
    );

    header('Content-Type: ' . $type);
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    if ($type === 'image/jpeg') {
        imagejpeg($thumbnail);
    } else {
        imagepng($thumbnail);
    }
    imagedestroy($source);
    imagedestroy($thumbnail);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD'
) {
    red_resize_refuse();
}

$width = red_resize_dimension('w');
$height = red_resize_dimension('h');
red_resize_thumbnail(red_resize_source(), $width, $height);

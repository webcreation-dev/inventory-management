<?php

// This distribution keeps /public/ in asset URLs, while artisan serve already
// uses public/ as its document root. Resolve that prefix for local development.
$publicPath = __DIR__ . '/public';
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (str_starts_with($uri, '/public/')) {
    $file = realpath($publicPath . substr($uri, 7));
    $root = realpath($publicPath) . DIRECTORY_SEPARATOR;
    $types = [
        'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
        'pdf' => 'application/pdf', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
    ];
    $extension = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
    if ($file && str_starts_with($file, $root) && is_file($file) && isset($types[$extension])) {
        header('Content-Type: ' . $types[$extension]);
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        return;
    }
    http_response_code(404);
    return;
}
if ($uri !== '/' && is_file($publicPath . $uri)) {
    return false;
}
require $publicPath . '/index.php';

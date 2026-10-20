<?php

declare(strict_types=1);

// Asks the running server for /up with APP_URL's host, since TRUSTED_HOSTS refuses 127.0.0.1.
$port = getenv('PORT') ?: '8000';
$host = parse_url((string) getenv('APP_URL'), PHP_URL_HOST) ?: 'localhost';

$context = stream_context_create(['http' => [
    'header' => "Host: {$host}\r\nX-Forwarded-Proto: https\r\n",
    'timeout' => 2,
    'ignore_errors' => true,
]]);

$body = @file_get_contents("http://127.0.0.1:{$port}/up", false, $context);
$status = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match) ? (int) $match[1] : 0;

exit(false !== $body && 200 === $status ? 0 : 1);

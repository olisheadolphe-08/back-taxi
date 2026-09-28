<?php

header('Content-Type: application/json; charset=UTF-8');

$allowedOrigins = env('CORS_ORIGIN', '*');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($allowedOrigins === '*') {
    header('Access-Control-Allow-Origin: *');
} elseif (str_contains($allowedOrigins, ',')) {
    $originsList = array_map('trim', explode(',', $allowedOrigins));
    if (in_array($origin, $originsList, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
    }
} else {
    header("Access-Control-Allow-Origin: {$allowedOrigins}");
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

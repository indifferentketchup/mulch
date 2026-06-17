<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/analyze' && $method === 'POST') {
    require __DIR__ . '/analyze.php';
    return true;
}

if ($uri === '/redact' && $method === 'POST') {
    require __DIR__ . '/redact.php';
    return true;
}

if ($uri === '/health' && $method === 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    return true;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found']);
return true;

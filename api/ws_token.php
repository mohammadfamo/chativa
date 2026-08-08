<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if (!is_realtime_enabled()) {
    echo json_encode(['enabled' => false]);
    exit;
}

$me = current_user();

echo json_encode([
    'enabled' => true,
    'token'   => mint_ws_token((int) $me['id'], $me['username']),
    'wsUrl'   => realtime_ws_url(),
], JSON_UNESCAPED_UNICODE);

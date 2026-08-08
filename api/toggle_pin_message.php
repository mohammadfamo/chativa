<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$csrf = $data['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf']);
    exit;
}

$me = current_user();
$scope = (string) ($data['scope'] ?? ($_POST['scope'] ?? ''));
$messageId = (int) ($data['message_id'] ?? ($_POST['message_id'] ?? 0));

if (!in_array($scope, ['public', 'private'], true) || $messageId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

$owner = get_message_owner($scope, $messageId);
if ($owner === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

if ($scope === 'private') {
    if (!$owner['conversation_id'] || !user_in_conversation($owner['conversation_id'], (int) $me['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
} elseif (!is_admin()) {
    // فقط ادمین اجازه‌ی پین‌کردن پیام در چت عمومی را دارد (مثل کانال تلگرام)
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$result = toggle_pin_message($scope, $messageId);
if ($result === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

echo json_encode([
    'ok'             => true,
    'pinned'         => $result['pinned'],
    'message_id'     => $result['message_id'],
    'pinned_message' => $result['pinned_message'],
], JSON_UNESCAPED_UNICODE);

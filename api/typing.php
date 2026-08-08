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
$scope = (string) ($data['scope'] ?? ($_POST['scope'] ?? 'public'));

if (!in_array($scope, ['public', 'private', 'group'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_scope']);
    exit;
}

$conversationId = null;
if ($scope === 'private') {
    $conversationId = (int) ($data['conversation_id'] ?? ($_POST['conversation_id'] ?? 0));
    if ($conversationId <= 0 || !user_in_conversation($conversationId, (int) $me['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
} elseif ($scope === 'group') {
    $conversationId = (int) ($data['conversation_id'] ?? ($_POST['conversation_id'] ?? 0));
    if ($conversationId <= 0 || !is_group_member($conversationId, (int) $me['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
}

if ((int) $me['is_blocked'] === 0) {
    ping_typing($scope, $conversationId, (int) $me['id']);
}

echo json_encode(['ok' => true]);

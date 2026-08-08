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
$groupId = (int) ($data['group_id'] ?? ($_POST['group_id'] ?? 0));

if ($groupId <= 0 || !is_group_member($groupId, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

leave_group($groupId, (int) $me['id']);
broadcast('group:' . $groupId, 'member_removed', ['user_id' => (int) $me['id']]);

echo json_encode(['ok' => true]);

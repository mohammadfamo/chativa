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
$targetId = (int) ($data['user_id'] ?? ($_POST['user_id'] ?? 0));
$role = (string) ($data['role'] ?? ($_POST['role'] ?? ''));

// فقط owner می‌تواند کسی را ادمین کند یا از ادمینی خلع کند
$group = $groupId > 0 ? get_group_row($groupId) : null;
if ($group === null || (int) $group['owner_id'] !== (int) $me['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if (!in_array($role, ['admin', 'member'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_role']);
    exit;
}

$targetRole = group_member_role($groupId, $targetId);
if ($targetRole === null || $targetRole === 'owner') {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

update_group_member_role($groupId, $targetId, $role);
broadcast('group:' . $groupId, 'member_role_changed', ['user_id' => $targetId, 'role' => $role]);

echo json_encode(['ok' => true, 'role' => $role]);

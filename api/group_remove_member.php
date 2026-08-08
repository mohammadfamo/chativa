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

if ($groupId <= 0 || !is_group_admin($groupId, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$targetRole = group_member_role($groupId, $targetId);
if ($targetRole === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

if ($targetRole === 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'cannot_remove_owner']);
    exit;
}

// ادمین‌های عادی فقط می‌توانند اعضای معمولی را حذف کنند؛ حذف یک ادمین دیگر فقط کار owner است
$myRole = group_member_role($groupId, (int) $me['id']);
if ($targetRole === 'admin' && $myRole !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

remove_group_member($groupId, $targetId);
broadcast('group:' . $groupId, 'member_removed', ['user_id' => $targetId]);

echo json_encode(['ok' => true]);

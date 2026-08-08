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
$mode = (string) ($data['mode'] ?? ($_POST['mode'] ?? 'me'));

if (!in_array($scope, ['public', 'private', 'group'], true) || $messageId <= 0 || !in_array($mode, ['me', 'everyone'], true)) {
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

if ($scope === 'private' && (!$owner['conversation_id'] || !user_in_conversation($owner['conversation_id'], (int) $me['id']))) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
if ($scope === 'group' && (!$owner['conversation_id'] || !is_group_member($owner['conversation_id'], (int) $me['id']))) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if ($mode === 'everyone') {
    $isOwner = $owner['user_id'] === (int) $me['id'];
    // در گروه، مجوز حذف پیام دیگران با ادمین/مالک همان گروه است، نه ادمین کل سایت
    $canModerate = $scope === 'group' ? is_group_admin((int) $owner['conversation_id'], (int) $me['id']) : is_admin();
    if (!$isOwner && !$canModerate) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    if (!delete_message_for_everyone($scope, $messageId)) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    echo json_encode(['ok' => true, 'mode' => 'everyone']);
    exit;
}

hide_message_for_me($scope, $messageId, (int) $me['id']);
echo json_encode(['ok' => true, 'mode' => 'me']);

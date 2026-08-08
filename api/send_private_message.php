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

if ((int) $me['is_blocked'] === 1) {
    http_response_code(403);
    echo json_encode(['error' => 'user_blocked']);
    exit;
}

if (email_verification_required() && !is_email_verified($me)) {
    http_response_code(403);
    echo json_encode(['error' => 'email_not_verified']);
    exit;
}

$conversationId = (int) ($data['conversation_id'] ?? ($_POST['conversation_id'] ?? 0));
$body = trim((string) ($data['body'] ?? ($_POST['body'] ?? '')));

if ($conversationId <= 0 || !user_in_conversation($conversationId, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
if ($body === '') {
    http_response_code(422);
    echo json_encode(['error' => 'empty_message']);
    exit;
}
if (mb_strlen($body) > 2000) {
    http_response_code(422);
    echo json_encode(['error' => 'message_too_long']);
    exit;
}

$partner = conversation_partner($conversationId, (int) $me['id']);
if ($partner !== null && (int) $partner['is_blocked'] === 1) {
    http_response_code(403);
    echo json_encode(['error' => 'user_blocked']);
    exit;
}

if (!conversation_send_allowed($conversationId)) {
    http_response_code(403);
    echo json_encode(['error' => 'conversation_blocked']);
    exit;
}

// اگر پیامِ ریپلای‌شده معتبر (و در همین گفتگو) نباشد، بی‌صدا نادیده گرفته می‌شود
$replyToId = (int) ($data['reply_to_id'] ?? ($_POST['reply_to_id'] ?? 0));
if ($replyToId > 0) {
    $checkStmt = db()->prepare('SELECT id FROM private_messages WHERE id = ? AND conversation_id = ? AND deleted_at IS NULL');
    $checkStmt->execute([$replyToId, $conversationId]);
    if ($checkStmt->fetchColumn() === false) {
        $replyToId = 0;
    }
}

$stmt = db()->prepare('INSERT INTO private_messages (conversation_id, sender_id, body, reply_to_id) VALUES (?, ?, ?, ?)');
$stmt->execute([$conversationId, $me['id'], $body, $replyToId > 0 ? $replyToId : null]);
$id = (int) db()->lastInsertId();

$messageData = [
    'id'           => $id,
    'body'         => $body,
    'time_label'   => date('H:i'),
    'reply_to'     => $replyToId > 0 ? get_reply_info('private', $replyToId) : null,
    'user_id'      => (int) $me['id'],
    'username'     => $me['username'],
    'display_name' => display_name_of($me),
    'avatar'       => initials($me['username']),
    'color'        => $me['avatar_color'],
    'avatar_path'  => !empty($me['avatar_path']) ? url($me['avatar_path']) : null,
];

broadcast('conversation:' . $conversationId, 'message', $messageData);

echo json_encode(['message' => $messageData + ['is_mine' => true]], JSON_UNESCAPED_UNICODE);

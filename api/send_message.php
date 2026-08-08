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

$body = trim((string) ($data['body'] ?? ($_POST['body'] ?? '')));

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

// اگر پیامِ ریپلای‌شده معتبر (و در همین چت عمومی) نباشد، بی‌صدا نادیده گرفته می‌شود
$replyToId = (int) ($data['reply_to_id'] ?? ($_POST['reply_to_id'] ?? 0));
if ($replyToId > 0) {
    $checkStmt = db()->prepare('SELECT id FROM messages WHERE id = ? AND deleted_at IS NULL');
    $checkStmt->execute([$replyToId]);
    if ($checkStmt->fetchColumn() === false) {
        $replyToId = 0;
    }
}

$stmt = db()->prepare('INSERT INTO messages (user_id, body, reply_to_id) VALUES (?, ?, ?)');
$stmt->execute([$me['id'], $body, $replyToId > 0 ? $replyToId : null]);
$id = (int) db()->lastInsertId();

$messageData = [
    'id'           => $id,
    'body'         => $body,
    'time_label'   => date('H:i'),
    'reply_to'     => $replyToId > 0 ? get_reply_info('public', $replyToId) : null,
    'user_id'      => (int) $me['id'],
    'username'     => $me['username'],
    'display_name' => display_name_of($me),
    'avatar'       => initials($me['username']),
    'color'        => $me['avatar_color'],
    'avatar_path'  => !empty($me['avatar_path']) ? url($me['avatar_path']) : null,
];

broadcast('public', 'message', $messageData);

echo json_encode(['message' => $messageData + ['is_mine' => true]], JSON_UNESCAPED_UNICODE);

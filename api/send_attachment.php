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

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
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

$kind = (string) ($_POST['kind'] ?? '');
if (!in_array($kind, ['image', 'video', 'voice', 'document'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_kind']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(422);
    echo json_encode(['error' => 'no_file']);
    exit;
}

try {
    $attachment = save_uploaded_file($_FILES['file'], $kind);
} catch (UploadException $e) {
    http_response_code(422);
    echo json_encode(['error' => 'upload_failed', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$caption = trim((string) ($_POST['caption'] ?? ''));
if (mb_strlen($caption) > 2000) {
    $caption = mb_substr($caption, 0, 2000);
}

$duration = isset($_POST['duration']) ? max(0, min(3600, (int) $_POST['duration'])) : null;

$replyToId = (int) ($_POST['reply_to_id'] ?? 0);
if ($replyToId > 0) {
    $checkStmt = db()->prepare('SELECT id FROM messages WHERE id = ? AND deleted_at IS NULL');
    $checkStmt->execute([$replyToId]);
    if ($checkStmt->fetchColumn() === false) {
        $replyToId = 0;
    }
}

$stmt = db()->prepare(
    'INSERT INTO messages (user_id, body, reply_to_id, attachment_type, attachment_path, attachment_name, attachment_size, attachment_duration)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $me['id'],
    $caption,
    $replyToId > 0 ? $replyToId : null,
    $attachment['type'],
    $attachment['path'],
    $attachment['original_name'] !== '' ? $attachment['original_name'] : null,
    $attachment['size'],
    $duration,
]);
$id = (int) db()->lastInsertId();

$messageData = [
    'id'                  => $id,
    'body'                => $caption,
    'time_label'          => date('H:i'),
    'reply_to'            => $replyToId > 0 ? get_reply_info('public', $replyToId) : null,
    'user_id'             => (int) $me['id'],
    'username'            => $me['username'],
    'display_name'        => display_name_of($me),
    'avatar'              => initials($me['username']),
    'color'               => $me['avatar_color'],
    'avatar_path'         => !empty($me['avatar_path']) ? url($me['avatar_path']) : null,
    'attachment_type'     => $attachment['type'],
    'attachment_url'      => url($attachment['path']),
    'attachment_name'     => $attachment['original_name'] !== '' ? $attachment['original_name'] : null,
    'attachment_size'     => $attachment['size'],
    'attachment_duration' => $duration,
];

broadcast('public', 'message', $messageData);

echo json_encode(['message' => $messageData + ['is_mine' => true]], JSON_UNESCAPED_UNICODE);

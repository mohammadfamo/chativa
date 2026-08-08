<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$me = current_user();
$targetId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$targetUsername = trim((string) ($_GET['username'] ?? ''));

if ($targetId <= 0 && $targetUsername === '') {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

if ($targetId > 0) {
    $stmt = db()->prepare(
        'SELECT id, username, display_name, avatar_color, avatar_path, bio, is_admin, is_blocked, last_seen, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$targetId]);
} else {
    $stmt = db()->prepare(
        'SELECT id, username, display_name, avatar_color, avatar_path, bio, is_admin, is_blocked, last_seen, created_at
         FROM users WHERE username = ?'
    );
    $stmt->execute([$targetUsername]);
}
$user = $stmt->fetch();

if ($user === false) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$isSelf = (int) $user['id'] === (int) $me['id'];
$online = is_user_online($user['last_seen']);

$blockState = ['i_blocked' => false, 'blocked_by_partner' => false];
$conversationId = null;

if (!$isSelf) {
    $conversationId = find_conversation_id((int) $me['id'], (int) $user['id']);
    if ($conversationId !== null) {
        $blockState = conversation_block_state($conversationId, (int) $me['id']) ?? $blockState;
    }
}

echo json_encode([
    'id'                 => (int) $user['id'],
    'username'           => $user['username'],
    'display_name'       => display_name_of($user),
    'bio'                => $user['bio'],
    'avatar_html'        => avatar_html($user, 'avatar-lg', false),
    'is_admin'           => (bool) $user['is_admin'],
    'is_blocked_account' => (bool) $user['is_blocked'],
    'online'             => $online,
    'joined_at'          => $user['created_at'],
    'is_self'            => $isSelf,
    'conversation_id'    => $conversationId,
    'i_blocked'          => $blockState['i_blocked'],
    'blocked_by_partner' => $blockState['blocked_by_partner'],
]);

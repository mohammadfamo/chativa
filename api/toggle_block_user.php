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
$targetId = (int) ($data['user_id'] ?? ($_POST['user_id'] ?? 0));

if ($targetId <= 0 || $targetId === (int) $me['id']) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_user']);
    exit;
}

$stmt = db()->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$targetId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$conversationId = find_or_create_conversation((int) $me['id'], $targetId);
if ($conversationId === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_conversation']);
    exit;
}

$blocked = toggle_conversation_block($conversationId, (int) $me['id']);

broadcast('conversation:' . $conversationId, 'block', [
    'by_user_id' => (int) $me['id'],
    'blocked'    => $blocked,
]);

echo json_encode(['blocked' => $blocked, 'conversation_id' => $conversationId]);

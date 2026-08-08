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
$username = trim((string) ($data['username'] ?? ($_POST['username'] ?? '')));

if ($groupId <= 0 || !is_group_admin($groupId, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if ($username === '') {
    http_response_code(422);
    echo json_encode(['error' => 'empty_username']);
    exit;
}

$stmt = db()->prepare('SELECT id, username, display_name, avatar_color, avatar_path FROM users WHERE username = ?');
$stmt->execute([ltrim($username, '@')]);
$user = $stmt->fetch();

if ($user === false) {
    http_response_code(404);
    echo json_encode(['error' => 'user_not_found']);
    exit;
}

if (is_group_member($groupId, (int) $user['id'])) {
    http_response_code(409);
    echo json_encode(['error' => 'already_member']);
    exit;
}

add_group_member($groupId, (int) $user['id']);

echo json_encode([
    'member' => [
        'id'           => (int) $user['id'],
        'username'     => $user['username'],
        'display_name' => display_name_of($user),
        'role'         => 'member',
    ],
], JSON_UNESCAPED_UNICODE);

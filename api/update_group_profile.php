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

if ($groupId <= 0 || !is_group_admin($groupId, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$name = trim((string) ($data['name'] ?? ($_POST['name'] ?? '')));
$description = trim((string) ($data['description'] ?? ($_POST['description'] ?? '')));

if ($name === '') {
    http_response_code(422);
    echo json_encode(['error' => 'empty_name']);
    exit;
}
if (mb_strlen($name) > 100) {
    $name = mb_substr($name, 0, 100);
}
if (mb_strlen($description) > 300) {
    $description = mb_substr($description, 0, 300);
}

update_group_profile($groupId, $name, $description, null, false);
broadcast('group:' . $groupId, 'group_profile_updated', ['name' => $name, 'description' => $description]);

echo json_encode(['ok' => true, 'name' => $name, 'description' => $description], JSON_UNESCAPED_UNICODE);

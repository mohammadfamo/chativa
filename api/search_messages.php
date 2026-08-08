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
$scope = (string) ($_GET['scope'] ?? 'public');
$query = trim((string) ($_GET['q'] ?? ''));
$conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;

if (!in_array($scope, ['public', 'private'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

// حداقل ۲ کاراکتر برای جستجو - از اسکن کل جدول با کوئری خیلی کوتاه جلوگیری می‌کند
if (mb_strlen($query) < 2) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($scope === 'private') {
    if ($conversationId <= 0 || !user_in_conversation($conversationId, (int) $me['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    $stmt = db()->prepare(
        'SELECT pm.id, pm.body, pm.created_at, pm.sender_id, u.username, u.display_name
         FROM private_messages pm JOIN users u ON u.id = pm.sender_id
         WHERE pm.conversation_id = ? AND pm.deleted_at IS NULL AND pm.body LIKE ?
           AND NOT EXISTS (SELECT 1 FROM private_message_hides pmh WHERE pmh.message_id = pm.id AND pmh.user_id = ?)
         ORDER BY pm.id DESC
         LIMIT 30'
    );
    $stmt->execute([$conversationId, '%' . $query . '%', (int) $me['id']]);
} else {
    $stmt = db()->prepare(
        'SELECT m.id, m.body, m.created_at, m.user_id AS sender_id, u.username, u.display_name
         FROM messages m JOIN users u ON u.id = m.user_id
         WHERE m.deleted_at IS NULL AND m.body LIKE ?
           AND NOT EXISTS (SELECT 1 FROM message_hides mh WHERE mh.message_id = m.id AND mh.user_id = ?)
         ORDER BY m.id DESC
         LIMIT 30'
    );
    $stmt->execute(['%' . $query . '%', (int) $me['id']]);
}

$rows = $stmt->fetchAll();

$results = array_map(static function (array $row) use ($query, $me): array {
    return [
        'id'           => (int) $row['id'],
        'is_mine'      => (int) $row['sender_id'] === (int) $me['id'],
        'author'       => display_name_of($row),
        'snippet_html' => highlight_snippet((string) $row['body'], $query),
        'time_label'   => date('Y/m/d H:i', strtotime($row['created_at'])),
    ];
}, $rows);

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);

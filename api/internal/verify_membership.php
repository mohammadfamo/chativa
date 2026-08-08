<?php
/**
 * اندپوینت داخلی — فقط برای فراخوانی توسط سرور Node (node-server/server.js)
 * برای تایید اینکه یک کاربر واقعاً عضو یک گفتگوی خصوصی است، پیش از اینکه
 * اجازه‌ی join شدن به room آن گفتگو را در WebSocket بدهد.
 *
 * این اندپوینت با session کاربر کاری ندارد؛ فقط با هدر X-Internal-Secret
 * محافظت می‌شود.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$secret = $_SERVER['HTTP_X_INTERNAL_SECRET'] ?? '';

if ($secret === '' || !hash_equals(realtime_secret(), $secret)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$userId = (int) ($_GET['user_id'] ?? 0);
$conversationId = (int) ($_GET['conversation_id'] ?? 0);

if ($userId <= 0 || $conversationId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_params']);
    exit;
}

echo json_encode(['ok' => user_in_conversation($conversationId, $userId)]);

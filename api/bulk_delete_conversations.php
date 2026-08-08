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
$rawIds = $data['conversation_ids'] ?? ($_POST['conversation_ids'] ?? []);

if (!is_array($rawIds) || $rawIds === []) {
    http_response_code(422);
    echo json_encode(['error' => 'empty_selection']);
    exit;
}

// حداکثر ۱۰۰ آیتم در یک درخواست - محافظت در برابر درخواست‌های غیرمعمول بزرگ
$ids = array_values(array_unique(array_map('intval', array_slice($rawIds, 0, 100))));

$deletedIds = [];
foreach ($ids as $conversationId) {
    if ($conversationId <= 0 || !user_in_conversation($conversationId, (int) $me['id'])) {
        continue; // بی‌سروصدا رد شو - این کاربر عضو این مکالمه نیست یا شناسه نامعتبر است
    }
    // حذف گروهی همیشه «حذف از سایدبار من» است (یک‌طرفه، مثل آیتم تکی)، نه
    // حذف کامل و برگشت‌ناپذیر - برای یک عملیات دسته‌جمعی گزینه‌ی امن‌تر است.
    hide_conversation_for_me($conversationId, (int) $me['id']);
    $deletedIds[] = $conversationId;
}

echo json_encode(['ok' => true, 'deleted_ids' => $deletedIds]);

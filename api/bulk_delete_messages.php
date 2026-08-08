<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

/**
 * حذف دسته‌جمعی پیام‌ها - فعلاً فقط برای چت خصوصی (messages.php) پیاده و
 * از سمت کلاینت وایر شده است. مجوزها دقیقاً همان منطق api/delete_message.php
 * است، فقط روی چند شناسه پشت‌سرهم اجرا می‌شود؛ پیام‌هایی که کاربر اجازه‌ی
 * «حذف برای همه»‌شان را ندارد بی‌سروصدا رد می‌شوند (نه خطا).
 */

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
$conversationId = (int) ($data['conversation_id'] ?? ($_POST['conversation_id'] ?? 0));
$mode = (string) ($data['mode'] ?? ($_POST['mode'] ?? 'me'));
$rawIds = $data['message_ids'] ?? ($_POST['message_ids'] ?? []);

if ($conversationId <= 0 || !user_in_conversation($conversationId, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
if (!in_array($mode, ['me', 'everyone'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_mode']);
    exit;
}
if (!is_array($rawIds) || $rawIds === []) {
    http_response_code(422);
    echo json_encode(['error' => 'empty_selection']);
    exit;
}

// حداکثر ۲۰۰ پیام در هر درخواست
$ids = array_values(array_unique(array_map('intval', array_slice($rawIds, 0, 200))));

$deletedIds = [];
$skippedIds = [];

foreach ($ids as $messageId) {
    if ($messageId <= 0) {
        continue;
    }
    $owner = get_message_owner('private', $messageId);
    // فقط پیام‌های همین مکالمه - جلوگیری از دستکاری شناسه‌ی پیام مکالمه‌ی دیگر
    if ($owner === null || $owner['conversation_id'] !== $conversationId) {
        $skippedIds[] = $messageId;
        continue;
    }

    if ($mode === 'everyone') {
        $isOwner = $owner['user_id'] === (int) $me['id'];
        if (!$isOwner && !is_admin()) {
            $skippedIds[] = $messageId;
            continue;
        }
        if (delete_message_for_everyone('private', $messageId)) {
            $deletedIds[] = $messageId;
        } else {
            $skippedIds[] = $messageId;
        }
        continue;
    }

    hide_message_for_me('private', $messageId, (int) $me['id']);
    $deletedIds[] = $messageId;
}

echo json_encode(['ok' => true, 'mode' => $mode, 'deleted_ids' => $deletedIds, 'skipped_ids' => $skippedIds]);

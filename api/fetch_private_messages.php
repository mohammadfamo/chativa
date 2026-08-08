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
$conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
$afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;
// کلاینت این را صراحتاً 0 می‌فرستد وقتی تب مخفی/پس‌زمینه است (پولینگ در پس‌زمینه
// ادامه دارد تا badge/پیام‌های جدید به‌روز بمانند، اما نباید «دیده‌شده» ثبت شود
// مگر واقعاً کاربر در حال نگاه‌کردن به این مکالمه باشد).
$markSeen = !isset($_GET['mark_seen']) || $_GET['mark_seen'] !== '0';

if ($conversationId <= 0 || !user_in_conversation($conversationId, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

// چون این درخواست یعنی کاربر گفتگو را باز کرده/در حال دیدن آن است،
// پیام‌های طرف مقابل را دیده‌شده علامت می‌زنیم - مگر اینکه mark_seen=0 باشد
// (پولینگ پس‌زمینه‌ی یک تب مخفی/غیرفعال).
if ($markSeen) {
    mark_conversation_seen($conversationId, (int) $me['id']);
}

$partner = conversation_partner($conversationId, (int) $me['id']);

$stmt = db()->prepare(
    'SELECT pm.id, pm.sender_id, pm.body, pm.reply_to_id, pm.edited_at, pm.created_at,
            pm.attachment_type, pm.attachment_path, pm.attachment_name, pm.attachment_size, pm.attachment_duration,
            rpm.body AS reply_body, rpm.deleted_at AS reply_deleted_at,
            ru.username AS reply_username, ru.display_name AS reply_display_name
     FROM private_messages pm
     LEFT JOIN private_messages rpm ON rpm.id = pm.reply_to_id
     LEFT JOIN users ru ON ru.id = rpm.sender_id
     WHERE pm.conversation_id = ? AND pm.id > ? AND pm.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM private_message_hides pmh WHERE pmh.message_id = pm.id AND pmh.user_id = ?)
     ORDER BY pm.id ASC
     LIMIT 100'
);
$stmt->execute([$conversationId, $afterId, (int) $me['id']]);
$rows = $stmt->fetchAll();

$reactionsByMessage = get_reactions_for_messages('private', array_map(static fn (array $r): int => (int) $r['id'], $rows), (int) $me['id']);

$messages = array_map(static function (array $row) use ($me, $partner, $reactionsByMessage): array {
    $isMine = (int) $row['sender_id'] === (int) $me['id'];
    $sender = $isMine ? $me : $partner;
    return [
        'id'                  => (int) $row['id'],
        'body'                => $row['body'],
        'created_at'          => $row['created_at'],
        'time_label'          => date('H:i', strtotime($row['created_at'])),
        'edited_at'           => $row['edited_at'],
        'edited_time_label'   => $row['edited_at'] !== null ? date('H:i', strtotime($row['edited_at'])) : null,
        'reply_to'            => reply_to_payload($row),
        'user_id'             => (int) $row['sender_id'],
        'username'            => $sender['username'] ?? '',
        'display_name'        => $sender !== null ? display_name_of($sender) : '',
        'avatar'              => initials($sender['username'] ?? '?'),
        'color'               => $sender['avatar_color'] ?? '#999',
        'avatar_path'         => !empty($sender['avatar_path']) ? url($sender['avatar_path']) : null,
        'is_mine'             => $isMine,
        'attachment_type'     => $row['attachment_type'],
        'attachment_url'      => $row['attachment_path'] !== null ? url($row['attachment_path']) : null,
        'attachment_name'     => $row['attachment_name'],
        'attachment_size'     => $row['attachment_size'] !== null ? (int) $row['attachment_size'] : null,
        'attachment_duration' => $row['attachment_duration'] !== null ? (int) $row['attachment_duration'] : null,
        'reactions'           => $reactionsByMessage[(int) $row['id']] ?? [],
    ];
}, $rows);

$recentlyDeletedStmt = db()->prepare(
    "SELECT id FROM private_messages WHERE conversation_id = ? AND deleted_at IS NOT NULL AND deleted_at > (NOW() - INTERVAL 30 SECOND)"
);
$recentlyDeletedStmt->execute([$conversationId]);
$recentlyDeleted = $recentlyDeletedStmt->fetchAll(PDO::FETCH_COLUMN);

$recentlyEditedStmt = db()->prepare(
    "SELECT id, body, edited_at FROM private_messages
     WHERE conversation_id = ? AND edited_at IS NOT NULL AND edited_at > (NOW() - INTERVAL 30 SECOND) AND id <= ?"
);
$recentlyEditedStmt->execute([$conversationId, $afterId]);
$recentlyEdited = array_map(static function (array $row): array {
    return [
        'id'                => (int) $row['id'],
        'body'              => $row['body'],
        'edited_time_label' => date('H:i', strtotime($row['edited_at'])),
    ];
}, $recentlyEditedStmt->fetchAll());

// وضعیت بلاک و «حذف کامل مکالمه توسط طرف مقابل» باید در همین پاسخ پولینگ
// هم برگردانده شود، نه فقط از طریق broadcast لحظه‌ای WebSocket - وگرنه
// روی نصب‌هایی که Real-Time (Node.js) فعال نیست، این تغییرات تا رفرش
// دستی صفحه اصلاً به طرف مقابل نمی‌رسند.
$blockState = conversation_block_state($conversationId, (int) $me['id']) ?? ['i_blocked' => false, 'blocked_by_partner' => false];

// اگر کلاینت قبلاً پیامی دیده بود (afterId > 0) ولی الان دیگر هیچ پیامی
// در این مکالمه برایش باقی نمانده، یعنی طرف مقابل مکالمه را کامل حذف کرده.
$remainingStmt = db()->prepare(
    'SELECT COUNT(*) FROM private_messages WHERE conversation_id = ? AND deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM private_message_hides pmh WHERE pmh.message_id = private_messages.id AND pmh.user_id = ?)'
);
$remainingStmt->execute([$conversationId, (int) $me['id']]);
$conversationWiped = $afterId > 0 && (int) $remainingStmt->fetchColumn() === 0;

echo json_encode([
    'messages'           => $messages,
    'deleted_ids'        => array_map('intval', $recentlyDeleted),
    'edited'             => $recentlyEdited,
    'last_seen_id'       => last_seen_message_id($conversationId, (int) $me['id']),
    'typing'             => get_typing_usernames('private', $conversationId, (int) $me['id']),
    'partner_online'     => $partner !== null && is_user_online($partner['last_seen'] ?? null),
    'partner_status_label' => $partner !== null ? partner_status_label($partner['last_seen'] ?? null) : '',
    'pinned_message'     => get_pinned_message_payload('private', $conversationId),
    'blocked_by_partner' => $blockState['blocked_by_partner'],
    'i_blocked'          => $blockState['i_blocked'],
    'conversation_wiped' => $conversationWiped,
], JSON_UNESCAPED_UNICODE);

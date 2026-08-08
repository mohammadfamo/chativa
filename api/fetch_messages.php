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
$afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;

$stmt = db()->prepare(
    'SELECT m.id, m.body, m.reply_to_id, m.edited_at, m.created_at, m.attachment_type, m.attachment_path, m.attachment_name, m.attachment_size, m.attachment_duration,
            u.id AS user_id, u.username, u.display_name, u.avatar_color, u.avatar_path,
            rm.body AS reply_body, rm.deleted_at AS reply_deleted_at, ru.username AS reply_username, ru.display_name AS reply_display_name
     FROM messages m
     JOIN users u ON u.id = m.user_id
     LEFT JOIN messages rm ON rm.id = m.reply_to_id
     LEFT JOIN users ru ON ru.id = rm.user_id
     WHERE m.id > ? AND m.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM message_hides mh WHERE mh.message_id = m.id AND mh.user_id = ?)
     ORDER BY m.id ASC
     LIMIT 100'
);
$stmt->execute([$afterId, (int) $me['id']]);
$rows = $stmt->fetchAll();

// چون این درخواست یعنی کاربر چت عمومی را باز/در حال دیدن است، خواندن را
// تا آخرین پیامی که تا این لحظه می‌شناسد (afterId از سمت کلاینت) ثبت می‌کنیم
$latestKnownId = $rows !== [] ? (int) end($rows)['id'] : $afterId;
mark_public_read((int) $me['id'], max($afterId, $latestKnownId));

$reactionsByMessage = get_reactions_for_messages('public', array_map(static fn (array $r): int => (int) $r['id'], $rows), (int) $me['id']);

$messages = array_map(static function (array $row) use ($me, $reactionsByMessage): array {
    return [
        'id'                  => (int) $row['id'],
        'body'                => $row['body'],
        'created_at'          => $row['created_at'],
        'time_label'          => date('H:i', strtotime($row['created_at'])),
        'edited_at'           => $row['edited_at'],
        'edited_time_label'   => $row['edited_at'] !== null ? date('H:i', strtotime($row['edited_at'])) : null,
        'reply_to'            => reply_to_payload($row),
        'user_id'             => (int) $row['user_id'],
        'username'            => $row['username'],
        'display_name'        => display_name_of($row),
        'avatar'              => initials($row['username']),
        'color'               => $row['avatar_color'],
        'avatar_path'         => $row['avatar_path'] !== null ? url($row['avatar_path']) : null,
        'is_mine'             => (int) $row['user_id'] === (int) $me['id'],
        'attachment_type'     => $row['attachment_type'],
        'attachment_url'      => $row['attachment_path'] !== null ? url($row['attachment_path']) : null,
        'attachment_name'     => $row['attachment_name'],
        'attachment_size'     => $row['attachment_size'] !== null ? (int) $row['attachment_size'] : null,
        'attachment_duration' => $row['attachment_duration'] !== null ? (int) $row['attachment_duration'] : null,
        'reactions'           => $reactionsByMessage[(int) $row['id']] ?? [],
    ];
}, $rows);

// شناسه‌ی پیام‌هایی که اخیراً «برای همه» حذف شده‌اند - برای حذف آن‌ها از
// صفحه‌ی کاربرانی که فقط از طریق Polling (بدون WebSocket) به‌روزرسانی می‌شوند
$recentlyDeleted = db()->query(
    "SELECT id FROM messages WHERE deleted_at IS NOT NULL AND deleted_at > (NOW() - INTERVAL 30 SECOND)"
)->fetchAll(PDO::FETCH_COLUMN);

// همین منطق برای پیام‌های اخیراً ویرایش‌شده - کلاینت‌های فقط-Polling
$recentlyEditedStmt = db()->query(
    "SELECT id, body, edited_at FROM messages WHERE edited_at IS NOT NULL AND edited_at > (NOW() - INTERVAL 30 SECOND) AND id <= {$afterId}"
);
$recentlyEdited = array_map(static function (array $row): array {
    return [
        'id'                => (int) $row['id'],
        'body'              => $row['body'],
        'edited_time_label' => date('H:i', strtotime($row['edited_at'])),
    ];
}, $recentlyEditedStmt->fetchAll());

echo json_encode([
    'messages'       => $messages,
    'deleted_ids'    => array_map('intval', $recentlyDeleted),
    'edited'         => $recentlyEdited,
    'typing'         => get_typing_usernames('public', null, (int) $me['id']),
    'pinned_message' => get_pinned_message_payload('public', null),
], JSON_UNESCAPED_UNICODE);

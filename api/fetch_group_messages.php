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
$groupId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
$afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;
$markSeen = !isset($_GET['mark_seen']) || $_GET['mark_seen'] !== '0';

if ($groupId <= 0 || !is_group_member($groupId, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$stmt = db()->prepare(
    'SELECT gm.id, gm.sender_id, gm.body, gm.reply_to_id, gm.edited_at, gm.created_at,
            gm.attachment_type, gm.attachment_path, gm.attachment_name, gm.attachment_size, gm.attachment_duration,
            u.username, u.display_name, u.avatar_color, u.avatar_path,
            rgm.body AS reply_body, rgm.deleted_at AS reply_deleted_at,
            ru.username AS reply_username, ru.display_name AS reply_display_name
     FROM group_messages gm
     JOIN users u ON u.id = gm.sender_id
     LEFT JOIN group_messages rgm ON rgm.id = gm.reply_to_id
     LEFT JOIN users ru ON ru.id = rgm.sender_id
     WHERE gm.group_id = ? AND gm.id > ? AND gm.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM group_message_hides gmh WHERE gmh.message_id = gm.id AND gmh.user_id = ?)
     ORDER BY gm.id ASC
     LIMIT 100'
);
$stmt->execute([$groupId, $afterId, (int) $me['id']]);
$rows = $stmt->fetchAll();

$latestKnownId = $rows !== [] ? (int) end($rows)['id'] : $afterId;
if ($markSeen) {
    mark_group_seen($groupId, (int) $me['id'], max($afterId, $latestKnownId));
}

$messages = array_map(static function (array $row) use ($me): array {
    return [
        'id'                  => (int) $row['id'],
        'body'                => $row['body'],
        'created_at'          => $row['created_at'],
        'time_label'          => date('H:i', strtotime($row['created_at'])),
        'edited_at'           => $row['edited_at'],
        'edited_time_label'   => $row['edited_at'] !== null ? date('H:i', strtotime($row['edited_at'])) : null,
        'reply_to'            => reply_to_payload($row),
        'user_id'             => (int) $row['sender_id'],
        'username'            => $row['username'],
        'display_name'        => display_name_of($row),
        'avatar'              => initials($row['username']),
        'color'               => $row['avatar_color'],
        'avatar_path'         => $row['avatar_path'] !== null ? url($row['avatar_path']) : null,
        'is_mine'             => (int) $row['sender_id'] === (int) $me['id'],
        'attachment_type'     => $row['attachment_type'],
        'attachment_url'      => $row['attachment_path'] !== null ? url($row['attachment_path']) : null,
        'attachment_name'     => $row['attachment_name'],
        'attachment_size'     => $row['attachment_size'] !== null ? (int) $row['attachment_size'] : null,
        'attachment_duration' => $row['attachment_duration'] !== null ? (int) $row['attachment_duration'] : null,
    ];
}, $rows);

$recentlyDeletedStmt = db()->prepare(
    'SELECT id FROM group_messages WHERE group_id = ? AND deleted_at IS NOT NULL AND deleted_at > (NOW() - INTERVAL 30 SECOND)'
);
$recentlyDeletedStmt->execute([$groupId]);
$recentlyDeleted = $recentlyDeletedStmt->fetchAll(PDO::FETCH_COLUMN);

$recentlyEditedStmt = db()->prepare(
    'SELECT id, body, edited_at FROM group_messages
     WHERE group_id = ? AND edited_at IS NOT NULL AND edited_at > (NOW() - INTERVAL 30 SECOND) AND id <= ?'
);
$recentlyEditedStmt->execute([$groupId, $afterId]);
$recentlyEdited = array_map(static function (array $row): array {
    return [
        'id'                => (int) $row['id'],
        'body'              => $row['body'],
        'edited_time_label' => date('H:i', strtotime($row['edited_at'])),
    ];
}, $recentlyEditedStmt->fetchAll());

echo json_encode([
    'messages'    => $messages,
    'deleted_ids' => array_map('intval', $recentlyDeleted),
    'edited'      => $recentlyEdited,
    'typing'      => get_typing_usernames('group', $groupId, (int) $me['id']),
], JSON_UNESCAPED_UNICODE);

<?php
/**
 * حذف پیام (یک‌طرفه/دوطرفه) - مرحله ۶
 * -------------------------------------------------
 * «حذف برای من» (mode=me): پیام فقط برای کاربر جاری در جدول‌های
 * message_hides / private_message_hides پنهان می‌شود؛ پیام واقعی و برای
 * بقیه‌ی کاربران دست‌نخورده باقی می‌ماند.
 * «حذف برای همه» (mode=everyone): ستون deleted_at پیام ست می‌شود (حذف نرم،
 * نه حذف واقعی ردیف - برای حفظ یکپارچگی شمارنده‌ها/آی‌دی‌ها) و از طریق
 * WebSocket به بقیه‌ی کلاینت‌های متصل اطلاع داده می‌شود تا آنی حذف شود.
 */

declare(strict_types=1);

/** مخفی‌کردن یک پیام فقط برای کاربر جاری (بدون تاثیر روی بقیه) */
function hide_message_for_me(string $scope, int $messageId, int $userId): void
{
    $table = match ($scope) {
        'private' => 'private_message_hides',
        'group'   => 'group_message_hides',
        default   => 'message_hides',
    };
    $stmt = db()->prepare("INSERT IGNORE INTO {$table} (message_id, user_id) VALUES (?, ?)");
    $stmt->execute([$messageId, $userId]);
}

/**
 * اطلاعات مالک پیام برای بررسی مجوز حذف. برای scope='group'، conversation_id
 * در واقع group_id است (برای هم‌ساختار ماندن با scope='private' که در
 * delete_message.php/edit_message.php از همین کلید برای بررسی عضویت استفاده می‌شود).
 * @return array{user_id: int, conversation_id: ?int}|null
 */
function get_message_owner(string $scope, int $messageId): ?array
{
    if ($scope === 'private') {
        $stmt = db()->prepare('SELECT sender_id AS user_id, conversation_id FROM private_messages WHERE id = ?');
    } elseif ($scope === 'group') {
        $stmt = db()->prepare('SELECT sender_id AS user_id, group_id AS conversation_id FROM group_messages WHERE id = ?');
    } else {
        $stmt = db()->prepare('SELECT user_id, NULL AS conversation_id FROM messages WHERE id = ?');
    }
    $stmt->execute([$messageId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    return [
        'user_id'         => (int) $row['user_id'],
        'conversation_id' => $row['conversation_id'] !== null ? (int) $row['conversation_id'] : null,
    ];
}

/**
 * اطلاعات یک پیام ریپلای‌شده برای درج در پاسخ فوری ارسال پیام (مرحله ۷)
 * - همان شکلی که reply_to_payload() در functions.php برمی‌گرداند.
 * @return array{id:int, author:string, snippet:string, deleted:bool}|null
 */
function get_reply_info(string $scope, int $replyToId): ?array
{
    if ($scope === 'private') {
        $stmt = db()->prepare(
            'SELECT pm.body, pm.deleted_at, u.username, u.display_name
             FROM private_messages pm JOIN users u ON u.id = pm.sender_id
             WHERE pm.id = ?'
        );
    } elseif ($scope === 'group') {
        $stmt = db()->prepare(
            'SELECT gm.body, gm.deleted_at, u.username, u.display_name
             FROM group_messages gm JOIN users u ON u.id = gm.sender_id
             WHERE gm.id = ?'
        );
    } else {
        $stmt = db()->prepare(
            'SELECT m.body, m.deleted_at, u.username, u.display_name
             FROM messages m JOIN users u ON u.id = m.user_id
             WHERE m.id = ?'
        );
    }
    $stmt->execute([$replyToId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    return reply_to_payload([
        'reply_to_id'         => $replyToId,
        'reply_body'          => $row['body'],
        'reply_deleted_at'    => $row['deleted_at'],
        'reply_username'      => $row['username'],
        'reply_display_name'  => $row['display_name'],
    ]);
}

/**
 * ویرایش متن یک پیام (فقط فرستنده یا ادمین مجازند - بررسی مجوز در اندپوینت
 * انجام می‌شود). ستون edited_at ست می‌شود تا «ویرایش‌شده در...» نمایش داده
 * شود، و رویداد به‌روزرسانی برای بینندگان زنده broadcast می‌شود.
 * @return array{id:int, body:string, edited_at:string, conversation_id:?int}|null
 */
function edit_message_body(string $scope, int $messageId, string $newBody): ?array
{
    if ($scope === 'private') {
        $stmt = db()->prepare('SELECT conversation_id FROM private_messages WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$messageId]);
        $conversationId = $stmt->fetchColumn();
        if ($conversationId === false) {
            return null;
        }

        db()->prepare('UPDATE private_messages SET body = ?, edited_at = NOW() WHERE id = ?')->execute([$newBody, $messageId]);
        $editedAt = (string) db()->query('SELECT edited_at FROM private_messages WHERE id = ' . (int) $messageId)->fetchColumn();

        $payload = ['id' => $messageId, 'body' => $newBody, 'edited_at' => $editedAt, 'edited_time_label' => date('H:i', strtotime($editedAt))];
        broadcast('conversation:' . (int) $conversationId, 'message_edited', $payload);

        return ['id' => $messageId, 'body' => $newBody, 'edited_at' => $editedAt, 'conversation_id' => (int) $conversationId];
    }

    if ($scope === 'group') {
        $stmt = db()->prepare('SELECT group_id FROM group_messages WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$messageId]);
        $groupId = $stmt->fetchColumn();
        if ($groupId === false) {
            return null;
        }

        db()->prepare('UPDATE group_messages SET body = ?, edited_at = NOW() WHERE id = ?')->execute([$newBody, $messageId]);
        $editedAt = (string) db()->query('SELECT edited_at FROM group_messages WHERE id = ' . (int) $messageId)->fetchColumn();

        $payload = ['id' => $messageId, 'body' => $newBody, 'edited_at' => $editedAt, 'edited_time_label' => date('H:i', strtotime($editedAt))];
        broadcast('group:' . (int) $groupId, 'message_edited', $payload);

        return ['id' => $messageId, 'body' => $newBody, 'edited_at' => $editedAt, 'conversation_id' => (int) $groupId];
    }

    $stmt = db()->prepare('SELECT id FROM messages WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$messageId]);
    if ($stmt->fetchColumn() === false) {
        return null;
    }

    db()->prepare('UPDATE messages SET body = ?, edited_at = NOW() WHERE id = ?')->execute([$newBody, $messageId]);
    $editedAt = (string) db()->query('SELECT edited_at FROM messages WHERE id = ' . (int) $messageId)->fetchColumn();

    $payload = ['id' => $messageId, 'body' => $newBody, 'edited_at' => $editedAt, 'edited_time_label' => date('H:i', strtotime($editedAt))];
    broadcast('public', 'message_edited', $payload);

    return ['id' => $messageId, 'body' => $newBody, 'edited_at' => $editedAt, 'conversation_id' => null];
}

/** حذف نرم یک پیام برای همه‌ی طرفین + اطلاع‌رسانی آنی */
function delete_message_for_everyone(string $scope, int $messageId): bool
{
    if ($scope === 'private') {
        $stmt = db()->prepare('SELECT conversation_id FROM private_messages WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$messageId]);
        $conversationId = $stmt->fetchColumn();
        if ($conversationId === false) {
            return false;
        }

        db()->prepare('UPDATE private_messages SET deleted_at = NOW() WHERE id = ?')->execute([$messageId]);
        broadcast('conversation:' . (int) $conversationId, 'message_deleted', ['id' => $messageId]);
        return true;
    }

    if ($scope === 'group') {
        $stmt = db()->prepare('SELECT group_id FROM group_messages WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$messageId]);
        $groupId = $stmt->fetchColumn();
        if ($groupId === false) {
            return false;
        }

        db()->prepare('UPDATE group_messages SET deleted_at = NOW() WHERE id = ?')->execute([$messageId]);
        broadcast('group:' . (int) $groupId, 'message_deleted', ['id' => $messageId]);
        return true;
    }

    $stmt = db()->prepare('SELECT id FROM messages WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$messageId]);
    if ($stmt->fetchColumn() === false) {
        return false;
    }

    db()->prepare('UPDATE messages SET deleted_at = NOW() WHERE id = ?')->execute([$messageId]);
    broadcast('public', 'message_deleted', ['id' => $messageId]);
    return true;
}

/**
 * پین/آنپین یک پیام (مرحله ۱۰). فقط یک پیام همزمان در هر چت/گفتگو پین
 * می‌ماند - پین‌کردن پیام تازه، پین قبلی همان چت را خودکار برمی‌دارد
 * (دقیقاً مثل حالت ساده‌ی تلگرام برای یک پین فعال).
 * @return array{pinned: bool, message_id: int, conversation_id: ?int, pinned_message: ?array}|null
 */
function toggle_pin_message(string $scope, int $messageId): ?array
{
    if ($scope === 'private') {
        $stmt = db()->prepare('SELECT id, conversation_id, pinned_at FROM private_messages WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$messageId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $conversationId = (int) $row['conversation_id'];

        if ($row['pinned_at'] !== null) {
            db()->prepare('UPDATE private_messages SET pinned_at = NULL WHERE id = ?')->execute([$messageId]);
            broadcast('conversation:' . $conversationId, 'message_unpinned', ['id' => $messageId]);
            return ['pinned' => false, 'message_id' => $messageId, 'conversation_id' => $conversationId, 'pinned_message' => null];
        }

        db()->prepare('UPDATE private_messages SET pinned_at = NULL WHERE conversation_id = ? AND pinned_at IS NOT NULL')->execute([$conversationId]);
        db()->prepare('UPDATE private_messages SET pinned_at = NOW() WHERE id = ?')->execute([$messageId]);

        $pinnedMessage = get_pinned_message_payload('private', $conversationId);
        broadcast('conversation:' . $conversationId, 'message_pinned', $pinnedMessage ?? []);
        return ['pinned' => true, 'message_id' => $messageId, 'conversation_id' => $conversationId, 'pinned_message' => $pinnedMessage];
    }

    $stmt = db()->prepare('SELECT id, pinned_at FROM messages WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$messageId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    if ($row['pinned_at'] !== null) {
        db()->prepare('UPDATE messages SET pinned_at = NULL WHERE id = ?')->execute([$messageId]);
        broadcast('public', 'message_unpinned', ['id' => $messageId]);
        return ['pinned' => false, 'message_id' => $messageId, 'conversation_id' => null, 'pinned_message' => null];
    }

    db()->exec('UPDATE messages SET pinned_at = NULL WHERE pinned_at IS NOT NULL');
    db()->prepare('UPDATE messages SET pinned_at = NOW() WHERE id = ?')->execute([$messageId]);

    $pinnedMessage = get_pinned_message_payload('public', null);
    broadcast('public', 'message_pinned', $pinnedMessage ?? []);
    return ['pinned' => true, 'message_id' => $messageId, 'conversation_id' => null, 'pinned_message' => $pinnedMessage];
}

/**
 * اطلاعات پیام پین‌شده‌ی فعلی یک چت عمومی/گفتگوی خصوصی، برای نمایش در نوار
 * بالای پیام‌ها. هم برای رندر اولیه‌ی PHP و هم پاسخ اندپوینت‌های fetch/toggle
 * استفاده می‌شود تا کلاینت‌های فقط-Polling هم بدون WebSocket همگام بمانند.
 * @return array{id:int, author:string, snippet:string}|null
 */
function get_pinned_message_payload(string $scope, ?int $conversationId): ?array
{
    if ($scope === 'private') {
        if ($conversationId === null) {
            return null;
        }
        $stmt = db()->prepare(
            'SELECT pm.id, pm.body, pm.attachment_type, u.username, u.display_name
             FROM private_messages pm JOIN users u ON u.id = pm.sender_id
             WHERE pm.conversation_id = ? AND pm.pinned_at IS NOT NULL AND pm.deleted_at IS NULL
             ORDER BY pm.pinned_at DESC LIMIT 1'
        );
        $stmt->execute([$conversationId]);
    } else {
        $stmt = db()->query(
            'SELECT m.id, m.body, m.attachment_type, u.username, u.display_name
             FROM messages m JOIN users u ON u.id = m.user_id
             WHERE m.pinned_at IS NOT NULL AND m.deleted_at IS NULL
             ORDER BY m.pinned_at DESC LIMIT 1'
        );
    }
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    $author = display_name_of(['display_name' => $row['display_name'], 'username' => $row['username']]);
    $snippet = trim((string) $row['body']);
    if ($snippet === '') {
        $snippet = match ($row['attachment_type']) {
            'image' => '📷 عکس',
            'video' => '🎬 ویدیو',
            'voice' => '🎤 پیام صوتی',
            default => '',
        };
    }
    $snippet = mb_strlen($snippet) > 60 ? mb_substr($snippet, 0, 60) . '…' : $snippet;

    return ['id' => (int) $row['id'], 'author' => $author, 'snippet' => $snippet];
}

/**
 * حذف نرم تمام پیام‌های چت عمومی (اکشن مدیر) - مرحله ۷. با یک UPDATE و یک
 * broadcast تکی انجام می‌شود (نه یک‌به‌یک) تا برای هزاران پیام هم سریع
 * بماند. کلاینت‌های فقط-Polling هم از طریق همان مکانیزم deleted_ids موجود
 * در fetch_messages.php (که هر پیام تازه‌حذف‌شده را در ۳۰ ثانیه‌ی اول
 * برمی‌گرداند) به‌طور خودکار به‌روزرسانی می‌شوند.
 */
function delete_all_public_messages(): int
{
    $affected = db()->exec('UPDATE messages SET deleted_at = NOW() WHERE deleted_at IS NULL');
    broadcast('public', 'messages_cleared', []);
    return (int) $affected;
}

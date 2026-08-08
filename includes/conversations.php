<?php
/**
 * توابع کمکی برای چت خصوصی بین دو نفر
 */

declare(strict_types=1);

/** پیدا کردن یا ساختن گفتگوی خصوصی بین دو کاربر. شناسه‌ها همیشه به ترتیب کوچک/بزرگ ذخیره می‌شوند. */
function find_or_create_conversation(int $userIdA, int $userIdB): ?int
{
    if ($userIdA === $userIdB) {
        return null;
    }

    $a = min($userIdA, $userIdB);
    $b = max($userIdA, $userIdB);

    $stmt = db()->prepare('SELECT id FROM conversations WHERE user_a_id = ? AND user_b_id = ?');
    $stmt->execute([$a, $b]);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        return (int) $id;
    }

    $stmt = db()->prepare('INSERT INTO conversations (user_a_id, user_b_id) VALUES (?, ?)');
    $stmt->execute([$a, $b]);

    return (int) db()->lastInsertId();
}

/** فقط پیدا کردن گفتگوی موجود بین دو کاربر (بدون ساختن گفتگوی جدید) */
function find_conversation_id(int $userIdA, int $userIdB): ?int
{
    if ($userIdA === $userIdB) {
        return null;
    }

    $a = min($userIdA, $userIdB);
    $b = max($userIdA, $userIdB);

    $stmt = db()->prepare('SELECT id FROM conversations WHERE user_a_id = ? AND user_b_id = ?');
    $stmt->execute([$a, $b]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/** بررسی اینکه آیا کاربر عضو این گفتگوست */
function user_in_conversation(int $conversationId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM conversations WHERE id = ? AND (user_a_id = ? OR user_b_id = ?)');
    $stmt->execute([$conversationId, $userId, $userId]);
    return (bool) $stmt->fetchColumn();
}

/** گرفتن اطلاعات طرف مقابل در یک گفتگوی خصوصی */
function conversation_partner(int $conversationId, int $myUserId): ?array
{
    $stmt = db()->prepare(
        'SELECT u.id, u.username, u.display_name, u.avatar_color, u.avatar_path, u.bio, u.is_blocked, u.last_seen
         FROM conversations c
         JOIN users u ON u.id = IF(c.user_a_id = ?, c.user_b_id, c.user_a_id)
         WHERE c.id = ?'
    );
    $stmt->execute([$myUserId, $conversationId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * لیست گفتگوهای خصوصی کاربر به همراه آخرین پیام، مرتب‌شده بر اساس جدیدترین فعالیت.
 * گفتگوهایی که کاربر «حذف» کرده (user_a_hidden_at/user_b_hidden_at) در این لیست
 * نمی‌آیند، مگر اینکه بعد از زمان حذف پیام جدیدی رسیده باشد - رفتار مشابه تلگرام.
 */
function list_conversations_for_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT
            c.id AS conversation_id,
            u.id AS partner_id,
            u.username AS partner_username,
            u.display_name AS partner_display_name,
            u.avatar_color AS partner_color,
            u.avatar_path AS partner_avatar_path,
            u.last_seen AS partner_last_seen,
            lm.body AS last_body,
            lm.created_at AS last_at,
            IF(c.user_a_id = ?, c.user_a_hidden_at, c.user_b_hidden_at) AS my_hidden_at,
            IF(c.user_a_id = ?, c.user_a_blocked, c.user_b_blocked) AS i_blocked,
            IF(c.user_a_id = ?, c.user_a_muted, c.user_b_muted) AS i_muted,
            IF(c.user_a_id = ?, c.user_a_pinned_at, c.user_b_pinned_at) AS my_pinned_at
         FROM conversations c
         JOIN users u ON u.id = IF(c.user_a_id = ?, c.user_b_id, c.user_a_id)
         LEFT JOIN private_messages lm ON lm.id = (
             SELECT pm.id FROM private_messages pm
             WHERE pm.conversation_id = c.id
             ORDER BY pm.id DESC LIMIT 1
         )
         WHERE (c.user_a_id = ? OR c.user_b_id = ?)
           AND (
               IF(c.user_a_id = ?, c.user_a_hidden_at, c.user_b_hidden_at) IS NULL
               OR lm.created_at > IF(c.user_a_id = ?, c.user_a_hidden_at, c.user_b_hidden_at)
           )
         ORDER BY
             (IF(c.user_a_id = ?, c.user_a_pinned_at, c.user_b_pinned_at) IS NULL) ASC,
             IF(c.user_a_id = ?, c.user_a_pinned_at, c.user_b_pinned_at) DESC,
             COALESCE(lm.created_at, c.created_at) DESC'
    );
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    return $stmt->fetchAll();
}

/** علامت‌گذاری پیام‌های طرف مقابل به‌عنوان دیده‌شده، وقتی من گفتگو را باز می‌کنم */
function mark_conversation_seen(int $conversationId, int $myUserId): void
{
    $stmt = db()->prepare(
        'UPDATE private_messages SET seen_at = NOW()
         WHERE conversation_id = ? AND sender_id != ? AND seen_at IS NULL'
    );
    $stmt->execute([$conversationId, $myUserId]);
}

/** بالاترین id پیامی از خودم که طرف مقابل دیده است (برای نمایش تیک آبی) */
function last_seen_message_id(int $conversationId, int $myUserId): int
{
    $stmt = db()->prepare(
        'SELECT MAX(id) FROM private_messages WHERE conversation_id = ? AND sender_id = ? AND seen_at IS NOT NULL'
    );
    $stmt->execute([$conversationId, $myUserId]);
    $id = $stmt->fetchColumn();
    return $id !== false && $id !== null ? (int) $id : 0;
}

/** تعداد پیام خوانده‌نشده‌ی چت عمومی برای یک کاربر (پیام‌های خودش حساب نمی‌شود) */
function count_unread_public(int $userId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM messages m
         WHERE m.deleted_at IS NULL AND m.user_id != ?
           AND m.id > (SELECT last_read_public_message_id FROM users WHERE id = ?)
           AND NOT EXISTS (SELECT 1 FROM message_hides mh WHERE mh.message_id = m.id AND mh.user_id = ?)'
    );
    $stmt->execute([$userId, $userId, $userId]);
    return (int) $stmt->fetchColumn();
}

/** علامت‌گذاری چت عمومی به‌عنوان خوانده‌شده تا یک شناسه‌ی مشخص */
function mark_public_read(int $userId, int $lastMessageId): void
{
    if ($lastMessageId <= 0) {
        return;
    }
    $stmt = db()->prepare('UPDATE users SET last_read_public_message_id = GREATEST(last_read_public_message_id, ?) WHERE id = ?');
    $stmt->execute([$lastMessageId, $userId]);
}

/** تعداد پیام خوانده‌نشده‌ی یک گفتگوی خصوصی (بر اساس seen_at موجود) */
function count_unread_private(int $conversationId, int $userId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM private_messages
         WHERE conversation_id = ? AND sender_id != ? AND seen_at IS NULL AND deleted_at IS NULL'
    );
    $stmt->execute([$conversationId, $userId]);
    return (int) $stmt->fetchColumn();
}

/** خواندن رکورد خام گفتگو (برای تشخیص a/b و وضعیت بلاک) */
function get_conversation_row(int $conversationId): ?array
{
    $stmt = db()->prepare('SELECT * FROM conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * وضعیت بلاک یک گفتگو نسبت به کاربر جاری.
 * @return array{i_blocked: bool, blocked_by_partner: bool}|null
 */
function conversation_block_state(int $conversationId, int $myUserId): ?array
{
    $conv = get_conversation_row($conversationId);
    if ($conv === null) {
        return null;
    }

    $iAmA = (int) $conv['user_a_id'] === $myUserId;

    return [
        'i_blocked'          => (bool) ($iAmA ? $conv['user_a_blocked'] : $conv['user_b_blocked']),
        'blocked_by_partner' => (bool) ($iAmA ? $conv['user_b_blocked'] : $conv['user_a_blocked']),
    ];
}

/** آیا ارسال پیام در این گفتگو مجاز است (هیچ‌کدام از دو طرف بلاک نکرده باشند) */
function conversation_send_allowed(int $conversationId): bool
{
    $conv = get_conversation_row($conversationId);
    if ($conv === null) {
        return false;
    }
    return (int) $conv['user_a_blocked'] === 0 && (int) $conv['user_b_blocked'] === 0;
}

/** حذف مکالمه فقط از سایدبار کاربر جاری (طرف مقابل چیزی متوجه نمی‌شود؛ با پیام جدید دوباره ظاهر می‌شود) */
function hide_conversation_for_me(int $conversationId, int $myUserId): bool
{
    $conv = get_conversation_row($conversationId);
    if ($conv === null) {
        return false;
    }
    $iAmA = (int) $conv['user_a_id'] === $myUserId;
    $column = $iAmA ? 'user_a_hidden_at' : 'user_b_hidden_at';
    $stmt = db()->prepare("UPDATE conversations SET {$column} = NOW() WHERE id = ?");
    $stmt->execute([$conversationId]);
    return true;
}

/**
 * حذف کامل و غیرقابل‌بازگشت یک مکالمه: تمام پیام‌ها و فایل‌های پیوست آن برای
 * هر دو طرف پاک می‌شود (مشابه حذف حساب کاربری، اما محدود به یک مکالمه).
 * تعداد پیام‌های حذف‌شده را برمی‌گرداند.
 */
function delete_conversation_completely(int $conversationId, int $myUserId): int
{
    $stmt = db()->prepare('SELECT attachment_path FROM private_messages WHERE conversation_id = ? AND attachment_path IS NOT NULL');
    $stmt->execute([$conversationId]);
    $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = db()->prepare('DELETE FROM private_messages WHERE conversation_id = ?');
    $stmt->execute([$conversationId]);
    $count = $stmt->rowCount();

    foreach ($paths as $path) {
        if (!$path) {
            continue;
        }
        $absolute = ROOT_PATH . '/' . ltrim((string) $path, '/');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    // بعد از حذف کامل، مکالمه از سایدبار من هم مخفی شود تا خالی نمایش داده نشود
    hide_conversation_for_me($conversationId, $myUserId);

    broadcast('conversation:' . $conversationId, 'conversation_cleared', [
        'conversation_id' => $conversationId,
        'by_user_id'      => $myUserId,
    ]);

    return $count;
}

/** تغییر وضعیت بلاک گفتگو توسط کاربر جاری. مقدار جدید (بلاک‌شده یا نه) را برمی‌گرداند. */
function toggle_conversation_block(int $conversationId, int $myUserId): ?bool
{
    $conv = get_conversation_row($conversationId);
    if ($conv === null) {
        return null;
    }

    $iAmA = (int) $conv['user_a_id'] === $myUserId;
    $column = $iAmA ? 'user_a_blocked' : 'user_b_blocked';
    $newValue = $iAmA ? 1 - (int) $conv['user_a_blocked'] : 1 - (int) $conv['user_b_blocked'];

    $stmt = db()->prepare("UPDATE conversations SET {$column} = ? WHERE id = ?");
    $stmt->execute([$newValue, $conversationId]);

    return (bool) $newValue;
}

/** تغییر وضعیت بی‌صدا (mute) مکالمه توسط کاربر جاری. مقدار جدید را برمی‌گرداند. */
function toggle_conversation_mute(int $conversationId, int $myUserId): ?bool
{
    $conv = get_conversation_row($conversationId);
    if ($conv === null) {
        return null;
    }

    $iAmA = (int) $conv['user_a_id'] === $myUserId;
    $column = $iAmA ? 'user_a_muted' : 'user_b_muted';
    $newValue = $iAmA ? 1 - (int) $conv['user_a_muted'] : 1 - (int) $conv['user_b_muted'];

    $stmt = db()->prepare("UPDATE conversations SET {$column} = ? WHERE id = ?");
    $stmt->execute([$newValue, $conversationId]);

    return (bool) $newValue;
}

/** پین/آن‌پین کردن مکالمه توسط کاربر جاری (فقط برای خودش). مقدار جدید (پین‌شده یا نه) را برمی‌گرداند. */
function toggle_conversation_pin(int $conversationId, int $myUserId): ?bool
{
    $conv = get_conversation_row($conversationId);
    if ($conv === null) {
        return null;
    }

    $iAmA = (int) $conv['user_a_id'] === $myUserId;
    $column = $iAmA ? 'user_a_pinned_at' : 'user_b_pinned_at';
    $currentlyPinned = $iAmA ? $conv['user_a_pinned_at'] !== null : $conv['user_b_pinned_at'] !== null;
    $newValue = $currentlyPinned ? null : date('Y-m-d H:i:s');

    $stmt = db()->prepare("UPDATE conversations SET {$column} = ? WHERE id = ?");
    $stmt->execute([$newValue, $conversationId]);

    return !$currentlyPinned;
}

/**
 * رندر HTML یک آیتم مکالمه در سایدبار. هم برای رندر اولیه‌ی صفحه (سمت PHP)
 * و هم برای به‌روزرسانی زنده‌ی AJAX (api/unread_counts.php) استفاده می‌شود
 * تا دو مسیر همیشه ساختار یکسانی تولید کنند.
 * @param array $conv سطری از list_conversations_for_user() (باید partner_* و last_body/last_at را داشته باشد)
 */
function conv_item_html(array $conv, int $unread, bool $isActive): string
{
    $convId = (int) $conv['conversation_id'];
    $online = is_user_online($conv['partner_last_seen'] ?? null);

    $avatar = avatar_html([
        'id' => $conv['partner_id'],
        'username' => $conv['partner_username'],
        'avatar_color' => $conv['partner_color'],
        'avatar_path' => $conv['partner_avatar_path'] ?? null,
    ], '', true, $online);

    $name = e((string) ($conv['partner_display_name'] ?: $conv['partner_username']));
    $preview = e($conv['last_body'] !== null ? mb_substr((string) $conv['last_body'], 0, 30) : 'گفتگو را شروع کنید');
    $badgeText = $unread > 99 ? '99+' : (string) $unread;

    $iBlocked = !empty($conv['i_blocked']);
    $iMuted = !empty($conv['i_muted']);
    $isPinned = !empty($conv['my_pinned_at']);

    $nameRow = '<span class="conv-name-row">'
        . ($isPinned ? '<span class="conv-pin-icon">' . icon_svg('pin') . '</span>' : '')
        . '<span class="conv-name">' . $name . '</span>'
        . ($iMuted ? '<span class="conv-mute-icon">' . icon_svg('bell-off') . '</span>' : '')
        . '</span>';

    $badgeHtml = ($unread > 0 && $iMuted)
        ? '<span class="unread-badge unread-dot" data-badge="conv-' . $convId . '"></span>'
        : '<span class="unread-badge ' . ($unread > 0 ? '' : 'hidden') . '" data-badge="conv-' . $convId . '">' . $badgeText . '</span>';

    return '<a href="' . e(url('messages.php?id=' . $convId)) . '" class="conv-item' . ($isActive ? ' active' : '') . '"'
        . ' data-conversation-id="' . $convId . '" data-partner-id="' . (int) $conv['partner_id'] . '"'
        . ' data-partner-name="' . e($name) . '" data-blocked="' . ($iBlocked ? '1' : '0') . '"'
        . ' data-muted="' . ($iMuted ? '1' : '0') . '" data-pinned="' . ($isPinned ? '1' : '0') . '">'
        . '<span class="conv-select-check">' . icon_svg('check') . '</span>'
        . $avatar
        . '<span class="conv-info">' . $nameRow
        . '<span class="conv-preview">' . $preview . '</span></span>'
        . $badgeHtml
        . '</a>';
}

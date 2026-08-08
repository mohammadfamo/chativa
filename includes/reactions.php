<?php
/**
 * ری‌اکشن ایموجی به پیام - مرحله ۱۰
 * -------------------------------------------------
 * هر کاربر برای هر پیام حداکثر یک ری‌اکشن فعال دارد (جدول message_reactions
 * با UNIQUE(scope, message_id, user_id)). کلیک روی همان ایموجی = حذف
 * ری‌اکشن، کلیک روی ایموجی دیگر = جایگزینی. مجموعه‌ی ایموجی‌های مجاز محدود
 * و ثابت است (سرور همیشه اعتبارسنجی می‌کند، حتی اگر کلاینت چیز دیگری بفرستد).
 */

declare(strict_types=1);

/** @return string[] */
function allowed_reaction_emojis(): array
{
    return ['👍', '❤️', '😂', '😮', '😢', '🙏'];
}

/**
 * افزودن/حذف/جایگزینی ری‌اکشن کاربر روی یک پیام.
 * @return array{message_id:int, reactions: array<int, array{emoji:string, count:int, mine:bool}>}|null
 */
function toggle_reaction(string $scope, int $messageId, int $userId, string $emoji): ?array
{
    if (!in_array($emoji, allowed_reaction_emojis(), true)) {
        return null;
    }

    $stmt = db()->prepare('SELECT emoji FROM message_reactions WHERE scope = ? AND message_id = ? AND user_id = ?');
    $stmt->execute([$scope, $messageId, $userId]);
    $current = $stmt->fetchColumn();

    if ($current === $emoji) {
        // همان ایموجی دوباره کلیک شده → حذف ری‌اکشن
        db()->prepare('DELETE FROM message_reactions WHERE scope = ? AND message_id = ? AND user_id = ?')
            ->execute([$scope, $messageId, $userId]);
    } elseif ($current !== false) {
        // ایموجی دیگری از قبل ثبت بود → جایگزینی
        db()->prepare('UPDATE message_reactions SET emoji = ?, created_at = NOW() WHERE scope = ? AND message_id = ? AND user_id = ?')
            ->execute([$emoji, $scope, $messageId, $userId]);
    } else {
        db()->prepare('INSERT INTO message_reactions (scope, message_id, user_id, emoji) VALUES (?, ?, ?, ?)')
            ->execute([$scope, $messageId, $userId, $emoji]);
    }

    $reactions = get_reactions_for_message($scope, $messageId, $userId);
    $payload = ['message_id' => $messageId, 'reactions' => $reactions];

    if ($scope === 'private') {
        $convStmt = db()->prepare('SELECT conversation_id FROM private_messages WHERE id = ?');
        $convStmt->execute([$messageId]);
        $conversationId = $convStmt->fetchColumn();
        if ($conversationId !== false) {
            broadcast('conversation:' . (int) $conversationId, 'reaction_updated', $payload);
        }
    } else {
        broadcast('public', 'reaction_updated', $payload);
    }

    return $payload;
}

/**
 * ری‌اکشن‌های تجمیع‌شده‌ی یک پیام (برای پاسخ فوری toggle).
 * @return array<int, array{emoji:string, count:int, mine:bool}>
 */
function get_reactions_for_message(string $scope, int $messageId, int $myUserId): array
{
    $stmt = db()->prepare(
        'SELECT emoji, COUNT(*) AS c, SUM(user_id = ?) AS mine
         FROM message_reactions WHERE scope = ? AND message_id = ?
         GROUP BY emoji ORDER BY MIN(created_at) ASC'
    );
    $stmt->execute([$myUserId, $scope, $messageId]);

    return array_map(static function (array $row): array {
        return ['emoji' => $row['emoji'], 'count' => (int) $row['c'], 'mine' => ((int) $row['mine']) > 0];
    }, $stmt->fetchAll());
}

/**
 * ری‌اکشن‌های تجمیع‌شده‌ی چند پیام همزمان (برای رندر اولیه‌ی PHP صفحه‌ی چت
 * - یک کوئری برای کل صفحه، نه یک‌به‌یک).
 * @param int[] $messageIds
 * @return array<int, array<int, array{emoji:string, count:int, mine:bool}>> کلید = message_id
 */
function get_reactions_for_messages(string $scope, array $messageIds, int $myUserId): array
{
    if ($messageIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $stmt = db()->prepare(
        "SELECT message_id, emoji, COUNT(*) AS c, SUM(user_id = ?) AS mine
         FROM message_reactions WHERE scope = ? AND message_id IN ({$placeholders})
         GROUP BY message_id, emoji ORDER BY MIN(created_at) ASC"
    );
    $stmt->execute(array_merge([$myUserId, $scope], $messageIds));

    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $mid = (int) $row['message_id'];
        $grouped[$mid][] = ['emoji' => $row['emoji'], 'count' => (int) $row['c'], 'mine' => ((int) $row['mine']) > 0];
    }

    return $grouped;
}

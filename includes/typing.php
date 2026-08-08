<?php
/**
 * نشانگر «در حال تایپ...» برای چت عمومی و خصوصی
 *
 * توجه: conversation_id هرگز NULL ذخیره نمی‌شود (برای چت عمومی مقدار 0 است)،
 * چون کلید یکتای MySQL مقادیر NULL را از هم متمایز می‌داند و UPSERT با
 * ON DUPLICATE KEY روی NULL کار نمی‌کند.
 */

declare(strict_types=1);

const TYPING_TTL_SECONDS = 4;

/** ثبت اینکه کاربر جاری در حال تایپ است. $conversationId را برای چت عمومی null بگذارید. */
function ping_typing(string $scope, ?int $conversationId, int $userId): void
{
    $convValue = $conversationId ?? 0;

    $stmt = db()->prepare(
        'INSERT INTO typing_status (scope, conversation_id, user_id, updated_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE updated_at = NOW()'
    );
    $stmt->execute([$scope, $convValue, $userId]);
}

/** لیست نام‌کاربری‌هایی که همین الان (چند ثانیه‌ی اخیر) در حال تایپ‌اند، به‌جز خودم */
function get_typing_usernames(string $scope, ?int $conversationId, int $excludeUserId): array
{
    $convValue = $conversationId ?? 0;

    $stmt = db()->prepare(
        'SELECT u.username FROM typing_status t
         JOIN users u ON u.id = t.user_id
         WHERE t.scope = ? AND t.conversation_id = ? AND t.user_id != ?
           AND t.updated_at >= DATE_SUB(NOW(), INTERVAL ' . TYPING_TTL_SECONDS . ' SECOND)'
    );
    $stmt->execute([$scope, $convValue, $excludeUserId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

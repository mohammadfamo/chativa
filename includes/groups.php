<?php
/**
 * چت گروهی/کانال - مرحله ۱۰
 * -------------------------------------------------
 * گروه‌ها یک نوع سوم چت هستند (در کنار عمومی/خصوصی). نقش‌ها: owner (سازنده،
 * فقط یکی، حذف/تغییر مالکیت گروه دستش است)، admin (می‌تواند عضو اضافه/حذف
 * کند و پیام دیگران را حذف کند) و member (فقط پیام می‌فرستد). لینک دعوت
 * (invite_code) به هر کسی که آن را دارد اجازه‌ی عضویت می‌دهد.
 *
 * v1 این قابلیت پین/ری‌اکشن/جستجو/میوت را برای گروه پیاده نمی‌کند (فقط
 * ستون group_members.muted برای توسعه‌ی بعدی از الان در دیتابیس هست)؛
 * پیام‌دهی، ریپلای، ویرایش، حذف و پیوست‌ها کامل کار می‌کنند.
 */

declare(strict_types=1);

/** کد دعوت تصادفی و یکتا برای یک گروه */
function generate_group_invite_code(): string
{
    do {
        $code = substr(bin2hex(random_bytes(6)), 0, 10);
        $stmt = db()->prepare('SELECT 1 FROM `groups` WHERE invite_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() !== false);

    return $code;
}

/** ساخت گروه جدید؛ سازنده به‌عنوان owner عضو می‌شود. @return int شناسه‌ی گروه تازه */
function create_group(string $name, ?string $description, int $ownerId): int
{
    $inviteCode = generate_group_invite_code();
    $stmt = db()->prepare('INSERT INTO `groups` (name, description, owner_id, invite_code) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $description !== '' ? $description : null, $ownerId, $inviteCode]);
    $groupId = (int) db()->lastInsertId();

    db()->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)')
        ->execute([$groupId, $ownerId, 'owner']);

    return $groupId;
}

function get_group_row(int $groupId): ?array
{
    $stmt = db()->prepare('SELECT * FROM `groups` WHERE id = ?');
    $stmt->execute([$groupId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function is_group_member(int $groupId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    return $stmt->fetchColumn() !== false;
}

/** @return 'owner'|'admin'|'member'|null */
function group_member_role(int $groupId, int $userId): ?string
{
    $stmt = db()->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $role = $stmt->fetchColumn();
    return $role !== false ? (string) $role : null;
}

/** owner یا admin - برای اجازه‌ی مدیریت اعضا/حذف پیام دیگران/ویرایش پروفایل گروه */
function is_group_admin(int $groupId, int $userId): bool
{
    return in_array(group_member_role($groupId, $userId), ['owner', 'admin'], true);
}

function group_member_count(int $groupId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
    $stmt->execute([$groupId]);
    return (int) $stmt->fetchColumn();
}

/** لیست اعضای گروه به‌همراه اطلاعات کاربری، مرتب‌شده owner/admin اول */
function list_group_members(int $groupId): array
{
    $stmt = db()->prepare(
        "SELECT u.id, u.username, u.display_name, u.avatar_color, u.avatar_path, u.last_seen, gm.role, gm.joined_at
         FROM group_members gm JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = ?
         ORDER BY FIELD(gm.role, 'owner', 'admin', 'member'), u.username ASC"
    );
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

/**
 * لیست گروه‌های یک کاربر برای سایدبار، به‌همراه پیش‌نمایش آخرین پیام و
 * تعداد نخوانده - دقیقاً هم‌ساختار با list_conversations_for_user().
 */
function list_groups_for_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT g.id AS group_id, g.name, g.description, g.avatar_path, g.avatar_color,
                gm.last_read_message_id, gm.muted AS i_muted,
                lm.body AS last_body, lm.created_at AS last_at,
                (SELECT COUNT(*) FROM group_messages gm2
                 WHERE gm2.group_id = g.id AND gm2.deleted_at IS NULL AND gm2.id > gm.last_read_message_id
                   AND NOT EXISTS (SELECT 1 FROM group_message_hides gmh WHERE gmh.message_id = gm2.id AND gmh.user_id = ?)
                ) AS unread
         FROM group_members gm
         JOIN `groups` g ON g.id = gm.group_id
         LEFT JOIN group_messages lm ON lm.id = (
             SELECT gm3.id FROM group_messages gm3
             WHERE gm3.group_id = g.id AND gm3.deleted_at IS NULL
             ORDER BY gm3.id DESC LIMIT 1
         )
         WHERE gm.user_id = ?
         ORDER BY COALESCE(lm.created_at, g.created_at) DESC'
    );
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}

function add_group_member(int $groupId, int $userId, string $role = 'member'): bool
{
    if (is_group_member($groupId, $userId)) {
        return false;
    }
    db()->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)')
        ->execute([$groupId, $userId, $role]);
    return true;
}

function remove_group_member(int $groupId, int $userId): void
{
    db()->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$groupId, $userId]);
}

function update_group_member_role(int $groupId, int $userId, string $role): void
{
    if (!in_array($role, ['admin', 'member'], true)) {
        return; // مالکیت (owner) فقط با انتقال مالکیت صریح تغییر می‌کند، نه از این تابع
    }
    db()->prepare('UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?')
        ->execute([$role, $groupId, $userId]);
}

/**
 * خروج یک عضو از گروه. اگر owner باشد و اعضای دیگری مانده باشند، مالکیت
 * به قدیمی‌ترین admin (یا در نبود admin، قدیمی‌ترین عضو) منتقل می‌شود؛
 * اگر عضو آخر باشد، کل گروه حذف می‌شود.
 */
function leave_group(int $groupId, int $userId): void
{
    $role = group_member_role($groupId, $userId);
    if ($role === null) {
        return;
    }

    remove_group_member($groupId, $userId);

    if ($role !== 'owner') {
        return;
    }

    $remaining = group_member_count($groupId);
    if ($remaining === 0) {
        delete_group($groupId);
        return;
    }

    $stmt = db()->prepare(
        "SELECT user_id FROM group_members WHERE group_id = ?
         ORDER BY FIELD(role, 'admin', 'member'), joined_at ASC LIMIT 1"
    );
    $stmt->execute([$groupId]);
    $nextOwnerId = $stmt->fetchColumn();
    if ($nextOwnerId !== false) {
        db()->prepare('UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?')
            ->execute(['owner', $groupId, $nextOwnerId]);
        db()->prepare('UPDATE `groups` SET owner_id = ? WHERE id = ?')->execute([$nextOwnerId, $groupId]);
    }
}

/** حذف کامل گروه (فقط owner) - پیام‌ها/اعضا با ON DELETE CASCADE خودکار پاک می‌شوند */
function delete_group(int $groupId): void
{
    $group = get_group_row($groupId);
    if ($group !== null && !empty($group['avatar_path'])) {
        $absolute = ROOT_PATH . '/' . ltrim((string) $group['avatar_path'], '/');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
    db()->prepare('DELETE FROM `groups` WHERE id = ?')->execute([$groupId]);
    broadcast('group:' . $groupId, 'group_deleted', []);
}

/**
 * عضویت در گروه از طریق لینک دعوت. اگر کاربر از قبل عضو باشد فقط رکورد
 * گروه را برمی‌گرداند (خطا نیست).
 * @return array|null آرایه‌ی گروه، یا null اگر کد دعوت نامعتبر باشد
 */
function join_group_by_invite_code(string $code, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM `groups` WHERE invite_code = ?');
    $stmt->execute([$code]);
    $group = $stmt->fetch();
    if ($group === false) {
        return null;
    }

    add_group_member((int) $group['id'], $userId, 'member');
    return $group;
}

/** علامت‌گذاری پیام‌های گروه به‌عنوان خوانده‌شده تا یک شناسه‌ی مشخص */
function mark_group_seen(int $groupId, int $userId, int $lastMessageId): void
{
    if ($lastMessageId <= 0) {
        return;
    }
    db()->prepare('UPDATE group_members SET last_read_message_id = GREATEST(last_read_message_id, ?) WHERE group_id = ? AND user_id = ?')
        ->execute([$lastMessageId, $groupId, $userId]);
}

/** به‌روزرسانی نام/توضیح/آواتار گروه (فقط owner/admin) */
function update_group_profile(int $groupId, string $name, ?string $description, ?string $avatarPath, bool $avatarChanged): void
{
    $sql = 'UPDATE `groups` SET name = ?, description = ?';
    $params = [$name, $description !== '' ? $description : null];
    if ($avatarChanged) {
        $sql .= ', avatar_path = ?';
        $params[] = $avatarPath;
    }
    $sql .= ' WHERE id = ?';
    $params[] = $groupId;

    db()->prepare($sql)->execute($params);
}

/** تولید کد دعوت تازه برای گروه (لینک قبلی از کار می‌افتد) - فقط owner/admin */
function regenerate_group_invite(int $groupId): string
{
    $code = generate_group_invite_code();
    db()->prepare('UPDATE `groups` SET invite_code = ? WHERE id = ?')->execute([$code, $groupId]);
    return $code;
}

/** رندر یک آیتم گروه در سایدبار - هم‌ساختار با conv_item_html() */
function group_item_html(array $group, bool $isActive): string
{
    $groupId = (int) $group['group_id'];
    $unread = (int) ($group['unread'] ?? 0);
    $iMuted = !empty($group['i_muted']);

    $avatar = avatar_html([
        'username'     => $group['name'],
        'avatar_color' => $group['avatar_color'],
        'avatar_path'  => $group['avatar_path'] ?? null,
    ], '', false);

    $name = e((string) $group['name']);
    $preview = e($group['last_body'] !== null ? mb_substr((string) $group['last_body'], 0, 30) : 'هنوز پیامی ارسال نشده');
    $badgeText = $unread > 99 ? '99+' : (string) $unread;

    $nameRow = '<span class="conv-name-row">'
        . '<span class="conv-name">' . $name . '</span>'
        . ($iMuted ? '<span class="conv-mute-icon">' . icon_svg('bell-off') . '</span>' : '')
        . '</span>';

    $badgeHtml = ($unread > 0 && $iMuted)
        ? '<span class="unread-badge unread-dot" data-badge="group-' . $groupId . '"></span>'
        : '<span class="unread-badge ' . ($unread > 0 ? '' : 'hidden') . '" data-badge="group-' . $groupId . '">' . $badgeText . '</span>';

    return '<a href="' . e(url('group.php?id=' . $groupId)) . '" class="conv-item' . ($isActive ? ' active' : '') . '"'
        . ' data-group-id="' . $groupId . '">'
        . $avatar
        . '<span class="conv-info">' . $nameRow
        . '<span class="conv-preview">' . $preview . '</span></span>'
        . $badgeHtml
        . '</a>';
}

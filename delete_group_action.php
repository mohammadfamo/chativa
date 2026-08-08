<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();
require_post_csrf();

$me = current_user();
$groupId = (int) ($_POST['group_id'] ?? 0);
$group = $groupId > 0 ? get_group_row($groupId) : null;

if ($group === null || (int) $group['owner_id'] !== (int) $me['id']) {
    set_flash('error', 'گروه مورد نظر پیدا نشد.');
    redirect('chat.php');
}

delete_group($groupId);

set_flash('success', 'گروه برای همیشه حذف شد.');
redirect('chat.php');

<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();
require_post_csrf();

$me = current_user();
$groupId = (int) ($_POST['group_id'] ?? 0);

if ($groupId <= 0 || !is_group_member($groupId, (int) $me['id'])) {
    set_flash('error', 'گروه مورد نظر پیدا نشد.');
    redirect('chat.php');
}

leave_group($groupId, (int) $me['id']);
broadcast('group:' . $groupId, 'member_removed', ['user_id' => (int) $me['id']]);

set_flash('success', 'از گروه خارج شدی.');
redirect('chat.php');

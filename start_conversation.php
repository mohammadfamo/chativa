<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();
require_post_csrf();

$me = current_user();
$targetId = (int) ($_POST['user_id'] ?? 0);

if ($targetId <= 0 || $targetId === (int) $me['id']) {
    set_flash('error', 'کاربر نامعتبر است.');
    redirect('new_chat.php');
}

$stmt = db()->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$targetId]);
if (!$stmt->fetchColumn()) {
    set_flash('error', 'کاربر مورد نظر پیدا نشد.');
    redirect('new_chat.php');
}

$conversationId = find_or_create_conversation((int) $me['id'], $targetId);

redirect('messages.php?id=' . $conversationId);

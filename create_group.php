<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();
require_post_csrf();

$me = current_user();
$name = trim((string) ($_POST['name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));

if ($name === '') {
    set_flash('error', 'نام گروه را وارد کن.');
    redirect('new_group.php');
}
if (mb_strlen($name) > 100) {
    $name = mb_substr($name, 0, 100);
}
if (mb_strlen($description) > 300) {
    $description = mb_substr($description, 0, 300);
}

$groupId = create_group($name, $description, (int) $me['id']);

redirect('group.php?id=' . $groupId);

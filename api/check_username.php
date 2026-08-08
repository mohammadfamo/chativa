<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

$username = trim((string) ($_GET['username'] ?? ''));

if ($username === '') {
    echo json_encode(['available' => false, 'reason' => 'empty']);
    exit;
}

if (!preg_match(USERNAME_REGEX, $username)) {
    echo json_encode(['available' => false, 'reason' => 'invalid_format']);
    exit;
}

if (is_username_reserved($username)) {
    echo json_encode(['available' => false, 'reason' => 'reserved']);
    exit;
}

// اگر کاربر لاگین است و همین نام کاربری فعلی خودش را چک می‌کند (صفحه‌ی تنظیمات)، آن را نادیده بگیر
$excludeId = null;
if (is_logged_in()) {
    $me = current_user();
    $excludeId = (int) $me['id'];
}

if (is_username_taken($username, $excludeId)) {
    echo json_encode(['available' => false, 'reason' => 'taken']);
    exit;
}

echo json_encode(['available' => true, 'reason' => null]);

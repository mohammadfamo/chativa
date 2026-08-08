<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$csrf = $data['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf']);
    exit;
}

$me = current_user();

if (is_email_verified($me)) {
    echo json_encode(['ok' => true, 'already_verified' => true]);
    exit;
}

// جلوگیری از ارسال درخواست مکرر: حداکثر هر ۶۰ ثانیه یک‌بار
$now = time();
if (!empty($_SESSION['last_verification_resend_at']) && ($now - (int) $_SESSION['last_verification_resend_at']) < 60) {
    http_response_code(429);
    echo json_encode(['error' => 'too_soon']);
    exit;
}
$_SESSION['last_verification_resend_at'] = $now;

$sent = send_verification_email((int) $me['id'], (string) $me['email'], $me['username']);

echo json_encode(['ok' => $sent]);

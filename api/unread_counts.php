<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$me = current_user();
$myId = (int) $me['id'];
$activeId = isset($_GET['active_id']) ? (int) $_GET['active_id'] : 0;

$conversations = list_conversations_for_user($myId);
$conversationCounts = [];
$conversationItems = [];
foreach ($conversations as $conv) {
    $convId = (int) $conv['conversation_id'];
    $unread = count_unread_private($convId, $myId);
    $conversationCounts[(string) $convId] = $unread;
    $conversationItems[] = [
        'id'   => $convId,
        'html' => conv_item_html($conv, $unread, $activeId === $convId),
    ];
}

echo json_encode([
    'public'              => count_unread_public($myId),
    'conversations'       => $conversationCounts,
    'conversation_items'  => $conversationItems,
]);

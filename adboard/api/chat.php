<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/chat-error.log');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

/// GET - Load messages between two users for an ad
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $adId = $conn->real_escape_string($_GET['ad_id'] ?? '');
    $withUser = $conn->real_escape_string($_GET['with_user'] ?? '');

    if (!$adId || !$withUser) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    // Mark messages as read when loading chat
    $markReadSql = "UPDATE messages 
                    SET is_read = 1 
                    WHERE ad_id = '$adId' 
                    AND from_user_id = '$withUser' 
                    AND to_user_id = '$userId' 
                    AND is_read = 0";
    $conn->query($markReadSql);

    $sql = "
      SELECT m.id, m.message, m.from_user_id, m.to_user_id, m.created_at, u.username, m.is_read
      FROM messages m
      JOIN users u ON m.from_user_id = u.id
      WHERE m.ad_id = '$adId'
      AND (
        (m.from_user_id = '$userId' AND m.to_user_id = '$withUser')
        OR
        (m.from_user_id = '$withUser' AND m.to_user_id = '$userId')
      )
      ORDER BY m.created_at ASC
    ";

    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("Chat messages SQL error: " . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed', 'details' => $conn->error]);
        exit;
    }

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    echo json_encode($messages);
    exit;
}

// POST - Send new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($data['action']) || $data['action'] !== 'send_message') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }

    $adId = $conn->real_escape_string($data['ad_id'] ?? '');
    $toUserId = $conn->real_escape_string($data['to_user_id'] ?? '');
    $message = $conn->real_escape_string($data['message'] ?? '');

    if (!$adId || !$toUserId || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing data']);
        exit;
    }

    $fromUserId = $userId;

    $sql = "INSERT INTO messages (ad_id, from_user_id, to_user_id, message)
            VALUES ('$adId', '$fromUserId', '$toUserId', '$message')";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Message sent', 'message_id' => $conn->insert_id]);
    } else {
        error_log("Chat insert error: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send message', 'error' => $conn->error]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>
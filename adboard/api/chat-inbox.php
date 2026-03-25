<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/chat-inbox-error.log');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// POST - Mark messages as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    if ($action === 'mark_read') {
        // ONLY mark specific conversation as read (removed "mark all" option)
        if (isset($data['ad_id']) && isset($data['with_user'])) {
            $adId = $conn->real_escape_string($data['ad_id']);
            $withUser = $conn->real_escape_string($data['with_user']);
            
            $sql = "UPDATE messages 
                    SET is_read = 1 
                    WHERE ad_id = '$adId' 
                    AND from_user_id = '$withUser' 
                    AND to_user_id = '$userId' 
                    AND is_read = 0";
            
            $conn->query($sql);
            echo json_encode(['success' => true, 'message' => 'Conversation marked as read']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing ad_id or with_user']);
        }
        exit;
    }
}

// GET - Get all conversations for this user
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "
      SELECT 
        m.ad_id,
        m.from_user_id,
        m.to_user_id,
        m.message,
        m.created_at,
        m.is_read,
        u.username AS sender_username,
        a.title AS ad_title
      FROM messages m
      JOIN users u ON u.id = m.from_user_id
      JOIN ads a ON a.id = m.ad_id
      WHERE m.from_user_id = '$userId' OR m.to_user_id = '$userId'
      ORDER BY m.created_at DESC
    ";

    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("Chat inbox SQL error: " . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed', 'details' => $conn->error]);
        exit;
    }

    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $otherUserId = ($row['from_user_id'] == $userId) ? $row['to_user_id'] : $row['from_user_id'];
        $otherUsername = ($row['from_user_id'] == $userId) ? 
            getToUserUsername($conn, $row['to_user_id']) : 
            $row['sender_username'];
        
        $convKey = $row['ad_id'] . '_' . $otherUserId;
        
        if (!isset($conversations[$convKey])) {
            $conversations[$convKey] = [
                'ad_id' => $row['ad_id'],
                'other_user_id' => $otherUserId,
                'other_username' => $otherUsername,
                'ad_title' => $row['ad_title'],
                'unread_count' => 0,
                'last_message' => $row['message'],
                'last_message_time' => $row['created_at']
            ];
        }
        
        // Count ONLY unread messages (messages from other user that haven't been read)
        if ($row['from_user_id'] != $userId && $row['is_read'] == 0) {
            $conversations[$convKey]['unread_count']++;
        }
    }

    $conversationsList = array_values($conversations);
    
    $totalUnread = 0;
    foreach ($conversationsList as $conv) {
        $totalUnread += (int)$conv['unread_count'];
    }

    echo json_encode([
        'conversations' => $conversationsList,
        'total_unread' => $totalUnread
    ]);
    exit;
}

function getToUserUsername($conn, $userId) {
    $sql = "SELECT username FROM users WHERE id = '$userId'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['username'];
    }
    return 'Unknown';
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>
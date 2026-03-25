<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// Prevent displaying PHP errors to client
ini_set('display_errors', 0);
error_reporting(0);

if ($action === 'get_users') {
    $sql = "SELECT id, username, email, is_admin FROM users ORDER BY id DESC";
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);

} elseif ($action === 'get_ads') {
    $sql = "
      SELECT a.id, a.title, a.description, u.username 
      FROM ads a
      JOIN users u ON a.user_id = u.id
      ORDER BY a.id DESC
    ";
    $result = $conn->query($sql);
    $ads = [];
    while ($row = $result->fetch_assoc()) {
        $ads[] = $row;
    }
    echo json_encode($ads);

} elseif ($action === 'delete_ad') {
    $ad_id = $conn->real_escape_string($_GET['id'] ?? '');
    if (!$ad_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing ad ID']);
        exit;
    }

    // Delete comments first
    $sql = "DELETE FROM comments WHERE ad_id = '$ad_id'";
    $conn->query($sql);

    // Then delete ad
    $sql = "DELETE FROM ads WHERE id = '$ad_id'";
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Ad deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete ad']);
    }
    exit;

} elseif ($action === 'toggle_admin') {
    $user_id = $conn->real_escape_string($_GET['id']);
    $sql = "SELECT is_admin FROM users WHERE id = '$user_id'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $new_status = $row['is_admin'] == 1 ? 0 : 1;

    $sql = "UPDATE users SET is_admin = '$new_status' WHERE id = '$user_id'";
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'is_admin' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
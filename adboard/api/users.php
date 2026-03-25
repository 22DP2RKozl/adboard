<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $user_id = $conn->real_escape_string($_GET['id']);
    $sql = "SELECT id, username FROM users WHERE id = '$user_id'";
    $result = $conn->query($sql);

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $user = $result->fetch_assoc();
    echo json_encode($user);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Missing user ID']);
?>
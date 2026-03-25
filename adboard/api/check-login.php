<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    include '../db.php';
    $sql = "SELECT id, username, is_admin FROM users WHERE id = '$user_id'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    echo json_encode([
      'loggedIn' => true,
      'user_id' => $row['id'],
      'username' => $row['username'],
      'is_admin' => $row['is_admin']
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
?>
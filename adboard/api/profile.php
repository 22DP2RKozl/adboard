<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// GET - Get current user profile data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT id, username, email FROM users WHERE id = '$userId'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit;
}

// POST - Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $newUsername = isset($data['username']) ? trim($data['username']) : '';
    $newPassword = isset($data['password']) ? $data['password'] : '';
    $confirmPassword = isset($data['confirm_password']) ? $data['confirm_password'] : '';
    $currentPassword = isset($data['current_password']) ? $data['current_password'] : '';
    
    // Validate current password
    $checkSql = "SELECT password FROM users WHERE id = '$userId'";
    $checkResult = $conn->query($checkSql);
    $user = $checkResult->fetch_assoc();
    
    if (!password_verify($currentPassword, $user['password'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Validate new username (if provided)
    if (!empty($newUsername)) {
        // Check if username is already taken
        $checkUsernameSql = "SELECT id FROM users WHERE username = '$newUsername' AND id != '$userId'";
        $checkUsernameResult = $conn->query($checkUsernameSql);
        
        if ($checkUsernameResult->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username is already taken']);
            exit;
        }
        
        if (strlen($newUsername) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']);
            exit;
        }
    }
    
    // Validate new password (if provided)
    if (!empty($newPassword)) {
        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }
        
        if (strlen($newPassword) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }
    }
    
    // Build update query
    $updateFields = [];
    
    if (!empty($newUsername)) {
        $updateFields[] = "username = '$newUsername'";
    }
    
    if (!empty($newPassword)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateFields[] = "password = '$hashedPassword'";
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No changes to update']);
        exit;
    }
    
    $updateSql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = '$userId'";
    
    if ($conn->query($updateSql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update profile', 'error' => $conn->error]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
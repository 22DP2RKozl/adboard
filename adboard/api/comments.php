<?php
session_start();
include '../db.php'; // Make sure this path is correct
header('Content-Type: application/json');

// Allow GET requests without login
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ad_id'])) {
    $ad_id = $conn->real_escape_string($_GET['ad_id']);
    $sql = "
        SELECT c.id, c.comment, u.username, c.user_id 
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.ad_id = '$ad_id'
        ORDER BY c.id DESC
    ";
    $result = $conn->query($sql);
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed']);
        exit;
    }
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    echo json_encode($comments);
    exit;
}

// POST actions require login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing action']);
        exit;
    }

    $action = $data['action'];

    if ($action === 'post_comment') {
        $ad_id = $conn->real_escape_string($data['ad_id'] ?? '');
        $comment = $conn->real_escape_string($data['comment'] ?? '');
        if (!$ad_id || !$comment) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }
        $user_id = $_SESSION['user_id'];
        $sql = "INSERT INTO comments (ad_id, user_id, comment) VALUES ('$ad_id', '$user_id', '$comment')";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Comment posted']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to post comment']);
        }
        exit;
    }

    if ($action === 'edit_comment') {
        $comment_id = $conn->real_escape_string($data['comment_id'] ?? '');
        $new_comment = $conn->real_escape_string($data['comment'] ?? '');
        if (!$comment_id || !$new_comment) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }

        $sql = "SELECT user_id FROM comments WHERE id = '$comment_id'";
        $result = $conn->query($sql);
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Comment not found']);
            exit;
        }
        $row = $result->fetch_assoc();

        $current_user_id = $_SESSION['user_id'];
        $admin_sql = "SELECT is_admin FROM users WHERE id = '$current_user_id'";
        $admin_result = $conn->query($admin_sql);
        $admin_row = $admin_result->fetch_assoc();
        $isAdmin = $admin_row['is_admin'] == 1;

        if ($row['user_id'] != $current_user_id && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You cannot edit this comment']);
            exit;
        }

        $sql = "UPDATE comments SET comment = '$new_comment' WHERE id = '$comment_id'";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Comment updated']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update comment']);
        }
        exit;
    }

    if ($action === 'delete_comment') {
        $comment_id = $conn->real_escape_string($data['comment_id'] ?? '');
        if (!$comment_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing comment ID']);
            exit;
        }

        $sql = "SELECT user_id FROM comments WHERE id = '$comment_id'";
        $result = $conn->query($sql);
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Comment not found']);
            exit;
        }
        $row = $result->fetch_assoc();

        $current_user_id = $_SESSION['user_id'];
        $admin_sql = "SELECT is_admin FROM users WHERE id = '$current_user_id'";
        $admin_result = $conn->query($admin_sql);
        $admin_row = $admin_result->fetch_assoc();
        $isAdmin = $admin_row['is_admin'] == 1;

        if ($row['user_id'] != $current_user_id && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You cannot delete this comment']);
            exit;
        }

        $sql = "DELETE FROM comments WHERE id = '$comment_id'";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Comment deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete comment']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown request']);
}
?>
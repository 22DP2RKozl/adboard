<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

// Allow GET requests without login
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ad_id'])) {
    $ad_id = $conn->real_escape_string($_GET['ad_id']);
    
    // Get comments with ratings
    $sql = "
    SELECT c.id, c.comment, c.rating, u.username, c.user_id
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
    
    // Get average rating and count
    $rating_sql = "
    SELECT AVG(rating) as avg_rating, COUNT(rating) as rating_count
    FROM comments
    WHERE ad_id = '$ad_id' AND rating IS NOT NULL
    ";
    $rating_result = $conn->query($rating_sql);
    $rating_data = $rating_result->fetch_assoc();
    
    echo json_encode([
        'comments' => $comments,
        'avg_rating' => round($rating_data['avg_rating'], 1),
        'rating_count' => (int)$rating_data['rating_count']
    ]);
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
        $rating = isset($data['rating']) ? (int)$data['rating'] : null;
        
        // Validate rating (1-5) - MANDATORY
        if ($rating === null || $rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rating is required (1-5 stars)']);
            exit;
        }
        
        $user_id = $_SESSION['user_id'];
        
        // Check if user already rated this ad
        $check_sql = "SELECT id FROM comments WHERE ad_id = '$ad_id' AND user_id = '$user_id'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You have already rated this ad']);
            exit;
        }
        
        $comment_value = $comment ? "'$comment'" : "NULL";
        
        $sql = "INSERT INTO comments (ad_id, user_id, comment, rating) VALUES ('$ad_id', '$user_id', $comment_value, '$rating')";
        
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to submit rating']);
        }
        exit;
    }
    
    if ($action === 'edit_comment') {
        $comment_id = $conn->real_escape_string($data['comment_id'] ?? '');
        $new_comment = isset($data['comment']) ? trim($data['comment']) : '';
        $rating = isset($data['rating']) ? (int)$data['rating'] : null;
        
        if (!$comment_id) {
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
        
        $update_parts = [];
        if ($new_comment) {
            $update_parts[] = "comment = '$new_comment'";
        }
        if ($rating !== null && $rating >= 1 && $rating <= 5) {
            $update_parts[] = "rating = '$rating'";
        }
        
        if (empty($update_parts)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No changes to update']);
            exit;
        }
        
        $sql = "UPDATE comments SET " . implode(', ', $update_parts) . " WHERE id = '$comment_id'";
        
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
<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_filter = isset($_GET['user_id']) ? $conn->real_escape_string($_GET['user_id']) : null;
    $sort_order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';
    
    // Base query - Include rating data
    $sql = "
    SELECT a.id, a.title, a.price, a.location, a.description, u.username, u.phone, u.show_contact, a.user_id,
           COALESCE(AVG(c.rating), 0) as avg_rating,
           COUNT(c.rating) as rating_count
    FROM ads a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN comments c ON a.id = c.ad_id AND c.rating IS NOT NULL
    ";
    
    if ($user_filter !== null) {
        $sql .= " WHERE a.user_id = '$user_filter'";
    }
    
    if (isset($_GET['mine']) && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $sql = "
        SELECT a.id, a.title, a.price, a.location, a.description, u.username, u.phone, u.show_contact, a.user_id,
               COALESCE(AVG(c.rating), 0) as avg_rating,
               COUNT(c.rating) as rating_count
        FROM ads a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN comments c ON a.id = c.ad_id AND c.rating IS NOT NULL
        WHERE a.user_id = '$user_id'
        ";
    }
    
    $sql .= " GROUP BY a.id";
    
    if (!isset($_GET['mine']) && $user_filter === null && !isset($_GET['order'])) {
        $sql .= " ORDER BY a.id DESC";
    } else {
        $sql .= " ORDER BY a.title $sort_order";
    }
    
    $result = $conn->query($sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed', 'details' => $conn->error]);
        exit;
    }
    
    $ads = [];
    while ($row = $result->fetch_assoc()) {
        $ads[] = $row;
    }
    
    echo json_encode($ads);
    exit;
}

// POST - Create new ad
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['title']) || !isset($data['description']) || !isset($data['price']) || !isset($data['location'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $title = $conn->real_escape_string($data['title']);
    $price = $conn->real_escape_string($data['price']);
    $location = $conn->real_escape_string($data['location']);
    $description = $conn->real_escape_string($data['description']);
    $user_id = $_SESSION['user_id'];

    $sql = "INSERT INTO ads (title, price, location, description, user_id) VALUES ('$title', '$price', '$location', '$description', '$user_id')";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Ad posted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to post ad', 'error' => $conn->error]);
    }
    exit;
}

// PUT - Update existing ad
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $ad_id = $conn->real_escape_string($data['ad_id'] ?? '');
    $user_id = $_SESSION['user_id'];

    // Verify ad belongs to user
    $check_sql = "SELECT user_id FROM ads WHERE id = '$ad_id'";
    $check_result = $conn->query($check_sql);
    $ad = $check_result->fetch_assoc();

    if (!$ad || $ad['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only edit your own ads']);
        exit;
    }

    $title = $conn->real_escape_string($data['title']);
    $price = $conn->real_escape_string($data['price']);
    $location = $conn->real_escape_string($data['location']);
    $description = $conn->real_escape_string($data['description']);

    $sql = "UPDATE ads SET title = '$title', price = '$price', location = '$location', description = '$description' WHERE id = '$ad_id'";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Ad updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update ad', 'error' => $conn->error]);
    }
    exit;
}

// DELETE - Delete ad
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $ad_id = $conn->real_escape_string($_GET['id'] ?? '');
    $user_id = $_SESSION['user_id'];

    // Verify ad belongs to user
    $check_sql = "SELECT user_id FROM ads WHERE id = '$ad_id'";
    $check_result = $conn->query($check_sql);
    $ad = $check_result->fetch_assoc();

    if (!$ad || $ad['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete your own ads']);
        exit;
    }

    // Delete comments first
    $conn->query("DELETE FROM comments WHERE ad_id = '$ad_id'");
    
    // Delete ad
    $sql = "DELETE FROM ads WHERE id = '$ad_id'";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Ad deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete ad', 'error' => $conn->error]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>
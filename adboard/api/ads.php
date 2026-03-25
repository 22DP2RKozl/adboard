<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get filter and sort options
    $user_filter = isset($_GET['user_id']) ? $conn->real_escape_string($_GET['user_id']) : null;
    $sort_order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

    // Base query - Include price and location
    $sql = "
      SELECT a.id, a.title, a.price, a.location, a.description, u.username, a.user_id 
      FROM ads a 
      JOIN users u ON a.user_id = u.id
    ";

    // Apply user filter if set
    if ($user_filter !== null) {
        $sql .= " WHERE a.user_id = '$user_filter'";
    }

    // Apply mine filter if set
    if (isset($_GET['mine']) && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $sql = "
          SELECT a.id, a.title, a.price, a.location, a.description, u.username, a.user_id 
          FROM ads a 
          JOIN users u ON a.user_id = u.id
          WHERE a.user_id = '$user_id'
        ";
    }

    // Always order by id DESC unless overridden by other filters
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    

    // Check ALL 4 required fields
    if (!isset($data['title']) || !isset($data['description']) || !isset($data['price']) || !isset($data['location'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields',
            'received' => $data,
            'raw' => $rawInput
        ]);
        exit;
    }

    $title = $conn->real_escape_string($data['title']);
    $price = $conn->real_escape_string($data['price']);
    $location = $conn->real_escape_string($data['location']);
    $description = $conn->real_escape_string($data['description']);
    $user_id = $_SESSION['user_id'];

    // INSERT with all 4 fields
    $sql = "INSERT INTO ads (title, price, location, description, user_id) VALUES ('$title', '$price', '$location', '$description', '$user_id')";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Ad posted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to post ad', 'error' => $conn->error]);
    }
}
?>
<?php
$host = "localhost";
$user = "root";
$password = "admin";
$dbname = "ad_board";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}
?>
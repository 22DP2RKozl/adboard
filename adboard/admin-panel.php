<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Check if user is admin
include 'db.php';
$user_id = $_SESSION['user_id'];
$sql = "SELECT is_admin FROM users WHERE id = '$user_id'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

if ($row['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Panel</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

<!-- Navigation -->
<header class="navbar">
  <div class="logo">AdBoard Admin</div>
  <nav class="nav-links">
    <a href="index.php">Home</a>
    <a href="#" onclick="logoutUser()">Logout</a>
  </nav>
</header>

<main class="container">

  <!-- Users Section -->
  <section class="admin-section">
    <h2>👥 Manage Users</h2>
    <table id="users-table" class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Email</th>
          <th>Is Admin</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </section>

  <!-- Ads Section -->
  <section class="admin-section">
    <h2>🗑️ Manage Ads</h2>
    <table id="ads-table" class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Description</th>
          <th>User</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </section>

</main>

<script src="script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  loadUsers();
  loadAdminAds();
});
</script>

</body>
</html>
<?php
session_start();
include 'db.php';

// Fetch list of users for dropdown
$user_sql = "SELECT id, username FROM users ORDER BY username";
$user_result = $conn->query($user_sql);
$users = [];
while ($row = $user_result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>AdBoard - Home</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

<!-- Navigation Bar -->
<header class="navbar">
  <div class="logo">AdBoard</div>
  <nav class="nav-links">
    <a href="index.php">Home</a>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="dashboard.php">Dashboard</a>
      <a href="#" onclick="toggleChatInbox(); return false;">
        💬 Messages
        <span id="chat-notification" class="notification-badge" style="display:none;">0</span>
      </a>
      <a href="#" id="logout-btn">Logout</a>
    <?php else: ?>
      <button onclick="toggleAuthModal()">Login / Register</button>
    <?php endif; ?>
  </nav>
</header>

<!-- Filter Section -->
<section class="filter-section">
  <label for="user-filter">Filter by User:</label>
  <select id="user-filter">
    <option value="">All Users</option>
    <?php foreach ($users as $u): ?>
      <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
    <?php endforeach; ?>
  </select>

  <label for="sort-order">Sort Title:</label>
  <select id="sort-order">
    <option value="asc">A → Z</option>
    <option value="desc">Z → A</option>
  </select>

  <!-- Keyword Search -->
  <input type="text" id="search-input" placeholder="Search by keyword..." />
</section>

<!-- Ads Feed -->
<main class="container">
  <h1>Welcome to AdBoard</h1>
  <p>Browse ads posted by users or log in/register to post your own!</p>
  <div id="ads-container"></div>
</main>

<!-- Authentication Modal -->
<div class="auth-modal" id="authModal">
  <div class="auth-modal-content">
    <span class="close-btn" onclick="toggleAuthModal()">&times;</span>

    <!-- Tabs -->
    <div class="auth-tabs">
      <button class="tab-btn active" onclick="switchTab('login')">Login</button>
      <button class="tab-btn" onclick="switchTab('register')">Register</button>
    </div>

    <!-- Login Form -->
    <form class="auth-form login-form" id="loginForm" onsubmit="handleLogin(event)">
      <h3>Login</h3>
      <input type="email" placeholder="Email" required />
      <input type="password" placeholder="Password" required />
      <button type="submit">Login</button>
    </form>

    <!-- Register Form -->
    <form class="auth-form register-form" id="registerForm" onsubmit="handleRegister(event)" style="display:none;">
      <h3>Register</h3>
      <input type="text" placeholder="Username" required />
      <input type="email" placeholder="Email" required />
      <input type="password" placeholder="Password" required />
      <button type="submit">Register</button>
    </form>
  </div>
</div>

<!-- Chat Inbox Modal -->
<div class="chat-inbox-modal" id="chat-inbox-modal">
  <div class="chat-inbox-content">
    <span class="close-inbox" onclick="toggleChatInbox()">&times;</span>
    <h3>📬 Your Conversations</h3>
    <div id="chat-inbox-list" class="conversation-list">
      <p>Loading conversations...</p>
    </div>
  </div>
</div>

<!-- Chat Modal -->
<div class="chat-modal" id="chatModal">
  <div class="chat-modal-content">
    <span class="close-chat" onclick="closeChat()">✖️ Close</span>
    <h4>Chat with <span id="chat-username"></span></h4>
    <div id="chat-messages" class="chat-box"></div>
    <form id="chat-form">
      <input type="text" id="chat-input" placeholder="Type a message..." required />
      <button type="submit">Send</button>
    </form>
  </div>
</div>

<!-- Pass PHP session data to JS -->
<script>
  const sessionUserId = <?= json_encode($_SESSION['user_id'] ?? 0); ?>;
  const isAdmin = <?= json_encode($_SESSION['is_admin'] ?? 0); ?>;
</script>
<script src="script.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    loadAds(); // Initial load

    document.getElementById('user-filter')?.addEventListener('change', loadAds);
    document.getElementById('sort-order')?.addEventListener('change', loadAds);
    document.getElementById('search-input')?.addEventListener('input', loadAds);
  });
</script>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

<!-- Navigation Bar -->
<header class="navbar">
  <div class="logo">AdBoard</div>
  
  <nav class="nav-links">
    <a href="index.php">Home</a>
    <a href="dashboard.php">Dashboard</a>
    <a href="#" onclick="toggleChatInbox(); return false;">
      💬 Messages
      <span id="chat-notification" class="notification-badge" style="display:none;">0</span>
    </a>
    <a href="#" onclick="logoutUser()">Logout</a>
  </nav>
</header>

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

<!-- Dashboard Content -->
<main class="container">
  <section class="post-ad-section">
    <h2>📝 Post a New Ad</h2>
    <div class="ad-form-card">
      <form id="post-ad-form">
        <label for="title">Ad Title</label>
        <input type="text" id="title" placeholder="e.g. Construction Worker" required />

        <label for="price">Price ($)</label>
        <input type="number" id="price" step="0.01" min="0" placeholder="e.g. 150.00" required />

        <label for="location">Location</label>
        <input type="text" id="location" placeholder="e.g. New York, NY" required />

        <label for="description">Description</label>
        <textarea id="description" rows="5" placeholder="Describe what you're selling..." required></textarea>

        <button type="submit" class="btn-primary">Post Ad</button>
      </form>
    </div>
  </section>

  <!-- Profile Management Section -->
  <section class="profile-section">
    <h2>👤 Manage Profile</h2>
    <div class="ad-form-card">
      <form id="profile-form">
        <label for="current-username">Current Username</label>
        <input type="text" id="current-username" value="" disabled style="background-color: #f0f0f0;" />

        <label for="new-username">New Username (optional)</label>
        <input type="text" id="new-username" placeholder="Enter new username" />

        <label for="new-password">New Password (optional)</label>
        <input type="password" id="new-password" placeholder="Enter new password" />

        <label for="confirm-password">Confirm New Password</label>
        <input type="password" id="confirm-password" placeholder="Confirm new password" />

        <label for="current-password-confirm">Enter Current Password to Confirm</label>
        <input type="password" id="current-password-confirm" placeholder="Enter your current password" required />

        <button type="submit" class="btn-primary">Update Profile</button>
      </form>
    </div>
  </section>

  <!-- Contact Info Management Section -->
  <section class="profile-section">
    <h2>📞 Contact Information</h2>
    <div class="ad-form-card">
      <form id="contact-form">
        <label for="contact-phone">Phone Number (optional)</label>
        <input type="tel" id="contact-phone" placeholder="e.g. +1 234 567 8900" />

        <label class="contact-toggle-label">
          <span>Show phone number on my ads</span>
          <input type="checkbox" id="show-contact" />
        </label>

        <div style="display: flex; gap: 10px; margin-top: 15px;">
          <button type="submit" class="btn-primary">Save Contact Info</button>
          <button type="button" id="clear-contact-btn" class="btn-secondary">Clear Contact Info</button>
        </div>
      </form>
    </div>
  </section>

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

  <h2>Your Ads</h2>
  <div id="user-ads-container"></div>
</main>

<!-- Edit Ad Modal -->
<div class="auth-modal" id="editAdModal">
  <div class="auth-modal-content">
    <span class="close-btn" onclick="closeEditAdModal()">&times;</span>
    <h3>✏️ Edit Ad</h3>
    <form id="edit-ad-form">
      <input type="hidden" id="edit-ad-id" />
      
      <label for="edit-title">Ad Title</label>
      <input type="text" id="edit-title" required />

      <label for="edit-price">Price ($)</label>
      <input type="number" id="edit-price" step="0.01" min="0" required />

      <label for="edit-location">Location</label>
      <input type="text" id="edit-location" required />

      <label for="edit-description">Description</label>
      <textarea id="edit-description" rows="5" required></textarea>

      <button type="submit" class="btn-primary">Save Changes</button>
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

<script>
  const sessionUserId = <?= json_encode($_SESSION['user_id'] ?? 0); ?>;
  const isAdmin = <?= json_encode($_SESSION['is_admin'] ?? 0); ?>;
</script>
<script src="script.js"></script>
<script>
  // Load current user profile data
  document.addEventListener('DOMContentLoaded', () => {
    loadUserProfile();
  });
</script>
</body>
</html>
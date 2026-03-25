// ====== Global Variables ======y
let currentChatAdId = null;
let currentChatUserId = null;
let chatPollingInterval = null;

// ====== DOM Ready ======
document.addEventListener('DOMContentLoaded', () => {
  // Initialize pages
  if (document.getElementById('ads-container')) {
    loadAds();
  }
  if (document.getElementById('user-ads-container')) {
    loadUserAds();
  }
  if (document.getElementById('users-table')) {
    loadUsers();
  }
  if (document.getElementById('ads-table')) {
    loadAdminAds();
  }
  
  // Load chat inbox if user is logged in
  if (typeof sessionUserId !== 'undefined' && sessionUserId !== 0) {
    loadChatInbox();
    // Refresh inbox every 5 seconds
    setInterval(loadChatInbox, 5000);
    
    // Load user profile data
    loadUserProfile();
  }

  // Post Ad Form Handler
  const postForm = document.getElementById("post-ad-form");
  if (postForm && !postForm.dataset.submitted) {
    postForm.addEventListener("submit", handlePostAd);
    postForm.dataset.submitted = true;
  }

  // Chat Form Handler
  const chatForm = document.getElementById('chat-form');
  if (chatForm && !chatForm.dataset.submitted) {
    chatForm.addEventListener('submit', handleChatSubmit);
    chatForm.dataset.submitted = true;
  }

  // Profile Form Handler
  const profileForm = document.getElementById('profile-form');
  if (profileForm && !profileForm.dataset.submitted) {
    profileForm.addEventListener('submit', handleProfileUpdate);
    profileForm.dataset.submitted = true;
  }

  // Logout Button
  const logoutBtn = document.getElementById("logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", logoutUser);
  }
});

// ====== Modal & Auth Functions ======
function toggleAuthModal() {
  const modal = document.getElementById("authModal");
  if (!modal) return;
  modal.style.display = modal.style.display === "block" ? "none" : "block";
}

function switchTab(tab) {
  document.querySelectorAll('.auth-form').forEach(form => form.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

  if (tab === 'login') {
    document.getElementById('loginForm').style.display = 'block';
    document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
  } else {
    document.getElementById('registerForm').style.display = 'block';
    document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
  }
}

async function handleLogin(e) {
  e.preventDefault();
  const [email, password] = e.target.querySelectorAll("input");

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'login',
        email: email.value,
        password: password.value
      })
    });

    const data = await res.json();
    alert(data.message);

    if (data.success) {
      window.location.href = 'dashboard.php';
    }
  } catch (err) {
    console.error("Login error:", err);
    alert("An error occurred during login.");
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const [username, email, password] = e.target.querySelectorAll("input");

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'register',
        username: username.value,
        email: email.value,
        password: password.value
      })
    });

    const data = await res.json();
    alert(data.message);

    if (data.success) {
      switchTab('login');
    }
  } catch (err) {
    console.error("Registration error:", err);
    alert("An error occurred during registration.");
  }
}

function logoutUser() {
  if (confirm("Are you sure you want to log out?")) {
    fetch('api/logout.php')
      .then(() => {
        window.location.href = 'index.php';
      })
      .catch(err => {
        console.error("Logout failed:", err);
        alert("Logout failed.");
      });
  }
}

// ====== Ads Functions ======
async function loadAds() {
  const container = document.getElementById('ads-container');
  if (!container) return;

  try {
    const url = buildAdsUrl();
    const res = await fetch(url);

    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);

    const ads = await res.json();
    const filteredAds = filterAdsBySearch(ads);

    container.innerHTML = '';

    if (filteredAds.length === 0) {
      container.innerHTML = '<p>No ads match your search.</p>';
      return;
    }

    renderAds(container, filteredAds);
    attachCommentFormListener();
    loadAllComments();

  } catch (err) {
    console.error("Failed to load ads:", err);
    container.innerHTML = '<p>Failed to load ads.</p>';
  }
}

function buildAdsUrl() {
  const params = [];
  const userFilter = document.getElementById('user-filter')?.value || '';
  const sortOrder = document.getElementById('sort-order')?.value || 'asc';

  if (userFilter) params.push(`user_id=${userFilter}`);
  if (sortOrder) params.push(`order=${sortOrder}`);

  return 'api/ads.php' + (params.length ? '?' + params.join('&') : '');
}

function filterAdsBySearch(ads) {
  const searchInput = document.getElementById('search-input');
  const searchKeyword = searchInput ? searchInput.value.trim().toLowerCase() : '';

  if (!searchKeyword) return ads;

  return ads.filter(ad => {
    const titleMatch = ad.title?.toLowerCase().includes(searchKeyword);
    const descMatch = ad.description?.toLowerCase().includes(searchKeyword);
    return titleMatch || descMatch;
  });
}

function renderAds(container, ads) {
  ads.forEach(ad => {
    const isOwner = ad.user_id == sessionUserId;
    const formattedPrice = ad.price ? `$${parseFloat(ad.price).toFixed(2)}` : 'Free';
    const locationDisplay = ad.location ? `📍 ${escapeHtml(ad.location)}` : '';

    container.innerHTML += `
      <div class="ad-card">
        <h3>${escapeHtml(ad.title)}</h3>
        
        <!-- Price and Location -->
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #555; font-weight: bold;">
          <span style="color: #27ae60; font-size: 1.1rem;">${formattedPrice}</span>
          <span>${locationDisplay}</span>
        </div>
        
        <p>${escapeHtml(ad.description)}</p>
        <small>Posted by ${escapeHtml(ad.username)}</small>

        <!-- Chat Button (only for non-owners) -->
        ${!isOwner && sessionUserId !== 0 ? `
          <button class="chat-btn" data-ad-id="${ad.id}" data-to-user-id="${ad.user_id}">
            💬 Chat with ${escapeHtml(ad.username)}
          </button>
        ` : ''}

        <!-- Comment Section -->
        <div class="ad-comments" data-ad-id="${ad.id}">
          <h4>💬 Comments</h4>
          <div class="comment-list" id="comments-${ad.id}"></div>
          <form class="comment-form" style="display: none;">
            <textarea placeholder="Write a comment..." required></textarea>
            <button type="submit">Post Comment</button>
          </form>
        </div>
      </div>`;
  });

  // Attach chat button listeners after rendering
  document.querySelectorAll('.chat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const adId = btn.getAttribute('data-ad-id');
      const toUserId = btn.getAttribute('data-to-user-id');
      openChat(adId, toUserId);
    });
  });
}

function attachCommentFormListener() {
  const adsContainer = document.getElementById('ads-container');
  if (!adsContainer || adsContainer.dataset.commentListenerAttached) return;

  adsContainer.addEventListener('submit', async function (e) {
    const form = e.target.closest('.comment-form');
    if (!form) return;
    e.preventDefault();

    const adEl = form.closest('.ad-comments');
    const adId = adEl.getAttribute('data-ad-id');
    const textarea = form.querySelector('textarea');
    const commentText = textarea.value.trim();

    if (!commentText) return;

    const result = await postComment(adId, commentText);
    alert(result.message);

    if (result.success) {
      textarea.value = '';
      const list = document.getElementById(`comments-${adId}`);
      await loadCommentsForAd(adId, list);
    }
  });

  adsContainer.dataset.commentListenerAttached = true;
}

function loadAllComments() {
  document.querySelectorAll('.ad-comments').forEach(async (el) => {
    const adId = el.getAttribute('data-ad-id');
    const list = el.querySelector('.comment-list');
    await loadCommentsForAd(adId, list);

    const form = el.querySelector('.comment-form');
    if (form && isLoggedIn()) {
      form.style.display = 'block';
    }
  });
}

async function loadCommentsForAd(adId, container) {
  if (!container) return;

  try {
    const res = await fetch(`api/comments.php?ad_id=${adId}`);

    let comments;
    try {
      comments = await res.json();
    } catch (err) {
      console.error("Invalid JSON from comments.php", err);
      container.innerHTML = '<p>Failed to load comments.</p>';
      return;
    }

    container.innerHTML = '';

    if (comments.length === 0) {
      container.innerHTML = '<p>No comments yet.</p>';
      return;
    }

    comments.forEach(c => {
      const isOwner = c.user_id && sessionUserId && c.user_id == sessionUserId;
      const canDelete = isOwner || (typeof isAdmin !== 'undefined' && isAdmin);

      container.innerHTML += `
        <div class="comment-item" id="comment-${c.id}" data-user-id="${c.user_id}">
          <strong>${escapeHtml(c.username)}</strong><br/>
          <span class="comment-text">${escapeHtml(c.comment)}</span>
          ${canDelete ? `
            <div class="comment-actions">
              <button class="edit-btn">Edit</button>
              <button class="save-btn" style="display:none;">Save</button>
              <button class="delete-btn">Delete</button>
            </div>
          ` : ''}
          <textarea class="edit-input" style="display:none;">${escapeHtml(c.comment)}</textarea>
        </div>`;
    });

    attachCommentEventListeners(container);

  } catch (err) {
    console.error("Failed to load comments:", err);
    container.innerHTML = '<p>Failed to load comments.</p>';
  }
}

function attachCommentEventListeners(container) {
  if (!container) return;

  container.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const item = btn.closest('.comment-item');
      const textSpan = item.querySelector('.comment-text');
      const textarea = item.querySelector('.edit-input');
      const saveBtn = item.querySelector('.save-btn');

      if (!textSpan || !textarea || !saveBtn) return;

      textSpan.style.display = 'none';
      textarea.style.display = 'block';
      btn.style.display = 'none';
      saveBtn.style.display = 'inline-block';
    });
  });

  container.querySelectorAll('.save-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
      const item = btn.closest('.comment-item');
      const textarea = item.querySelector('.edit-input');
      const commentId = item.id.replace('comment-', '');

      const res = await fetch('api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'edit_comment',
          comment_id: commentId,
          comment: textarea.value.trim()
        })
      });

      const data = await res.json();
      alert(data.message);

      if (data.success) {
        item.querySelector('.comment-text').textContent = textarea.value.trim();
        item.querySelector('.comment-text').style.display = 'block';
        textarea.style.display = 'none';
        btn.style.display = 'none';
        const editBtn = item.querySelector('.edit-btn');
        if (editBtn) editBtn.style.display = 'inline-block';
      }
    });
  });

  container.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
      const item = btn.closest('.comment-item');
      const commentId = item.id.replace('comment-', '');

      if (!confirm("Delete this comment?")) return;

      const res = await fetch('api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'delete_comment',
          comment_id: commentId
        })
      });

      const data = await res.json();
      alert(data.message);

      if (data.success) {
        item.remove();
      }
    });
  });
}

async function postComment(adId, commentText) {
  const res = await fetch('api/comments.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'post_comment',
      ad_id: adId,
      comment: commentText
    })
  });

  return await res.json();
}

// ====== Dashboard: Post Ad (WITH PRICE & LOCATION) ======
async function handlePostAd(e) {
  e.preventDefault();
  
  const title = document.getElementById("title").value.trim();
  const price = document.getElementById("price").value.trim();
  const location = document.getElementById("location").value.trim();
  const description = document.getElementById("description").value.trim();

  // Debug: Check what's being sent
  console.log("Sending ad:", { title, price, location, description });

  const res = await fetch("api/ads.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ title, price, location, description })
  });

  // Debug: Check response
  const text = await res.text();
  console.log("Raw response:", text);
  
  let data;
  try {
    data = JSON.parse(text);
  } catch (err) {
    console.error("Invalid JSON response:", text);
    alert("Server error: " + text.substring(0, 100));
    return;
  }

  alert(data.message);

  if (data.success) {
    document.getElementById("title").value = "";
    document.getElementById("price").value = "";
    document.getElementById("location").value = "";
    document.getElementById("description").value = "";
    loadAds();
    if (document.getElementById('user-ads-container')) {
      loadUserAds();
    }
  }
}

async function loadUserAds() {
  try {
    const res = await fetch('api/ads.php?mine=1');
    const ads = await res.json();
    const container = document.getElementById('user-ads-container');
    if (!container) return;

    container.innerHTML = '';

    if (ads.length === 0) {
      container.innerHTML = '<p>You havent posted any ads yet.</p>';
      return;
    }

    ads.forEach(ad => {
      const formattedPrice = ad.price ? `$${parseFloat(ad.price).toFixed(2)}` : 'Free';
      const locationDisplay = ad.location ? `📍 ${escapeHtml(ad.location)}` : '';

      container.innerHTML += `
        <div class="ad-card">
          <h3>${escapeHtml(ad.title)}</h3>
          <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #555; font-weight: bold;">
            <span style="color: #27ae60; font-size: 1.1rem;">${formattedPrice}</span>
            <span>${locationDisplay}</span>
          </div>
          <p>${escapeHtml(ad.description)}</p>
          <small>Posted by ${escapeHtml(ad.username)}</small>
        </div>`;
    });

  } catch (err) {
    console.error("Failed to load your ads:", err);
    const container = document.getElementById('user-ads-container');
    if (container) {
      container.innerHTML = '<p>Failed to load your ads.</p>';
    }
  }
}

// ====== Admin Panel Functions ======
async function loadUsers() {
  try {
    const res = await fetch('api/admin.php?action=get_users');
    const users = await res.json();
    const tbody = document.querySelector('#users-table tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    users.forEach(user => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${user.id}</td>
        <td>${escapeHtml(user.username)}</td>
        <td>${escapeHtml(user.email)}</td>
        <td>${user.is_admin == 1 ? '✅ Yes' : '❌ No'}</td>
        <td>
          <button onclick="toggleAdmin(${user.id}, ${user.is_admin})">
            ${user.is_admin == 1 ? 'Demote' : 'Promote'}
          </button>
          <button class="delete-user-btn" data-user-id="${user.id}">Delete</button>
        </td>`;
      tbody.appendChild(tr);
    });

    // Attach delete user listeners
    tbody.querySelectorAll('.delete-user-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const userId = btn.getAttribute('data-user-id');
        deleteUser(userId);
      });
    });

  } catch (err) {
    console.error("Failed to load users:", err);
    alert("Failed to load users.");
  }
}

async function deleteUser(userId) {
  if (!confirm("Delete this user? This will also delete their ads and comments.")) return;

  const res = await fetch(`api/admin.php?action=delete_user&id=${userId}`);
  const data = await res.json();
  alert(data.message);

  if (data.success) {
    loadUsers();
  }
}

async function loadAdminAds() {
  const container = document.querySelector('#ads-table tbody');
  if (!container) return;

  try {
    const res = await fetch('api/admin.php?action=get_ads');
    const ads = await res.json();
    container.innerHTML = '';

    if (ads.length === 0) {
      container.innerHTML = '<tr><td colspan="5">No ads found.</td></tr>';
      return;
    }

    ads.forEach(ad => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${ad.id}</td>
        <td>${escapeHtml(ad.title)}</td>
        <td>${escapeHtml(ad.description)}</td>
        <td>${escapeHtml(ad.username)}</td>
        <td><button class="admin-delete-btn" data-ad-id="${ad.id}">Delete</button></td>`;

      const btn = tr.querySelector(".admin-delete-btn");
      if (btn) {
        btn.addEventListener("click", () => deleteAd(ad.id));
      }
      container.appendChild(tr);
    });

  } catch (err) {
    console.error("Failed to load admin ads:", err);
    container.innerHTML = '<tr><td colspan="5">Failed to load ads.</td></tr>';
  }
}

async function deleteAd(adId) {
  if (!confirm("Delete this ad?")) return;

  const res = await fetch(`api/admin.php?action=delete_ad&id=${adId}`);

  let data;
  try {
    data = await res.json();
  } catch (err) {
    console.error("Invalid JSON response from server:", err);
    data = { message: "Ad deleted successfully", success: true };
  }

  alert(data.message || 'Ad deleted');

  if (data.success) {
    loadAdminAds();
  }
}

async function toggleAdmin(userId, currentStatus) {
  const newStatus = currentStatus === 1 ? 0 : 1;
  await fetch(`api/admin.php?action=toggle_admin&id=${userId}&status=${newStatus}`);
  loadUsers();
}

// ====== Chat Functions ======
async function openChat(adId, toUserId) {
  if (!toUserId || !adId) {
    alert("Invalid chat target");
    console.error("Invalid chat target:", { adId, toUserId });
    return;
  }

  currentChatAdId = adId;
  currentChatUserId = toUserId;

  try {
    const usernameRes = await fetch(`api/users.php?id=${toUserId}`);
    if (!usernameRes.ok) {
      const errorText = await usernameRes.text();
      console.error("User fetch failed:", errorText);
      throw new Error("User not found");
    }

    const user = await usernameRes.json();
    document.getElementById('chat-username').textContent = user.username;
    document.getElementById('chatModal').style.display = 'block';

    await loadChatMessages(adId, toUserId);

    // Clear any existing interval first
    if (chatPollingInterval) {
      clearInterval(chatPollingInterval);
    }

    // Start polling for new messages
    chatPollingInterval = setInterval(() => {
      if (currentChatAdId && currentChatUserId) {
        loadChatMessages(currentChatAdId, currentChatUserId);
        loadChatInbox(); // Also update inbox unread count
      }
    }, 3000);
    
    // Mark messages as read when opening specific chat
    markConversationAsRead(adId, toUserId);

  } catch (err) {
    console.error("Failed to get chat user:", err);
    alert("Failed to start chat: " + err.message);
  }
}

// Mark specific conversation as read
async function markConversationAsRead(adId, toUserId) {
  try {
    console.log("Marking conversation as read:", { adId, toUserId });
    
    const res = await fetch('api/chat-inbox.php?action=mark_read', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ad_id: adId, with_user: toUserId })
    });
    
    const data = await res.json();
    console.log("Mark read response:", data);
    
    // Hide notification badge
    const notificationBadge = document.getElementById('chat-notification');
    if (notificationBadge) {
      notificationBadge.style.display = 'none';
    }
    
    // Reload inbox to update unread counts
    setTimeout(() => loadChatInbox(), 500);
  } catch (err) {
    console.error("Failed to mark conversation as read:", err);
  }
}

function closeChat() {
  document.getElementById('chatModal').style.display = 'none';

  // Stop polling
  if (chatPollingInterval) {
    clearInterval(chatPollingInterval);
    chatPollingInterval = null;
  }
}

async function loadChatMessages(adId, toUserId) {
  try {
    const res = await fetch(`api/chat.php?ad_id=${adId}&with_user=${toUserId}`);
    
    // Check if response is OK
    if (!res.ok) {
      const errorText = await res.text();
      console.error("Chat messages error:", errorText);
      document.getElementById('chat-messages').innerHTML = '<p>Failed to load messages</p>';
      return;
    }

    let messages;
    try {
      messages = await res.json();
    } catch (err) {
      console.error("Invalid JSON response:", err);
      document.getElementById('chat-messages').innerHTML = '<p>Failed to load messages</p>';
      return;
    }

    const chatBox = document.getElementById('chat-messages');
    chatBox.innerHTML = '';

    if (messages.length === 0) {
      chatBox.innerHTML = '<p>No messages yet.</p>';
      return;
    }

    messages.forEach(m => {
      // FIX: Compare as integers to avoid type mismatch
      const isMe = parseInt(m.from_user_id) === parseInt(sessionUserId);
      
      // Format timestamp
      const date = new Date(m.created_at);
      const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      
      chatBox.innerHTML += `
        <div class="message ${isMe ? 'sent' : 'received'}">
          <strong>${isMe ? 'You' : escapeHtml(m.username)}</strong>
          <span class="message-time">${timeStr}</span><br/>
          ${escapeHtml(m.message)}
        </div>`;
    });

    chatBox.scrollTop = chatBox.scrollHeight;

  } catch (err) {
    console.error("Failed to load chat:", err);
    document.getElementById('chat-messages').innerHTML = '<p>Failed to load messages</p>';
  }
}

async function handleChatSubmit(e) {
  e.preventDefault();

  const input = document.getElementById('chat-input');
  const message = input.value.trim();

  if (!message || !currentChatAdId || !currentChatUserId) return;

  const result = await fetch('api/chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'send_message',
      ad_id: currentChatAdId,
      to_user_id: currentChatUserId,
      message: message
    })
  });

  const data = await result.json();

  if (data.success) {
    input.value = '';
    loadChatMessages(currentChatAdId, currentChatUserId);
  }
}

// ====== Chat Inbox Functions ======
async function loadChatInbox() {
  try {
    const res = await fetch('api/chat-inbox.php');
    
    if (!res.ok) {
      const errorText = await res.text();
      console.error("Chat inbox error:", errorText);
      return;
    }
    
    const data = await res.json();
    
    if (data.conversations) {
      const inboxList = document.getElementById('chat-inbox-list');
      if (inboxList) {
        inboxList.innerHTML = '';
        
        if (data.conversations.length === 0) {
          inboxList.innerHTML = '<p class="no-chats">No conversations yet</p>';
        } else {
          data.conversations.forEach(conv => {
            const unreadClass = conv.unread_count > 0 ? 'has-unread' : '';
            const unreadBadge = conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : '';
            
            inboxList.innerHTML += `
              <div class="conversation-item ${unreadClass}" onclick="openChat(${conv.ad_id}, ${conv.other_user_id})">
                <div class="conversation-info">
                  <strong>${escapeHtml(conv.other_username)}</strong>
                  <span class="ad-title">Ad: ${escapeHtml(conv.ad_title)}</span>
                  <span class="last-message">${escapeHtml(conv.last_message || 'No messages')}</span>
                </div>
                ${unreadBadge}
              </div>`;
          });
        }
      }
      
      // Update notification badge
      const notificationBadge = document.getElementById('chat-notification');
      if (notificationBadge) {
        if (data.total_unread > 0) {
          notificationBadge.textContent = data.total_unread;
          notificationBadge.style.display = 'block';
        } else {
          notificationBadge.style.display = 'none';
        }
      }
    }
  } catch (err) {
    console.error("Failed to load chat inbox:", err);
  }
}

// Clear badge when inbox opens
function toggleChatInbox() {
  const inbox = document.getElementById('chat-inbox-modal');
  if (inbox) {
    const isOpening = inbox.style.display !== 'block';
    inbox.style.display = isOpening ? 'block' : 'none';
    
    if (isOpening) {
      loadChatInbox();
    }
  }
}

// ====== Profile Management Functions ======
async function loadUserProfile() {
  try {
    const res = await fetch('api/profile.php');
    const data = await res.json();
    
    if (data.success && data.user) {
      const currentUsernameInput = document.getElementById('current-username');
      if (currentUsernameInput) {
        currentUsernameInput.value = data.user.username;
      }
    }
  } catch (err) {
    console.error("Failed to load profile:", err);
  }
}

async function handleProfileUpdate(e) {
  e.preventDefault();
  
  const newUsername = document.getElementById('new-username').value.trim();
  const newPassword = document.getElementById('new-password').value.trim();
  const confirmPassword = document.getElementById('confirm-password').value.trim();
  const currentPassword = document.getElementById('current-password-confirm').value.trim();
  
  // Validate inputs
  if (!newUsername && !newPassword) {
    alert('Please enter a new username or password');
    return;
  }
  
  if (newPassword && newPassword !== confirmPassword) {
    alert('New passwords do not match');
    return;
  }
  
  if (!currentPassword) {
    alert('Please enter your current password to confirm');
    return;
  }
  
  try {
    const res = await fetch('api/profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username: newUsername,
        password: newPassword,
        confirm_password: confirmPassword,
        current_password: currentPassword
      })
    });
    
    const data = await res.json();
    alert(data.message);
    
    if (data.success) {
      // Clear form fields
      document.getElementById('new-username').value = '';
      document.getElementById('new-password').value = '';
      document.getElementById('confirm-password').value = '';
      document.getElementById('current-password-confirm').value = '';
      
      // Reload profile data
      loadUserProfile();
      
      // Reload ads to show new username
      if (document.getElementById('ads-container')) {
        loadAds();
      }
      if (document.getElementById('user-ads-container')) {
        loadUserAds();
      }
    }
  } catch (err) {
    console.error("Profile update error:", err);
    alert("An error occurred while updating profile");
  }
}

// ====== Utility Functions ======
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function isLoggedIn() {
  return typeof sessionUserId !== 'undefined' && sessionUserId !== 0;
}
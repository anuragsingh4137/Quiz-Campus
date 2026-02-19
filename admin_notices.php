<?php
session_start();
require 'config.php';

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Handle Add Notice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notice'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Always send to all dashboards (no audience option)
    $audience = 'all';

    if ($title !== '' && $description !== '') {
        $stmt = $conn->prepare("INSERT INTO notices (title, description, audience, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sss", $title, $description, $audience);
            if ($stmt->execute()) {
                $message = "✅ Notice added successfully! (Visible to all users)";
            } else {
                $error = "⚠️ Error adding notice: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $error = "⚠️ Could not prepare statement: " . htmlspecialchars($conn->error);
        }
    } else {
        $error = "⚠️ Please fill in both title and description.";
    }
}

// Handle Delete Notice (prepared)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM notices WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "🗑️ Notice deleted successfully!";
        } else {
            $error = "⚠️ Error deleting notice: " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    } else {
        $error = "⚠️ Could not prepare delete statement: " . htmlspecialchars($conn->error);
    }
}

// Pagination Setup
$notices_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $notices_per_page;

// Search Query Setup
$search_query = '';
$params = [];
$types = '';
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search_term = '%' . $_GET['search'] . '%';
    $search_query = " AND (title LIKE ? OR description LIKE ?)";
    $params = [$search_term, $search_term];
    $types = 'ss';
}

// Fetch Notices with Pagination and Search (prepared)
$sql = "SELECT * FROM notices WHERE 1=1 $search_query ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($search_query) {
        // bind (search_term, search_term, limit, offset)
        $types_all = $types . "ii";
        // prepare values for bind_param by reference
        $bind_values = [];
        $bind_values[] = &$types_all;
        $bind_values[] = &$params[0];
        $bind_values[] = &$params[1];
        $bind_values[] = &$notices_per_page;
        $bind_values[] = &$offset;
        call_user_func_array([$stmt, 'bind_param'], $bind_values);
    } else {
        $stmt->bind_param("ii", $notices_per_page, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // fallback
    $result = $conn->query("SELECT * FROM notices ORDER BY created_at DESC LIMIT $notices_per_page OFFSET $offset");
}

// Fetch Total Notices for Pagination (respect search)
if ($search_query) {
    // simpler safe fallback: do count with escaped string if prepare is messy
    $count_sql = "SELECT COUNT(*) AS total FROM notices WHERE 1=1 $search_query";
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt) {
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $total_notices_result = $count_stmt->get_result();
        $total_notices = $total_notices_result->fetch_assoc()['total'] ?? 0;
        $count_stmt->close();
    } else {
        $total_notices_result = $conn->query("SELECT COUNT(*) AS total FROM notices");
        $total_notices = $total_notices_result->fetch_assoc()['total'] ?? 0;
    }
} else {
    $total_notices_result = $conn->query("SELECT COUNT(*) AS total FROM notices");
    $total_notices = $total_notices_result->fetch_assoc()['total'];
}
$total_pages = max(1, ceil($total_notices / $notices_per_page));

function audience_label($v) {
    $v = strtolower(trim($v));
    if ($v === 'all') return 'All Users';
    if ($v === 'student') return 'Students';
    if ($v === 'teacher') return 'Teachers';
    if ($v === 'admin') return 'Admin Only';
    return htmlspecialchars($v);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Manage Notices</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    .notice-container { max-width: 900px; margin: 40px auto; background:#fff; padding:25px; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
    h2 { color:#2563eb; text-align:center; margin-bottom:25px; }
    form { display:flex; flex-direction:column; gap:12px; margin-bottom:30px; }
    input, textarea { padding:10px; border:1px solid #ccc; border-radius:6px; font-size:15px; }
    button { background:#2563eb; color:white; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-size:15px; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { padding:10px; border:1px solid #ddd; text-align:left; }
    th { background:#2563eb; color:white; }
    tr:nth-child(even) { background:#f9fafb; }
    .delete-btn { background:#dc2626; color:white; padding:6px 10px; border-radius:5px; text-decoration:none; }
    .msg { text-align:center; background:#f3f4f6; padding:10px; border-radius:6px; margin-bottom:15px; color:#111827; font-weight:500; }
    .pagination a { margin:0 5px; text-decoration:none; color:#2563eb; padding:8px 12px; border-radius:6px; background:#f3f4f6; }
    .pagination a:hover { background:#2563eb; color:white; }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="logo"><img src="css/Quiz Campus  logo.png" alt="Logo"> Quiz Campus - Admin</div>
    <div><a href="logout.php" style="color:white;">Logout</a></div>
  </div>

  <div class="container">
    <div class="sidebar">
      <ul>
                <li>
  <a href="admin_dashboard.php">
    <i class="fa-solid fa-house"></i> Dashboard
  </a>
</li>

<li>
  <a href="admin_users.php">
    <i class="fa-solid fa-users"></i> Manage Users
  </a>
</li>

<li>
  <a href="admin_quizzes.php">
    <i class="fa-solid fa-file-lines"></i> Manage Quizzes
  </a>
</li>

<li>
  <a href="admin_payments.php">
    <i class="fa-solid fa-credit-card"></i> View Payments
  </a>
</li>

<li>
  <a href="admin_reports.php">
    <i class="fa-solid fa-chart-column"></i> Reports
  </a>
</li>

<li>
  <a href="admin_notices.php" class="active">
    <i class="fa-solid fa-bell"></i> Manage Notices
  </a>
</li>

<li>
  <a href="admin_ads.php">
    <i class="fa-solid fa-bullhorn"></i> Ads Manager
  </a>
</li>
      </ul>
    </div>

    <div class="content">
      <h2>
  <i class="fa-solid fa-bell"></i> Manage Announcements & Notices
</h2>


      <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="msg" style="background:#fff5f5;color:#b91c1c;border:1px solid #fca5a5;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- Search Form -->
      <form method="GET" class="search-form">
        <input type="text" name="search" placeholder="Search Notices..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" />
        <button type="submit">Search</button>
      </form>

      <!-- Add New Notice (audience removed; always all) -->
      <form method="POST">
        <input type="text" name="title" placeholder="Notice Title" required>
        <textarea name="description" rows="3" placeholder="Notice Description" required></textarea>

        <!-- Audience removed — notices always saved as 'all' -->
        <div style="color:#6b7280;font-size:14px;">Note: This notice will be shown to <strong>All Users</strong> (students, teachers & admins).</div>

        <button type="submit" name="add_notice">Add Notice</button>
      </form>

      <!-- Display Existing Notices -->
      <table>
        <tr>
          <th>Title</th>
          <th>Description</th>
          <th>Audience</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td><?= audience_label($row['audience']) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td>
              <a href="?delete=<?= intval($row['id']) ?>" class="delete-btn" onclick="return confirmDelete();">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </table>

      <!-- Pagination -->
      <div class="pagination" style="margin-top:12px;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="?page=<?= $i ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>

    </div>
  </div>

  <script>
    // Confirm deletion
    function confirmDelete() {
      return confirm('Are you sure you want to delete this notice?');
    }
  </script>
</body>
</html>

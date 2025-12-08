<?php
session_start();
require 'db.php';

// ✅ Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// ✅ Handle approval action
if (isset($_POST['approve']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    // Update user as premium
    $stmt = $conn->prepare("UPDATE users SET is_premium_user = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $msg = "✅ User marked as premium successfully.";
    } else {
        $msg = "❌ Failed to update user.";
    }
}

// ✅ Fetch all payments
$result = $conn->query("SELECT p.id, p.user_id, p.amount, p.payment_status, p.created_at, 
                               u.name, u.email, u.is_premium_user 
                        FROM payments p 
                        JOIN users u ON p.user_id = u.id 
                        ORDER BY p.created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin - View Payments</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="navbar">
  <div class="logo">
    <img src="css/Quiz Campus  logo.png" alt="Logo">
    Quiz Campus - Admin
  </div>
  <div class="logout"><a href="logout.php">Logout</a></div>
</div>

<div class="container">
  <div class="sidebar">
    <ul>
      <li><a href="admin_dashboard.php">🏠 Dashboard</a></li>
        <li><a href="admin_users.php">👥 Manage Users</a></li>
        <li><a href="admin_quizzes.php">📝 Manage Quizzes</a></li>
        <li><a href="admin_payments.php" class="active">💳 View Payments</a></li>
        <li><a href="admin_reports.php">📊 Reports</a></li>
        <li><a href="admin_notices.php">🔔 Manage Notices</a></li>
         <li><a href="admin_ads.php"> 📢 Ads Manager</a></li> 
    </ul>
  </div>

  <div class="content">
    <h2>💳Payments Overview</h2>

    <?php if (!empty($msg)): ?>
      <p class="msg"><?= $msg ?></p>
    <?php endif; ?>

    <table>
      <tr>
        <th>ID</th>
        <th>User</th>
        <th>Email</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Premium</th>
        <th>Action</th>
        <th>Date</th>
      </tr>

      <?php while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td>$<?= number_format($row['amount'], 2) ?></td>
        <td>
          <?php if ($row['payment_status'] === 'Completed'): ?>
            <span style="color: green;">✔ Completed</span>
          <?php else: ?>
            <span style="color: #dc2626;">Pending</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($row['is_premium_user']): ?>
            <span style="color: green;">✅ Premium</span>
          <?php else: ?>
            ❌ Not Premium
          <?php endif; ?>
        </td>
        <td>
          <?php if ($row['payment_status'] === 'Completed' && !$row['is_premium_user']): ?>
            <button class="btn btn-primary grant-btn" data-user-id="<?= $row['user_id'] ?>">Grant Premium</button>

          <?php else: ?>
            <em>-</em>
          <?php endif; ?>
        </td>
        <td><?= date("Y-m-d H:i", strtotime($row['created_at'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>
<script>
document.querySelectorAll(".grant-btn").forEach(button => {
  button.addEventListener("click", function() {
    const userId = this.getAttribute("data-user-id");
    const row = this.closest("tr");

    fetch("update_premium.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "user_id=" + userId
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === "success") {
        row.querySelector("td:nth-child(6)").innerHTML = "<span style='color:green;'>✅ Premium</span>";
        this.outerHTML = "<em>Updated</em>";
        alert("✅ " + data.message);
      } else {
        alert("❌ " + data.message);
      }
    })
    .catch(err => alert("Error: " + err));
  });
});
</script>

</body>
</html>

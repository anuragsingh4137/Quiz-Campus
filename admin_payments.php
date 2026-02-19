<?php
// admin_payments.php
session_start();
require 'db.php'; // your mysqli $conn

// Admin-only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

// optional message for page (not used with AJAX grants)
$msg = '';

// Fetch payments (latest first). Use prepared stmt for safety.
$sql = "SELECT p.id, p.user_id, p.amount, p.payment_status, p.created_at, u.name, u.email, u.is_premium_user
        FROM payments p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin - View Payments</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    /* small table tweaks */
    table { width:100%; border-collapse: collapse; }
    th, td { padding:12px 10px; border-bottom:1px solid #e8edf6; text-align:left; }
    th { background:#2563eb; color:#fff; font-weight:700; }
    .msg { padding:10px 14px; background:#ecfdf5; border:1px solid #bbf7d0; color:#065f46; border-radius:6px; display:inline-block; margin-bottom:12px; }
    .btn { cursor:pointer; padding:8px 12px; border-radius:8px; border:none; font-weight:700; }
    .btn-primary { background:linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; }
    .muted { color:#6b7280; }
  </style>
</head>
<body>

<div class="navbar">
  <div class="logo">
    <img src="css/Quiz Campus  logo.png" alt="Logo" style="height:34px;">
    Quiz Campus - Admin
  </div>
  <div class="logout"><a href="logout.php">Logout</a></div>
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
  <a href="admin_payments.php" class="active">
    <i class="fa-solid fa-credit-card"></i> View Payments
  </a>
</li>

<li>
  <a href="admin_reports.php">
    <i class="fa-solid fa-chart-column"></i> Reports
  </a>
</li>

<li>
  <a href="admin_notices.php">
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
  <i class="fa-solid fa-credit-card"></i> Payments Overview
</h2>


    <?php if (!empty($msg)): ?>
      <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <table aria-describedby="payments">
      <thead>
      <tr>
        <th style="width:60px">ID</th>
        <th>User</th>
        <th>Email</th>
        <th style="width:140px">Amount (NPR)</th>
        <th style="width:120px">Premium</th>
        <th style="width:160px">Date</th>
      </tr>
      </thead>
      <tbody>
      <?php while ($row = $result->fetch_assoc()): 
            // normalize status
            $status = strtolower(trim($row['payment_status'] ?? ''));
            $isCompleted = ($status === 'completed' || $status === 'success');
            $isPremium = (int)($row['is_premium_user'] ?? 0);

            // amount expected to be stored in paisa (integer). Convert to NPR
            $amount_display = is_numeric($row['amount']) ? 'Rs ' . number_format($row['amount'] / 100, 2) : htmlspecialchars($row['amount']);
      ?>
      <tr data-user-id="<?= (int)$row['user_id'] ?>" data-payment-id="<?= (int)$row['id'] ?>">
        <td><?= (int)$row['id'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= $amount_display ?></td>
  
        <td class="premium-cell">
          <?php if ($isPremium): ?>
            <span style="color:green;font-weight:700;">✅ Premium</span>
          <?php else: ?>
            <span class="muted">❌ Not Premium</span>
          <?php endif; ?>
        </td>
          
        <td><?= date("Y-m-d H:i", strtotime($row['created_at'])) ?></td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
/*
 * Grant premium via AJAX to avoid full page reload.
 * update_premium.php returns JSON { status: "success"|"error", message: "..." }
 */
document.querySelectorAll(".grant-btn").forEach(btn => {
  btn.addEventListener("click", function() {
    const userId = this.getAttribute("data-user-id");
    const paymentId = this.getAttribute("data-payment-id");
    const tr = this.closest("tr");
    if (!confirm("Grant premium to this user?")) return;

    fetch("update_premium.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "user_id=" + encodeURIComponent(userId) + "&payment_id=" + encodeURIComponent(paymentId)
    })
    .then(r => r.json())
    .then(data => {
      if (data && data.status === "success") {
        // update UI
        const premiumCell = tr.querySelector(".premium-cell");
        const actionCell = tr.querySelector(".action-cell");
        if (premiumCell) premiumCell.innerHTML = "<span style='color:green;font-weight:700;'>✅ Premium</span>";
        if (actionCell) actionCell.innerHTML = "<em>Updated</em>";
        alert("✅ " + data.message);
      } else {
        alert("❌ " + (data.message || "Server error"));
      }
    })
    .catch(err => {
      console.error(err);
      alert("Error: " + err);
    });
  });
});

// (Optional) small Verify button — left for future use
document.querySelectorAll(".btn-verify").forEach(b => {
  b.addEventListener("click", function() {
    const paymentId = this.getAttribute("data-payment-id");
    window.open("admin_verify_payment.php?id=" + encodeURIComponent(paymentId), "_blank");
  });
});
</script>

</body>
</html>

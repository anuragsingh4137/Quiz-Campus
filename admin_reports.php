<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch overall stats
$total_users = $conn->query("SELECT COUNT(*) AS count FROM users")->fetch_assoc()['count'];
$total_students = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='student'")->fetch_assoc()['count'];
$total_teachers = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role='teacher'")->fetch_assoc()['count'];
$total_quizzes = $conn->query("SELECT COUNT(*) AS count FROM quizzes")->fetch_assoc()['count'];
$total_attempts = $conn->query("SELECT COUNT(*) AS count FROM quiz_attempts")->fetch_assoc()['count'];
$total_premium = $conn->query("SELECT COUNT(*) AS count FROM users WHERE is_premium_user = 1")->fetch_assoc()['count'];

// Optional: Total payments (if you have a payments table)
$payment_check = $conn->query("SHOW TABLES LIKE 'payments'");
$total_revenue = 0;
if ($payment_check->num_rows > 0) {
    $total_revenue = $conn->query("SELECT SUM(amount) AS total FROM payments")->fetch_assoc()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Reports - Quiz Campus</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .report-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 30px;
    }
    .report-card {
      flex: 1;
      min-width: 220px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 3px 8px rgba(0,0,0,0.05);
      transition: transform 0.2s;
    }
    .report-card:hover {
      transform: translateY(-4px);
    }
    .report-card h3 {
      margin: 0;
      color: #2563eb;
      font-size: 20px;
    }
    .report-card p {
      margin: 8px 0 0;
      font-size: 16px;
      color: #374151;
    }
    .chart-container {
      background: #fff;
      padding: 25px;
      margin-top: 30px;
      border-radius: 12px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    }
    canvas {
      max-height: 380px;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Admin
    </div>
    <div class="logout"><a href="logout.php">Logout</a></div>
  </div>

  <!-- Layout -->
  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <ul>
        <li><a href="admin_dashboard.php">🏠 Dashboard</a></li>
        <li><a href="admin_users.php">👥 Manage Users</a></li>
        <li><a href="admin_quizzes.php">📝 Manage Quizzes</a></li>
        <li><a href="admin_payments.php">💳 View Payments</a></li>
        <li><a href="admin_reports.php" class="active">📊 Reports</a></li>
        <li><a href="admin_notices.php">🔔 Manage Notices</a></li>
        <li><a href="admin_ads.php"> 📢 Ads Manager</a></li>
      </ul>
    </div>

    <!-- Content -->
    <div class="content">
      <h2>📊 Admin Reports Dashboard</h2>
      <p>Here’s an overview of your platform activity and performance.</p>

      <div class="report-cards">
        <div class="report-card">
          <h3>👨‍🎓 Students</h3>
          <p><?= $total_students ?></p>
        </div>
        <div class="report-card">
          <h3>👩‍🏫 Teachers</h3>
          <p><?= $total_teachers ?></p>
        </div>
        <div class="report-card">
          <h3>📝 Total Quizzes</h3>
          <p><?= $total_quizzes ?></p>
        </div>
        <div class="report-card">
          <h3>🎯 Quiz Attempts</h3>
          <p><?= $total_attempts ?></p>
        </div>
        <div class="report-card">
          <h3>💎 Premium Users</h3>
          <p><?= $total_premium ?></p>
        </div>
        <?php if ($total_revenue > 0): ?>
        <div class="report-card">
          <h3>💰 Total Revenue</h3>
          <p>₹<?= number_format($total_revenue, 2) ?></p>
        </div>
        <?php endif; ?>
      </div>

      <div class="chart-container">
        <h3>📈 User & Quiz Statistics</h3>
        <canvas id="statsChart"></canvas>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('statsChart');

const data = {
  labels: ['Students', 'Teachers', 'Quizzes', 'Attempts', 'Premium Users'],
  datasets: [
    {
      label: 'Students',
      data: [<?= $total_students ?>, 0, 0, 0, 0],
      backgroundColor: '#3b82f6'
    },
    {
      label: 'Teachers',
      data: [0, <?= $total_teachers ?>, 0, 0, 0],
      backgroundColor: '#10b981'
    },
    {
      label: 'Quizzes',
      data: [0, 0, <?= $total_quizzes ?>, 0, 0],
      backgroundColor: '#f59e0b'
    },
    {
      label: 'Attempts',
      data: [0, 0, 0, <?= $total_attempts ?>, 0],
      backgroundColor: '#ef4444'
    },
    {
      label: 'Premium Users',
      data: [0, 0, 0, 0, <?= $total_premium ?>],
      backgroundColor: '#8b5cf6'
    }
  ]
};

new Chart(ctx, {
  type: 'bar',
  data: data,
  options: {
    responsive: true,
    plugins: {
      legend: {
        position: 'top',
        labels: { color: '#111', font: { size: 14 } }
      },
      title: {
        display: true,
        text: '📊 Platform Statistics',
        font: { size: 18, weight: 'bold' }
      }
    },
    scales: {
      y: {
        beginAtZero: true
      }
    }
  }
});
</script>

</body>
</html>

<?php
// payment_success.php — matches student_dashboard sidebar & header, Premium highlighted
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// fetch user info for display (name/email/premium)
$user_query = $conn->query("SELECT name, email, education_level, gender, is_premium_user, premium_expiry FROM users WHERE id = $user_id");
$user = $user_query->fetch_assoc();

$is_premium = (int) ($user['is_premium_user'] ?? 0);
$premium_expiry = $user['premium_expiry'] ?? null;
$expiry_display = '';
if ($is_premium) {
    $expiry_display = empty($premium_expiry) ? 'Lifetime' : date('j M Y, H:i', strtotime($premium_expiry));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Success — Quiz Campus</title>
  <link rel="stylesheet" href="css/style.css">
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    /* Page tweaks to match dashboard and ensure full-pill active highlight */
    /* Increase sidebar width so long labels fit on one line */
    .sidebar { width: 260px; min-width: 260px; box-sizing: border-box; }

    /* Make the whole menu item highlight as a pill (full background + shadow) */
    .sidebar ul { list-style:none; padding:0; margin:0; }
    .sidebar ul li { margin: 10px 0; }
    .sidebar ul li a {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 10px;
      text-decoration: none;
      color: #0f172a;
      transition: background .12s ease, box-shadow .12s ease;
    }
    .sidebar ul li a .icon { width:22px; text-align:center; }

    /* active full pill */
    .sidebar ul li a.active {
      background: linear-gradient(90deg, rgba(37,99,235,0.12), rgba(37,99,235,0.03));
      color: var(--accent, #2563eb);
      font-weight: 800;
      box-shadow: 0 6px 14px rgba(37,99,235,0.06);
    }

    /* small responsive fix */
    @media (max-width: 880px) {
      .sidebar { width: 220px; min-width: 220px; }
    }

    /* Card spacing to match dashboard */
    .page { padding: 28px 36px; max-width: 1200px; margin: 0 auto; }
    .card { background:#fff;border-radius:12px;padding:26px;box-shadow:0 14px 36px rgba(2,6,23,0.06);border:1px solid rgba(2,6,23,0.04);display:flex;gap:20px;align-items:center; }
    .badge { width:72px;height:72px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:32px;background:linear-gradient(90deg,#10b981,#059669);color:#fff; }
    .pill { background:#f1f5f9;padding:8px 12px;border-radius:999px;color:#0f172a;font-weight:600;border:1px solid rgba(2,6,23,0.04); }
    .actions a { display:inline-block; padding:10px 16px; border-radius:10px; font-weight:700; text-decoration:none; }
    .actions a.primary { background:linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; }
    .actions a.ghost { background:#fff; border:1px solid #e6eefc; color:#0f172a; }
  </style>
</head>
<body>
  <!-- Navbar (same structure as dashboard) -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Student
    </div>
    <div class="logout">
      <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
    </div>
  </div>




    <!-- Main Content -->
    <div class="content page">
      <div class="title" style="font-size:24px;font-weight:800;margin-bottom:10px;">Payment Complete</div>

      <div class="card" role="region" aria-labelledby="success-heading">
        <div class="badge">✓</div>

        <div style="flex:1;">
          <h2 id="success-heading" style="margin:0 0 6px;font-size:22px;font-weight:800;">Thank you, <?php echo htmlspecialchars($user['name'] ?? 'Student'); ?></h2>
          <div style="color:#6b7280;margin:0 0 12px;">Your premium access is active.</div>

          <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap;">
            <div class="pill">Status: <strong style="margin-left:8px;"><?php echo $is_premium ? 'Premium Active' : 'Not Premium'; ?></strong></div>
            <?php if ($is_premium): ?>
              <div class="pill">Expires: <?php echo htmlspecialchars($expiry_display); ?></div>
            <?php endif; ?>
          </div>

          <div class="actions" style="margin-top:18px;">
            <a class="primary" href="student_dashboard.php">Go to dashboard</a>
            <a class="ghost" href="student_profile.php">View profile</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</body>
</html>

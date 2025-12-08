<?php
// admin_ads.php
session_start();
require 'db.php'; // uses the same $conn

// ------------------------- helpers -------------------------
function flash($key, $val = null) {
  if ($val !== null) { $_SESSION[$key] = $val; return; }
  if (!empty($_SESSION[$key])) { $v = $_SESSION[$key]; unset($_SESSION[$key]); return $v; }
  return null;
}
function ensure_dir($path) {
  if (!is_dir($path)) { @mkdir($path, 0775, true); }
}

// ------------------------- config -------------------------
$uploadDirFs = __DIR__ . '/uploads/ads/';   // filesystem
$uploadDirUrl = 'uploads/ads/';             // url (relative)
ensure_dir($uploadDirFs);

// ------------------------- create/save -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ad'])) {
  $title      = trim($_POST['title'] ?? '');
  $caption    = trim($_POST['caption'] ?? '');
  $sort_order = (int)($_POST['sort_order'] ?? 0);
  $is_active  = isset($_POST['is_active']) ? 1 : 0;

  // basic validation
  if ($title === '') {
    flash('error', 'Title is required.');
    header('Location: admin_ads.php'); exit;
  }
  if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    flash('error', 'Image is required.');
    header('Location: admin_ads.php'); exit;
  }

  // file checks
  $f = $_FILES['image'];
  if ($f['size'] > 2 * 1024 * 1024) { // 2MB
    flash('error', 'Image too large. Max 2MB.');
    header('Location: admin_ads.php'); exit;
  }
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg','jpeg','png','webp'];
  if (!in_array($ext, $allowed, true)) {
    flash('error', 'Invalid image type. Use JPG/PNG/WEBP.');
    header('Location: admin_ads.php'); exit;
  }

  // move
  $newName = 'ad_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $destFs  = $uploadDirFs . $newName;
  $destUrl = $uploadDirUrl . $newName;

  if (!move_uploaded_file($f['tmp_name'], $destFs)) {
    flash('error', 'Failed to store the image.');
    header('Location: admin_ads.php'); exit;
  }

  // insert
  $stmt = $conn->prepare(
    "INSERT INTO ads (title, caption, image, is_active, sort_order, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())"
  );
  $stmt->bind_param('sssii', $title, $caption, $destUrl, $is_active, $sort_order);
  if ($stmt->execute()) {
    flash('success', 'Ad saved.');
  } else {
    // cleanup file if DB insert failed
    @unlink($destFs);
    flash('error', 'Database error while saving ad.');
  }
  $stmt->close();
  header('Location: admin_ads.php'); exit;
}

// ------------------------- activate/deactivate -------------------------
if (isset($_GET['activate'])) {
  $id = (int)$_GET['activate'];
  $conn->query("UPDATE ads SET is_active = 1 WHERE id = {$id} LIMIT 1");
  flash('success', 'Ad activated.');
  header('Location: admin_ads.php'); exit;
}
if (isset($_GET['deactivate'])) {
  $id = (int)$_GET['deactivate'];
  $conn->query("UPDATE ads SET is_active = 0 WHERE id = {$id} LIMIT 1");
  flash('success', 'Ad deactivated.');
  header('Location: admin_ads.php'); exit;
}

// ------------------------- delete -------------------------
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  // find file path first
  $res = $conn->query("SELECT image FROM ads WHERE id = {$id} LIMIT 1");
  if ($res && $row = $res->fetch_assoc()) {
    $imageUrl = $row['image']; // e.g. uploads/ads/xxx.webp
    $fsPath = __DIR__ . '/' . $imageUrl;
    $conn->query("DELETE FROM ads WHERE id = {$id} LIMIT 1");
    if (is_file($fsPath)) { @unlink($fsPath); }
    flash('success', 'Ad deleted.');
  } else {
    flash('error', 'Ad not found.');
  }
  header('Location: admin_ads.php'); exit;
}

// ------------------------- list -------------------------
$ads = [];
$res = $conn->query("SELECT id, title, caption, image, sort_order, is_active FROM ads ORDER BY sort_order, id DESC");
if ($res) { $ads = $res->fetch_all(MYSQLI_ASSOC); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Ads Manager | Quiz Campus</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .page-wrap { padding: 20px; }
    .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.04); padding:20px; }
    .card h3 { margin:0 0 12px 0; font-size:20px; color:#111827; }
    .subtle { color:#6b7280; font-size:14px; }
    .grid-2 { display:grid; grid-template-columns: 1.2fr .8fr; gap:18px; }
    @media (max-width: 980px){ .grid-2{ grid-template-columns:1fr; } }
    .form-group { margin-bottom:12px; text-align:left; }
    .form-group label { display:block; font-weight:600; margin-bottom:6px; }
    .form-inline { display:flex; align-items:center; gap:12px; }
    .muted-box { background:#f8fafc; border:1px dashed #d1d5db; border-radius:12px; padding:16px; color:#374151; }
    .table-actions { display:flex; gap:8px; }
    .img-preview { width:140px; height:70px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb; }
    .status-pill { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:600; }
    .status-on { background:#dcfce7; color:#166534; }
    .status-off{ background:#fee2e2; color:#991b1b; }
    .toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
    .toolbar h2 { margin:0; font-size:22px; color:#1f2937; }
    .alert { padding:10px 12px; border-radius:8px; margin-bottom:12px; font-size:14px; }
    .alert-ok { background:#ecfdf5; color:#065f46; border:1px solid #34d399; }
    .alert-err{ background:#fef2f2; color:#991b1b; border:1px solid #fca5a5; }
  </style>
</head>
<body>

  <!-- top bar from your app -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="logo">
      <span>Quiz Campus — Admin</span>
    </div>
    <div class="logout">
  <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

  </div>

  <div class="container">
    <!-- left sidebar from your app -->
    <aside class="sidebar">
      <ul>
        <li><a href="admin_dashboard.php">🏠 Dashboard</a></li>
        <li><a href="admin_users.php">👥 Manage Users</a></li>
        <li><a href="admin_quizzes.php">📝 Manage Quizzes</a></li>
        <li><a href="admin_payments.php">💳 View Payments</a></li>
        <li><a href="admin_reports.php">📊 Reports</a></li>
        <li><a href="admin_notices.php">🔔 Manage Notices</a></li>
        <li><a href="admin_ads.php" class="active">📢 Ads Manager</a></li>
      </ul>
    </aside>

    <!-- main -->
    <main class="content">
      <div class="toolbar"><h2>Ads Manager</h2></div>

      <?php if ($m = flash('success')): ?>
        <div class="alert alert-ok"><?= htmlspecialchars($m) ?></div>
      <?php endif; ?>
      <?php if ($m = flash('error')): ?>
        <div class="alert alert-err"><?= htmlspecialchars($m) ?></div>
      <?php endif; ?>

      <div class="grid-2">
        <!-- create -->
        <section class="card">
          <h3>Create New Ad</h3>
          <form method="post" enctype="multipart/form-data">
            <div class="form-group">
              <label for="title">Title</label>
              <input id="title" name="title" type="text" placeholder="e.g., School Admission 2025/26" required>
            </div>

            <div class="form-group">
              <label for="caption">Caption</label>
              <input id="caption" name="caption" type="text" placeholder="Short line under the title">
            </div>

            <div class="form-group">
              <label for="image">Image (JPG/PNG/WEBP, max 2MB, ~1920×500 best)</label>
              <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp" required>
            </div>

            <div class="form-inline">
              <div class="form-group" style="flex:1;">
                <label for="sort_order">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" value="0">
              </div>
              <div class="form-group">
                <label class="checkbox-label" style="margin-top:24px;">
                  <input type="checkbox" name="is_active" value="1" checked>
                  <span>Active</span>
                </label>
              </div>
            </div>

            <button class="btn btn-primary" type="submit" name="save_ad">Save Ad</button>
          </form>
        </section>

        <!-- tips -->
        <aside class="card">
          <h3>Tips</h3>
          <div class="muted-box">
            <ul style="margin:0 0 10px 18px;">
              <li>Use wide images. Suggested ratio ≈ <strong>1920×500</strong>.</li>
              <li>Keep key content near the center to avoid cropping.</li>
              <li>Use clear titles and concise captions.</li>
            </ul>
            <p class="subtle">Supported: JPG, PNG, WEBP. Max size 2MB.</p>
          </div>
        </aside>
      </div>

      <!-- list -->
      <section class="card" style="margin-top:18px;">
        <h3>All Ads</h3>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:60px;">ID</th>
                <th>Preview</th>
                <th>Title / Caption</th>
                <th style="width:90px;">Order</th>
                <th style="width:110px;">Status</th>
                <th style="width:180px;">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($ads)): ?>
              <tr><td colspan="6">No ads found.</td></tr>
            <?php else: foreach ($ads as $ad): ?>
              <tr>
                <td><?= (int)$ad['id'] ?></td>
                <td>
                  <?php if (!empty($ad['image'])): ?>
                    <img class="img-preview" src="<?= htmlspecialchars($ad['image']) ?>" alt="ad">
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= htmlspecialchars($ad['title']) ?></strong><br>
                  <span class="subtle"><?= htmlspecialchars($ad['caption']) ?></span>
                </td>
                <td><?= (int)$ad['sort_order'] ?></td>
                <td>
                  <?php if ((int)$ad['is_active'] === 1): ?>
                    <span class="status-pill status-on">Active</span>
                  <?php else: ?>
                    <span class="status-pill status-off">Hidden</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="table-actions">
                    <?php if ((int)$ad['is_active'] === 1): ?>
                      <a class="btn btn-secondary" href="admin_ads.php?deactivate=<?= (int)$ad['id'] ?>">Deactivate</a>
                    <?php else: ?>
                      <a class="btn btn-primary" href="admin_ads.php?activate=<?= (int)$ad['id'] ?>">Activate</a>
                    <?php endif; ?>
                    <a class="btn btn-danger" href="admin_ads.php?delete=<?= (int)$ad['id'] ?>" onclick="return confirm('Delete this ad?');">Delete</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>

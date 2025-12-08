<?php
require 'config.php';
session_start();

$token = $_GET['token'] ?? '';
$message = '';
$valid = false;

// Check token validity
if ($token) {
    $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $expiry_time = strtotime($user['reset_token_expiry']);
        if ($expiry_time > time()) {
            $valid = true;
            $user_id = $user['id'];
        } else {
            $message = "Token expired. Please request a new password reset.";
        }
    } else {
        $message = "Invalid token.";
    }
}

// Handle new password submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password']) && isset($_POST['user_id'])) {
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_id = (int) $_POST['user_id'];

    $update = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_token_expiry=NULL WHERE id=?");
    $update->bind_param("si", $new_password, $user_id);
    $update->execute();

    $message = "✅ Password has been reset successfully. <a href='login.php'>Login Now</a>";
    $valid = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reset Password</title>
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body>
  <div class="auth-header">
    <img src="css/Quiz Campus  logo.png" alt="Quiz Campus" />
    <h1>Quiz Campus</h1>
  </div>

  <div class="auth-card">
    <h2>Reset Password</h2>

    <?php if (!empty($message)): ?>
      <div class="msg"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($valid): ?>
      <form method="POST">
        <input type="hidden" name="user_id" value="<?= $user_id ?>" />
        <input type="password" name="password" placeholder="Enter new password" required />
        <button type="submit">Reset Password</button>
      </form>
    <?php endif; ?>

    <div class="auth-alt">
      <a href="login.php">← Back to Login</a>
    </div>
  </div>
</body>
</html>

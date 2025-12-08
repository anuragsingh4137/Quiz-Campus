<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password']) && $user['role'] === $role) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            if ($role === 'student') {
                header("Location: student_dashboard.php");
            } elseif ($role === 'teacher') {
                header("Location: teacher_dashboard.php");
            } else {
                header("Location: admin_dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid email, password, or role.";
        }
    } else {
        $error = "No user found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login | Quiz Campus</title>
  <link rel="stylesheet" href="css/auth.css"/> 
</head>
<body>
  <div class="auth-header">
    <img src="css/Quiz Campus  logo.png" alt="Quiz Campus" />
    <h1>Quiz Campus</h1>
  </div>

  <div class="auth-card">
    <h2>Login</h2>

    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="email" name="email" placeholder="Email" required />
      <input type="password" name="password" placeholder="Password" required />
      <select name="role" required>
        <option value="student">Student</option>
        <option value="teacher">Teacher</option>
        <option value="admin">Admin</option>
      </select>
      <button type="submit">Login</button>
    </form>

    <div class="auth-alt">
      <a href="register.php">Register</a> | 
      <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
    </div>
  </div>
</body>
</html>

<?php
// forget_password.php
require 'config.php';   // has DB connection + SMTP constants
session_start();

// autoload PHPMailer (installed via composer)
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";

/**
 * Send password reset link via PHPMailer
 * Returns true on success, false on error.
 */
function send_reset_email($to_email, $reset_link) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;

        // Choose encryption (STARTTLS for 587, SMTPS for 465)
        if (defined('SMTP_PORT') && SMTP_PORT == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = "Quiz Campus — Password reset request";
        $mail->Body = "
            <p>Hello,</p>
            <p>We received a request to reset your Quiz Campus password. Click the link below to reset. This link is valid for 1 hour.</p>
            <p><a href='" . htmlspecialchars($reset_link, ENT_QUOTES) . "'>Reset your password</a></p>
            <p>If you didn't request this, you can safely ignore this message.</p>
            <p>Regards,<br>Quiz Campus Team</p>
        ";
        $mail->AltBody = "Reset your password: " . $reset_link;

        // Optional: reduce timeouts so sending doesn't block too long
        $mail->Timeout = 15; // seconds

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error for debugging (do not show to user)
        error_log("Reset email failed to {$to_email}: " . $mail->ErrorInfo);
        return false;
    }
}

// DEV fallback logger: write link to a local file for debugging (remove in production)
function dev_log_reset($email, $reset_link) {
    $entry = sprintf("[%s] %s -> %s\n", date('Y-m-d H:i:s'), $email, $reset_link);
    file_put_contents(__DIR__ . '/reset_dev.log', $entry, FILE_APPEND | LOCK_EX);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // normalize email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        // NOTE: To avoid leaking whether an email is registered, you can always show
        // a generic message like "If an account exists we'll send a link".
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            // create secure token
            $token = bin2hex(random_bytes(32)); // 64 hex chars
            $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Save token and expiry in DB
            $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $update->bind_param("ssi", $token, $expiry, $user['id']);
            $update->execute();
            $update->close();

            // Build reset link (adjust host/path as needed)
            $reset_link = "http://localhost/quiz-campus/reset_password.php?token=" . $token;

            // Send email via PHPMailer
            $sent = send_reset_email($email, $reset_link);
            if ($sent) {
                $message = "If an account exists for that email, a reset link has been sent.";
            } else {
                // dev fallback: log link to file so you can copy it during testing
                dev_log_reset($email, $reset_link);
                $message = "Failed to send email. For local testing the link has been saved to reset_dev.log.";
            }

        } else {
            // Show the same message even if email not found (prevents account enumeration)
            $message = "If an account exists for that email, a reset link has been sent.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Forgot Password</title>
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body>
  <div class="auth-header">
    <img src="css/Quiz Campus  logo.png" alt="Quiz Campus" />
    <h1>Quiz Campus</h1>
  </div>

  <div class="auth-card">
    <h2>Forgot Password</h2>

    <?php if (!empty($message)): ?>
      <div class="msg"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="email" name="email" placeholder="Enter your registered email" required />
      <button type="submit">Send Reset Link</button>
    </form>

    <div class="auth-alt">
      <a href="login.php">← Back to Login</a>
    </div>
  </div>
</body>
</html>

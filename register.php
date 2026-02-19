<?php
session_start();
require 'config.php'; // must provide $conn (MySQLi) and SMTP constants

// autoload PHPMailer (installed via composer)
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$message = '';

// OTP settings
define('OTP_LENGTH', 6);
define('OTP_EXPIRE_SECONDS', 10 * 60); // 10 minutes
define('OTP_MAX_ATTEMPTS', 5);
define('OTP_RESEND_COOLDOWN', 30); // seconds between resends

// helper: generate numeric OTP
function generate_otp($len = OTP_LENGTH) {
    $otp = '';
    for ($i = 0; $i < $len; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

// DEV only: log OTP to a file (remove in production)
function dev_log_otp($email, $otp) {
    $entry = sprintf("[%s] %s -> %s\n", date('Y-m-d H:i:s'), $email, $otp);
    file_put_contents(__DIR__ . '/otp_dev.log', $entry, FILE_APPEND | LOCK_EX);
}

// send OTP using PHPMailer via SMTP (returns true/false)
function send_otp_email($to_email, $to_name, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;

        // choose encryption based on port
        if (defined('SMTP_PORT') && SMTP_PORT == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port       = SMTP_PORT;

       $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
$mail->addAddress($to_email, $to_name);
$mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

// ✅ Enable HTML email
$mail->isHTML(true);
$mail->Subject = "Quiz Campus:Your Verification Code";

// ✅ HTML Email Body
$mail->Body = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Quiz Campus OTP</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center" style="padding:30px;">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:30px;">
          
          <tr>
            <td align="center" style="padding-bottom:15px;">
              <h2 style="margin:0; color:#2c3e50;">Quiz Campus</h2>
            </td>
          </tr>

          <tr>
            <td style="color:#333333; font-size:15px; line-height:1.6;">
              <p>Hello <strong>'.htmlspecialchars($to_name ?: 'User').'</strong>,</p>

              <p>Your verification code is:</p>

              <p style="font-size:26px; font-weight:bold; letter-spacing:5px; text-align:center; color:#111;">
                '.$otp.'
              </p>

              <p>
                This code will expire in <strong>'.(OTP_EXPIRE_SECONDS/60).' minutes</strong>.
              </p>

              <p style="font-size:13px; color:#666;">
                If you did not request this, please ignore this email.
              </p>

              <p style="margin-top:25px;">
                Regards,<br>
                <strong>Quiz Campus Team</strong>
              </p>
            </td>
          </tr>
    </tr>
  </table>
</body>
</html>
';

// ✅ Plain-text fallback (important)
$mail->AltBody =
"Hello ".($to_name ?: 'User').",

Your verification code is: $otp
This code will expire in ".(OTP_EXPIRE_SECONDS/60)." minutes.

If you did not request this, please ignore.

Regards,
Quiz Campus Team";
 

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log detailed error for debugging (check your PHP/Apache error logs)
        error_log("OTP email failed to {$to_email}: " . $mail->ErrorInfo);
        return false;
    }
}

// Blocked/disposable domains
$blocked_domains = [
    'mailinator.com','10minutemail.com','tempmail.com','guerrillamail.com','yopmail.com',
    'trashmail.com','temporary-mail.net','dispostable.com'
];

// ---------- HANDLE RESEND OTP ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    if (empty($_SESSION['reg_pending']) || empty($_SESSION['otp'])) {
        $error = "No pending registration to resend OTP for.";
    } else {
        $last_sent = $_SESSION['otp']['sent_at'] ?? 0;
        if (time() - $last_sent < OTP_RESEND_COOLDOWN) {
            $error = "Please wait before requesting another code.";
        } else {
            $otp = generate_otp();
            $_SESSION['otp'] = [
                'code' => $otp,
                'expires_at' => time() + OTP_EXPIRE_SECONDS,
                'attempts' => 0,
                'sent_at' => time()
            ];
            $to = $_SESSION['reg_pending']['email'];
            $name = $_SESSION['reg_pending']['name'] ?? '';
            $sent = send_otp_email($to, $name, $otp);
            if ($sent) {
                $message = "OTP resent. Check your email.";
            } else {
                dev_log_otp($to, $otp); // dev fallback
                $message = "Failed to send email. OTP saved to otp_dev.log for debugging.";
            }
        }
    }
}

// ---------- HANDLE VERIFY OTP ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $provided = trim($_POST['otp'] ?? '');
    if (empty($_SESSION['reg_pending']) || empty($_SESSION['otp'])) {
        $error = "No pending registration. Please register first.";
    } else {
        $otpData = &$_SESSION['otp'];
        if (time() > ($otpData['expires_at'] ?? 0)) {
            // clear pending to force re-register
            unset($_SESSION['reg_pending'], $_SESSION['otp']);
            $error = "OTP expired. Please register again.";
        } elseif (($otpData['attempts'] ?? 0) >= OTP_MAX_ATTEMPTS) {
            unset($_SESSION['reg_pending'], $_SESSION['otp']);
            $error = "Maximum OTP attempts exceeded. Please register again.";
        } elseif ($provided !== ($otpData['code'] ?? '')) {
            $otpData['attempts'] = ($otpData['attempts'] ?? 0) + 1;
            $remaining = OTP_MAX_ATTEMPTS - $otpData['attempts'];
            $error = "Incorrect code. Attempts left: $remaining";
        } else {
            // OTP correct -> insert user into DB
            $data = $_SESSION['reg_pending'];

            // final server side validation (again)
            $stmtCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->bind_param("s", $data['email']);
            $stmtCheck->execute();
            $stmtCheck->store_result();
            if ($stmtCheck->num_rows > 0) {
                $error = "Email already registered.";
                // cleanup pending
                unset($_SESSION['reg_pending'], $_SESSION['otp']);
            } else {
                $hashed = $data['password_hash'];
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, dob, gender, country_code, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $data['name'], $data['email'], $hashed, $data['role'], $data['dob'], $data['gender'], $data['country_code'], $data['phone']);
                if ($stmt->execute()) {
                    // success
                    unset($_SESSION['reg_pending'], $_SESSION['otp']);
                    session_regenerate_id(true);
                    header("Location: login.php?registered=success");
                    exit();
                } else {
                    $error = "Failed to create account. DB error: " . $stmt->error;
                }
            }
            $stmtCheck->close();
        }
    }
}

// ---------- HANDLE INITIAL REGISTRATION FORM SUBMISSION (create pending + send OTP) ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['register_submit'])) {
    $name           = trim($_POST['name']           ?? '');
    $email          = trim($_POST['email']          ?? '');
    $password       = trim($_POST['password']       ?? '');
    $confirm_pass   = trim($_POST['confirm_password'] ?? '');
    $role           = trim($_POST['role']           ?? '');
    $dob            = trim($_POST['dob']            ?? '');
    $gender         = trim($_POST['gender']         ?? '');
    $country_code   = trim($_POST['country_code']   ?? '');
    $phone          = trim($_POST['phone']          ?? '');

    $errors = [];

    // basic required
    if ($name === '' || $email === '' || $password === '' || $confirm_pass === '' ||
        $dob === '' || $gender === '' || $phone === '' || $role === '') {
        $errors[] = "All fields are required.";
    }

    // name letters+spaces only
    if ($name !== '' && !preg_match('/^[A-Za-z\s]+$/', $name)) {
        $errors[] = "Name should contain letters and spaces only.";
    }

    // password rules
    if ($password !== '') {
        if (strlen($password) < 6 || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must be at least 6 characters long and include at least one number and one symbol.";
        }
    }

    // confirm
    if ($password !== '' && $confirm_pass !== '' && $password !== $confirm_pass) {
        $errors[] = "Password and Confirm Password do not match.";
    }

    // dob not future
    if ($dob !== '') {
        $today = date('Y-m-d');
        if ($dob > $today) $errors[] = "Date of Birth cannot be in the future.";
    }

    // phone digits only min 10
    if ($phone !== '' && !preg_match('/^[0-9]{10,}$/', $phone)) {
        $errors[] = "Phone number must be at least 10 digits and contain digits only.";
    }

    // email format + blocked domains
    if ($email !== '' && strpos($email, '@') !== false) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        } else {
            $parts = explode('@', $email);
            $domain = strtolower(end($parts));
            if (in_array($domain, $blocked_domains, true)) {
                $errors[] = "Temporary/disposable email domains are not allowed. Please use a valid email address.";
            }
        }
    }

    // check email not already registered
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $errors[] = "Email already registered.";
        }
        $check->close();
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        // store pending registration in session (store password hash, not plain)
        $_SESSION['reg_pending'] = [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'dob' => $dob,
            'gender' => $gender,
            'country_code' => $country_code,
            'phone' => $phone
        ];
        // create OTP
        $otp = generate_otp();
        $_SESSION['otp'] = [
            'code' => $otp,
            'expires_at' => time() + OTP_EXPIRE_SECONDS,
            'attempts' => 0,
            'sent_at' => time()
        ];
        // send email
        $sent = send_otp_email($email, $name, $otp);
        if ($sent) {
            $message = "Verification code sent to your email. Please enter it below.";
        } else {
            // fallback: log for dev and inform user
            dev_log_otp($email, $otp);
            $message = "Failed to send verification email. OTP saved in otp_dev.log for debugging.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register | Quiz Campus</title>
  <link rel="stylesheet" href="css/auth.css">
  <style>
    /* small inline styles for visual hints */
    .hint { font-size: 12px; margin-top: 4px; }
    .hint.valid { color:#16a34a; }
    .hint.invalid { color:#dc2626; }
    .input-valid { border-color:#16a34a !important; }
    .input-invalid { border-color:#dc2626 !important; }
    .otp-box { max-width:420px; margin: 10px 0; padding: 14px; background:#f8fafc; border-radius:8px; border:1px solid #e6edf3; }
    .msg { background:#eef; padding:10px; border-radius:6px; margin-bottom:12px; }
    .error { background:#fee2e2; padding:10px; border-radius:6px; margin-bottom:12px; color:#7f1d1d; }
  </style>
</head>
<body>
  <div class="auth-header">
    <img src="css/Quiz Campus  logo.png" alt="Quiz Campus">
    <h1>Quiz Campus</h1>
  </div>

  <div class="auth-card">
    <?php if (!empty($error)): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
      <div class="msg"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['reg_pending']) && !empty($_SESSION['otp'])): ?>
      <!-- OTP verification screen -->
      <h2>Email verification</h2>
      <p>Enter the 6-digit code sent to <strong><?= htmlspecialchars($_SESSION['reg_pending']['email']) ?></strong></p>
      <div class="otp-box">
        <form method="post" action="">
          <label for="otp">Verification Code</label><br>
          <input type="text" name="otp" id="otp" pattern="\d{6}" inputmode="numeric" maxlength="6" required style="padding:10px;width:100%;box-sizing:border-box;margin-top:6px;">
          <div style="margin-top:8px;display:flex;gap:8px;">
            <button type="submit" name="verify_otp" class="btn">Verify Code</button>
            <button type="submit" name="resend_otp" class="btn secondary" onclick="return confirm('Resend code to <?= htmlspecialchars($_SESSION['reg_pending']['email']) ?>?')">Resend Code</button>
          </div>
        </form>

        <?php
          $expires = $_SESSION['otp']['expires_at'] ?? time();
          $remaining = max(0, $expires - time());
        ?>
        <p style="margin-top:10px;font-size:13px;color:#374151;">Code expires in <span id="countdown"><?= gmdate("i:s", $remaining) ?></span></p>
      </div>

      <script>
        // simple countdown for OTP expiry
        let remaining = <?= $remaining ?>;
        const cd = document.getElementById('countdown');
        if (cd) {
          const t = setInterval(() => {
            if (remaining <= 0) { cd.textContent = "00:00"; clearInterval(t); return; }
            remaining--;
            const mm = Math.floor(remaining/60);
            const ss = remaining % 60;
            cd.textContent = (mm<10?'0':'')+mm+':'+(ss<10?'0':'')+ss;
          }, 1000);
        }
      </script>

    <?php else: ?>
      <!-- Registration form -->
      <h2>Register</h2>
      <form method="POST" action="">
        <input id="name" type="text" name="name" placeholder="Full Name" required>
        <small id="name_hint" class="hint"></small>

        <input id="email" type="email" name="email" placeholder="Email" required>
        <small id="email_hint" class="hint"></small>

        <input id="password" type="password" name="password" placeholder="Password" required>
        <small id="password_hint" class="hint"></small>

        <input id="confirm_password" type="password" name="confirm_password" placeholder="Confirm Password" required>
        <small id="confirm_password_hint" class="hint"></small>

        <div class="form-row-compact">
          <div class="form-field-group">
            <label for="dob">Date of Birth</label>
            <input id="dob" type="date" name="dob" required>
            <small id="dob_hint" class="hint"></small>
          </div>
          <div class="form-field-group">
            <label for="gender">Gender</label>
            <select name="gender" id="gender" required>
              <option value="">-- Select Gender --</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
            <small id="gender_hint" class="hint"></small>
          </div>
        </div>

        <label>Phone Number</label>
        <div class="phone-group">
          <select name="country_code" id="country_code" required>
            <option value="+977">🇳🇵 +977 (Nepal)</option>
            <option value="+91">🇮🇳 +91 (India)</option>
            <option value="+1">🇺🇸 +1 (USA)</option>
            <option value="+44">🇬🇧 +44 (UK)</option>
            <option value="+61">🇦🇺 +61 (Australia)</option>
            <option value="+81">🇯🇵 +81 (Japan)</option>
            <option value="+82">🇰🇷 +82 (Korea)</option>
            <option value="+971">🇦🇪 +971 (UAE)</option>
          </select>
          <input id="phone" type="text" name="phone" placeholder="Phone number" required>
        </div>
        <small id="phone_hint" class="hint"></small>

        <label>Register as</label>
        <select name="role" id="role" required>
          <option value="student">Student</option>
          <option value="teacher">Teacher</option>
         
        </select>

        <div style="margin-top:12px;">
          <button type="submit" name="register_submit" class="btn">Register</button>
        </div>
      </form>

      <div style="margin-top:10px;">
        <a href="login.php">Already have an account? Login</a>
      </div>
    <?php endif; ?>
  </div>

<script>
  // Client-side live validation (same as before)
  function setValid(el, hintEl, msg) {
    el.classList.remove('input-invalid'); el.classList.add('input-valid');
    if (hintEl) { hintEl.textContent = msg || ''; hintEl.classList.remove('invalid'); hintEl.classList.add('valid'); }
  }
  function setInvalid(el, hintEl, msg) {
    el.classList.remove('input-valid'); el.classList.add('input-invalid');
    if (hintEl) { hintEl.textContent = msg || ''; hintEl.classList.remove('valid'); hintEl.classList.add('invalid'); }
  }
  function clearState(el, hintEl) {
    el.classList.remove('input-valid','input-invalid');
    if (hintEl) { hintEl.textContent = ''; hintEl.classList.remove('valid','invalid'); }
  }

  const nameEl = document.getElementById('name');
  const nameHint = document.getElementById('name_hint');
  const emailEl = document.getElementById('email');
  const emailHint = document.getElementById('email_hint');
  const passEl = document.getElementById('password');
  const passHint = document.getElementById('password_hint');
  const confirmEl = document.getElementById('confirm_password');
  const confirmHint = document.getElementById('confirm_password_hint');
  const dobEl = document.getElementById('dob');
  const dobHint = document.getElementById('dob_hint');
  const phoneEl = document.getElementById('phone');
  const phoneHint = document.getElementById('phone_hint');

  const blockedDomains = <?= json_encode(array_values($blocked_domains)) ?>;

  if (nameEl) {
    nameEl.addEventListener('input', function() {
      const v = this.value.trim();
      if (!v) { clearState(this, nameHint); return; }
      if (/^[A-Za-z\s]+$/.test(v)) setValid(this, nameHint, 'Looks good.');
      else setInvalid(this, nameHint, 'Only letters and spaces allowed.');
    });
  }

  if (emailEl) {
    emailEl.addEventListener('input', function() {
      const v = this.value.trim();
      if (!v) { clearState(this, emailHint); return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { setInvalid(this, emailHint, 'Enter a valid email.'); return; }
      const domain = v.split('@').pop().toLowerCase();
      if (blockedDomains.indexOf(domain) !== -1) setInvalid(this, emailHint, 'Disposable email not allowed.');
      else setValid(this, emailHint, 'Email looks good.');
    });
  }

  if (passEl) {
    passEl.addEventListener('input', function() {
      const v = this.value;
      if (!v) { clearState(this, passHint); return; }
      const hasLen = v.length >= 6;
      const hasNum = /[0-9]/.test(v);
      const hasSym = /[^A-Za-z0-9]/.test(v);
      if (hasLen && hasNum && hasSym) setValid(this, passHint, 'Strong password.');
      else setInvalid(this, passHint, 'At least 6 chars, 1 number, 1 symbol.');
      if (confirmEl) confirmEl.dispatchEvent(new Event('input'));
    });
  }

  if (confirmEl) {
    confirmEl.addEventListener('input', function() {
      const v = this.value;
      if (!v) { clearState(this, confirmHint); return; }
      if (v === passEl.value && v.length > 0) setValid(this, confirmHint, 'Passwords match.');
      else setInvalid(this, confirmHint, 'Passwords do not match.');
    });
  }

  if (dobEl) {
    dobEl.addEventListener('change', function() {
      const v = this.value;
      if (!v) { clearState(this, dobHint); return; }
      const today = new Date().toISOString().split('T')[0];
      if (v > today) setInvalid(this, dobHint, 'DOB cannot be in the future.');
      else setValid(this, dobHint, 'DOB is valid.');
    });
    // optionally set max to today
    dobEl.max = new Date().toISOString().split('T')[0];
  }

  if (phoneEl) {
    phoneEl.addEventListener('input', function() {
      this.value = this.value.replace(/\D/g, '');
      const v = this.value;
      if (!v) { clearState(this, phoneHint); return; }
      if (/^[0-9]{10,}$/.test(v)) setValid(this, phoneHint, 'Phone valid.');
      else setInvalid(this, phoneHint, 'At least 10 digits required.');
    });
  }
</script>
</body>
</html>

<?php
session_start();
require 'config.php';

// ✅ Only students can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch existing email from DB so we never change it (email cannot be modified)
$fetchStmt = $conn->prepare("SELECT name, email, is_premium_user, gender, education_level, profile_pic, dob, country_code, phone FROM users WHERE id=?");
$fetchStmt->bind_param("i", $user_id);
$fetchStmt->execute();
$result = $fetchStmt->get_result();
$user = $result->fetch_assoc();
$fetchStmt->close();

$existing_email = $user['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // use trimmed inputs
    $name = trim($_POST['name'] ?? '');
    // NOTE: we ignore $_POST['email'] to prevent email change
    $gender = $_POST['gender'] ?? '';
    $education_level = $_POST['education_level'] ?? '';
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Other fields
    $dob = trim($_POST['dob'] ?? '');
    $country_code = trim($_POST['country_code'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // ----------------------
    // SERVER-SIDE VALIDATION
    // ----------------------

    // Name validation: only letters and spaces
    if ($name === '' || !preg_match("/^[A-Za-z\s]+$/", $name)) {
        $message = "⚠ Name can only contain letters and spaces and cannot be blank.";
    }

    // Phone validation: only digits and 10-15 length
    if (empty($message) && !preg_match("/^[0-9]{10,15}$/", $phone)) {
        $message = "⚠ Phone number must be 10 to 15 digits (numbers only).";
    }

    // DOB validation: cannot be future date
    if (empty($message) && !empty($dob) && strtotime($dob) > time()) {
        $message = "⚠ Date of birth cannot be a future date.";
    }

    // Password validation: only if user provided a new password
    // Rule: at least 6 chars, at least one letter, one digit, one symbol
    if (empty($message) && !empty($password)) {
        $pw_pattern = "/^(?=.*[A-Za-z])(?=.*\d)(?=.*[!@#\$%\^&\*\(\)_\+\-=\[\]\{\};:'\"\\\\|,<>\.\?\/]).{6,}$/";
        if (!preg_match($pw_pattern, $password)) {
            $message = "⚠ Password must be at least 6 characters and include at least one letter, one number, and one symbol.";
        } elseif ($password !== $confirm_password) {
            $message = "⚠ Password and Confirm Password do not match.";
        }
    }

    // Profile picture handling (only if validation passed so far)
    $profile_pic = null;
    if (empty($message) && !empty($_FILES['profile_pic']['name'])) {
        $target_dir = "uploads/profile/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $filename = basename($_FILES["profile_pic"]["name"]);
        $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
        $profile_pic = time() . "_" . $filename;
        $target_file = $target_dir . $profile_pic;

        if (!move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $message = "⚠ Failed to upload profile picture.";
            $profile_pic = null;
        }
    }

    // If validation is OK, perform update
    if (empty($message)) {
        try {
            if (!empty($password)) {
                // update with password
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                if ($profile_pic) {
                    $stmt = $conn->prepare("UPDATE users SET name=?, password=?, gender=?, education_level=?, profile_pic=?, dob=?, country_code=?, phone=? WHERE id=?");
                    $stmt->bind_param("ssssssssi", $name, $hashed, $gender, $education_level, $profile_pic, $dob, $country_code, $phone, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name=?, password=?, gender=?, education_level=?, dob=?, country_code=?, phone=? WHERE id=?");
                    $stmt->bind_param("sssssssi", $name, $hashed, $gender, $education_level, $dob, $country_code, $phone, $user_id);
                }
            } else {
                // update without password (and do NOT update email)
                if ($profile_pic) {
                    $stmt = $conn->prepare("UPDATE users SET name=?, gender=?, education_level=?, profile_pic=?, dob=?, country_code=?, phone=? WHERE id=?");
                    $stmt->bind_param("sssss ssi", $name, $gender, $education_level, $profile_pic, $dob, $country_code, $phone, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name=?, gender=?, education_level=?, dob=?, country_code=?, phone=? WHERE id=?");
                    $stmt->bind_param("ssssssi", $name, $gender, $education_level, $dob, $country_code, $phone, $user_id);
                }
            }

            if ($stmt->execute()) {
                $message = "✅ Profile updated successfully!";
                $_SESSION['name'] = $name;
                // Refresh $user values to reflect updated data (so page shows new info)
                $fetchStmt = $conn->prepare("SELECT name, email, is_premium_user, gender, education_level, profile_pic, dob, country_code, phone FROM users WHERE id=?");
                $fetchStmt->bind_param("i", $user_id);
                $fetchStmt->execute();
                $result = $fetchStmt->get_result();
                $user = $result->fetch_assoc();
                $fetchStmt->close();
            } else {
                $message = "⚠ Error updating profile: " . $stmt->error;
            }
            if (isset($stmt) && $stmt) $stmt->close();
        } catch (Exception $e) {
            $message = "⚠ Exception: " . $e->getMessage();
        }
    }
} // end POST

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Student Profile</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    .profile-container { max-width: 700px; margin: auto; padding: 20px; background: #fff; border-radius:8px; }
    .profile-pic { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; }
    .edit-btn { background: #2563eb; color: #fff; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; }
    .save-btn { background: #10b981; color: #fff; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; }
    .field { margin-bottom: 10px; display:block; }
    .msg { background:#eef; padding:10px; border-radius:6px; margin-bottom:12px; }
    .input-ok { border: 2px solid #16a34a !important; }
    .input-err { border: 2px solid #dc2626 !important; }
    .small-note { font-size:13px;color:#6b7280;margin-top:6px; }
    /* Confirm password hidden by default */
    #confirm_wrap { display:none; margin-top:8px; }
  </style>
</head>
<body>
  <!-- Navbar -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo" style="width:40px;height:40px;">
      Quiz Campus - Student
    </div>
    <div class="logout"><a href="logout.php">Logout</a></div>
  </div>

  <!-- Container -->
  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <ul>
       <li><a href="/quiz-campus/student_dashboard.php">
  <i class="fa-solid fa-house"></i> Dashboard
</a></li>

<li><a href="/quiz-campus/student_quizzes.php">
  <i class="fa-solid fa-file-lines"></i> Available Quizzes
</a></li>

<li><a href="/quiz-campus/student_premium.php">
  <i class="fa-solid fa-star"></i> Premium Mock Tests
</a></li>

<li><a href="/quiz-campus/student_my_results.php">
  <i class="fa-solid fa-chart-column"></i> My Results
</a></li>

<li><a href="/quiz-campus/student_profile.php" class="active">
  <i class="fa-solid fa-user"></i> Profile
</a></li>

      </ul>
    </div>

    <!-- Profile Content -->
    <div class="content">
      <div class="profile-container">
       <h2>
  <i class="fa-solid fa-user"></i> My Profile
</h2>


        <?php if (!empty($message)): ?>
          <p class="msg"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <div style="display:flex;gap:20px;align-items:center;margin-bottom:12px;">
          <div style="flex:0 0 120px;text-align:center;">
            <?php if (!empty($user['profile_pic'])): ?>
              <img src="uploads/profile/<?= htmlspecialchars($user['profile_pic']) ?>" class="profile-pic" alt="profile">
            <?php else: ?>
              <img src="css/default-avatar.png" class="profile-pic" alt="default avatar">
            <?php endif; ?>
          </div>

          <div style="flex:1;">
            <div style="font-size:18px;font-weight:700;color:#0b1220;"><?= htmlspecialchars($user['name'] ?? '') ?></div>
            <div style="color:#6b7280;margin-top:4px;"><?= htmlspecialchars($user['email'] ?? '') ?></div>
          </div>
        </div>

        <form method="post" enctype="multipart/form-data" id="profileForm" novalidate>
          <label class="field">Name:</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" disabled required>

          <label class="field">Email: <small style="color:#6b7280">(email cannot be changed)</small></label>
          <!-- email shown but disabled; server ignores posted email -->
          <input type="email" id="email" name="email_display" value="<?= htmlspecialchars($existing_email) ?>" disabled>

          <label class="field">Gender:</label>
          <select name="gender" id="gender" disabled>
            <option value="">-- Select --</option>
            <option value="Male" <?= isset($user['gender']) && $user['gender']=="Male"?"selected":"" ?>>Male</option>
            <option value="Female" <?= isset($user['gender']) && $user['gender']=="Female"?"selected":"" ?>>Female</option>
            <option value="Other" <?= isset($user['gender']) && $user['gender']=="Other"?"selected":"" ?>>Other</option>
          </select>

          <label class="field">Education Level:</label>
          <select name="education_level" id="education_level" disabled>
            <option value="">-- Select --</option>
            <option value="High School" <?= isset($user['education_level']) && $user['education_level']=="High School"?"selected":"" ?>>High School</option>
            <option value="Bachelor" <?= isset($user['education_level']) && $user['education_level']=="Bachelor"?"selected":"" ?>>Bachelor</option>
            <option value="Master" <?= isset($user['education_level']) && $user['education_level']=="Master"?"selected":"" ?>>Master</option>
            <option value="PhD" <?= isset($user['education_level']) && $user['education_level']=="PhD"?"selected":"" ?>>PhD</option>
          </select>

          <label class="field">Date of Birth:</label>
          <input type="date" id="dob" name="dob" value="<?= htmlspecialchars($user['dob'] ?? '') ?>" disabled>

          <label class="field">Country Code:</label>
          <select name="country_code" id="country_code" disabled>
            <option value="">-- Select Country Code --</option>
            <option value="+977" <?= ($user['country_code']=="+977")?"selected":"" ?>>🇳🇵 Nepal (+977)</option>
            <option value="+91" <?= ($user['country_code']=="+91")?"selected":"" ?>>🇮🇳 India (+91)</option>
            <option value="+1" <?= ($user['country_code']=="+1")?"selected":"" ?>>🇺🇸 United States (+1)</option>
            <option value="+44" <?= ($user['country_code']=="+44")?"selected":"" ?>>🇬🇧 United Kingdom (+44)</option>
            <option value="+61" <?= ($user['country_code']=="+61")?"selected":"" ?>>🇦🇺 Australia (+61)</option>
            <option value="+971" <?= ($user['country_code']=="+971")?"selected":"" ?>>🇦🇪 UAE (+971)</option>
            <option value="+965" <?= ($user['country_code']=="+965")?"selected":"" ?>>🇰🇼 Kuwait (+965)</option>
            <option value="+974" <?= ($user['country_code']=="+974")?"selected":"" ?>>🇶🇦 Qatar (+974)</option>
            <option value="+966" <?= ($user['country_code']=="+966")?"selected":"" ?>>🇸🇦 Saudi Arabia (+966)</option>
            <option value="+880" <?= ($user['country_code']=="+880")?"selected":"" ?>>🇧🇩 Bangladesh (+880)</option>
            <option value="+86" <?= ($user['country_code']=="+86")?"selected":"" ?>>🇨🇳 China (+86)</option>
            <option value="+81" <?= ($user['country_code']=="+81")?"selected":"" ?>>🇯🇵 Japan (+81)</option>
            <option value="+82" <?= ($user['country_code']=="+82")?"selected":"" ?>>🇰🇷 South Korea (+82)</option>
            <option value="+92" <?= ($user['country_code']=="+92")?"selected":"" ?>>🇵🇰 Pakistan (+92)</option>
          </select>

          <label class="field">Phone:</label>
          <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" disabled>

          <label class="field">Profile Picture:</label>
          <input type="file" id="profile_pic" name="profile_pic" disabled>

          <label class="field">Password (leave blank if not changing):</label>
          <input type="password" id="password" name="password" disabled placeholder="Enter new password (optional)">
          <div id="pw_note" class="small-note">Min 6 chars, include 1 letter, 1 number & 1 symbol.</div>

          <div id="confirm_wrap">
            <label class="field">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password">
            <div id="confirm_msg" class="small-note"></div>
          </div>

          <p style="margin-top:14px;"><strong>Premium Status:</strong>
            <?= !empty($user['is_premium_user']) ? "✅ Premium User" : "❌ Free User" ?>
          </p>

          <div style="margin-top:18px;display:flex;gap:10px;">
            <button type="button" class="edit-btn" id="editBtn">Edit</button>
            <button type="submit" class="save-btn" id="saveBtn" style="display:none;">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
document.getElementById("editBtn").addEventListener("click", function() {
    // enable all editable inputs (including password and file input)
    let form = document.getElementById("profileForm").elements;
    for (let i = 0; i < form.length; i++) {
        // keep email always disabled
        if (form[i].name === 'email_display') continue;
        form[i].disabled = false;
    }
    document.getElementById("editBtn").style.display = "none";
    document.getElementById("saveBtn").style.display = "inline-block";
});

// Live frontend validation for password & confirm password
const password = document.getElementById('password');
const confirm_wrap = document.getElementById('confirm_wrap');
const confirm_password = document.getElementById('confirm_password');
const confirm_msg = document.getElementById('confirm_msg');

// show confirm password when password is focused or has value
password.addEventListener('focus', () => { confirm_wrap.style.display = 'block'; });
password.addEventListener('input', () => {
    if (password.value.trim().length > 0) {
        confirm_wrap.style.display = 'block';
    } else {
        confirm_wrap.style.display = 'none';
        confirm_password.value = '';
        confirm_password.classList.remove('input-ok','input-err');
        confirm_msg.textContent = '';
    }
    validatePasswordField();
});

confirm_password.addEventListener('input', validateConfirmField);

// regex: at least 6 chars, one letter, one digit, one symbol
function validatePasswordField() {
    const val = password.value;
    const pattern = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[!@#\$%\^&\*\(\)_\+\-=\[\]\{\};:'"\\|,<>\.\?\/]).{6,}$/;
    if (val.length === 0) {
        password.classList.remove('input-ok','input-err');
        return;
    }
    if (pattern.test(val)) {
        password.classList.add('input-ok');
        password.classList.remove('input-err');
    } else {
        password.classList.add('input-err');
        password.classList.remove('input-ok');
    }
}

function validateConfirmField() {
    const a = password.value;
    const b = confirm_password.value;
    if (b.length === 0) {
        confirm_password.classList.remove('input-ok','input-err');
        confirm_msg.textContent = '';
        return;
    }
    if (a === b) {
        confirm_password.classList.add('input-ok');
        confirm_password.classList.remove('input-err');
        confirm_msg.style.color = '#16a34a';
        confirm_msg.textContent = 'Passwords match';
    } else {
        confirm_password.classList.add('input-err');
        confirm_password.classList.remove('input-ok');
        confirm_msg.style.color = '#dc2626';
        confirm_msg.textContent = 'Passwords do not match';
    }
}

// Optional: simple frontend name validation (no numbers)
const nameInput = document.getElementById('name');
nameInput.addEventListener('input', () => {
    if (/^[A-Za-z\s]*$/.test(nameInput.value)) {
        nameInput.classList.remove('input-err');
    } else {
        nameInput.classList.add('input-err');
    }
});

// Optional: phone validation (digits only) — phone field is disabled normally
const phoneInput = document.getElementById('phone');
phoneInput.addEventListener('input', () => {
    if (/^[0-9]{0,15}$/.test(phoneInput.value)) {
        phoneInput.classList.remove('input-err');
    } else {
        phoneInput.classList.add('input-err');
    }
});
</script>
</body>
</html>

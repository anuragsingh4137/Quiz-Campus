<?php
session_start();
require 'config.php';

// ✅ Only teachers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";

// ✅ Handle form submission (update profile)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $education_level = $_POST['education_level'] ?? '';
    $password = trim($_POST['password'] ?? '');

    // New fields from register
    $dob = trim($_POST['dob'] ?? '');
    $country_code = trim($_POST['country_code'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // ✅ Handle profile picture upload
    $profile_pic = null;
    if (!empty($_FILES['profile_pic']['name'])) {
        $target_dir = "uploads/profile/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        // sanitize filename a little
        $filename = basename($_FILES["profile_pic"]["name"]);
        $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
        $profile_pic = time() . "_" . $filename;
        $target_file = $target_dir . $profile_pic;

        if (!move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $message = "⚠ Failed to upload profile picture.";
            $profile_pic = null;
        }
    }

    // Build update queries while preserving original logic, but include new fields
    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        if ($profile_pic) {
            // name, email, password, gender, education_level, profile_pic, dob, country_code, phone WHERE id
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, gender=?, education_level=?, profile_pic=?, dob=?, country_code=?, phone=? WHERE id=?");
            $stmt->bind_param("sssssssssi", $name, $email, $hashed, $gender, $education_level, $profile_pic, $dob, $country_code, $phone, $user_id);
        } else {
            // name, email, password, gender, education_level, dob, country_code, phone WHERE id
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, gender=?, education_level=?, dob=?, country_code=?, phone=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $name, $email, $hashed, $gender, $education_level, $dob, $country_code, $phone, $user_id);
        }
    } else {
        if ($profile_pic) {
            // name, email, gender, education_level, profile_pic, dob, country_code, phone WHERE id
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, gender=?, education_level=?, profile_pic=?, dob=?, country_code=?, phone=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $name, $email, $gender, $education_level, $profile_pic, $dob, $country_code, $phone, $user_id);
        } else {
            // name, email, gender, education_level, dob, country_code, phone WHERE id
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, gender=?, education_level=?, dob=?, country_code=?, phone=? WHERE id=?");
            $stmt->bind_param("sssssssi", $name, $email, $gender, $education_level, $dob, $country_code, $phone, $user_id);
        }
    }

    if (isset($stmt) && $stmt) {
        if ($stmt->execute()) {
            $message = "✅ Profile updated successfully!";
            $_SESSION['name'] = $name;
        } else {
            $message = "⚠ Error updating profile: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "⚠ Failed to prepare update statement.";
    }
}

// ✅ Fetch teacher details (include new fields)
$stmt = $conn->prepare("SELECT name, email, gender, education_level, profile_pic, dob, country_code, phone, is_premium_user FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Teacher Profile</title>
  <link rel="stylesheet" href="css/style.css">
  
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Teacher
    </div>
    <div class="logout"><a href="logout.php">Logout</a></div>
  </div>


  <div class="container">
    <div class="sidebar" role="navigation" aria-label="Teacher menu">
      <ul>
        <li><a href="teacher_dashboard.php" class="<?= (basename($_SERVER['PHP_SELF'])=='teacher_dashboard.php') ? 'active' : '' ?>"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="teacher_create_quiz.php" class="<?= (basename($_SERVER['PHP_SELF'])=='teacher_create_quiz.php') ? 'active' : '' ?>"><span class="icon">✏️</span> Create Quiz</a></li>
        <li><a href="teacher_add_questions.php" class="<?= (basename($_SERVER['PHP_SELF'])=='teacher_add_questions.php') ? 'active' : '' ?>"><span class="icon">➕</span> Add Questions</a></li>
        <li><a href="teacher_bulk_upload.php" class="<?= (basename($_SERVER['PHP_SELF'])=='teacher_bulk_upload.php') ? 'active' : '' ?>"><span class="icon">📂</span> Bulk Upload (CSV)</a></li>
        <li><a href="teacher_manage_quizzes.php" class="<?= (basename($_SERVER['PHP_SELF'])=='teacher_manage_quizzes.php') ? 'active' : '' ?>"><span class="icon">📘</span> Manage My Quizzes</a></li>
        <li><a href="teacher_view_results.php" class="<?= (basename($_SERVER['PHP_SELF'])=='teacher_view_results.php') ? 'active' : '' ?>"><span class="icon">📈</span> View Results</a></li>
        <li><a href="teacher_profile.php" class="active"><span class="icon">👤</span> Profile</a></li>
      </ul>
    </div>

    <!-- Profile Content -->
    <div class="content">
      <div class="profile-card">
        <h2 style="margin-top:0;color:#1e3a8a;">My Profile</h2>

        <?php if (!empty($message)): ?>
          <div class="msg"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="profile-grid">
          <div style="text-align:center;">
            <?php if (!empty($user['profile_pic'])): ?>
              <img src="uploads/profile/<?= htmlspecialchars($user['profile_pic']) ?>" class="profile-pic" alt="profile">
            <?php else: ?>
              <img src="css/default-avatar.png" class="profile-pic" alt="default avatar">
            <?php endif; ?>

            <div class="small-note" style="margin-top:10px;">
              <?= !empty($user['is_premium_user']) ? "✅ Premium User" : "❌ Free User" ?>
            </div>
          </div>

          <div>
            <form method="post" enctype="multipart/form-data" id="profileForm">
              <div class="profile-fields">
                <label>Name:</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" disabled required>

                <label>Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled required>

                <label>Gender:</label>
                <select name="gender" disabled>
                  <option value="">-- Select --</option>
                  <option value="Male" <?= isset($user['gender']) && $user['gender']=="Male"?"selected":"" ?>>Male</option>
                  <option value="Female" <?= isset($user['gender']) && $user['gender']=="Female"?"selected":"" ?>>Female</option>
                  <option value="Other" <?= isset($user['gender']) && $user['gender']=="Other"?"selected":"" ?>>Other</option>
                </select>

                <label>Education Level:</label>
                <select name="education_level" disabled>
                  <option value="">-- Select --</option>
                  <option value="High School" <?= isset($user['education_level']) && $user['education_level']=="High School"?"selected":"" ?>>High School</option>
                  <option value="Bachelor" <?= isset($user['education_level']) && $user['education_level']=="Bachelor"?"selected":"" ?>>Bachelor</option>
                  <option value="Master" <?= isset($user['education_level']) && $user['education_level']=="Master"?"selected":"" ?>>Master</option>
                  <option value="PhD" <?= isset($user['education_level']) && $user['education_level']=="PhD"?"selected":"" ?>>PhD</option>
                </select>

                <label>Date of Birth:</label>
                <input type="date" name="dob" value="<?= htmlspecialchars($user['dob'] ?? '') ?>" disabled>

                <label>Country Code:</label>
                <select name="country_code" disabled>
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

                <label>Phone:</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" disabled>

                <label>Profile Picture:</label>
                <input type="file" name="profile_pic" disabled>

                <label>Password (leave blank if not changing):</label>
                <input type="password" name="password" disabled>

                <div style="margin-top:16px; display:flex; gap:10px; align-items:center;">
                  <button type="button" class="edit-btn" id="editBtn">Edit</button>
                  <button type="submit" class="save-btn" id="saveBtn">Save</button>
                </div>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>

<script>
// Enable editing
document.getElementById("editBtn").addEventListener("click", function() {
    let form = document.getElementById("profileForm").elements;
    for (let i = 0; i < form.length; i++) {
        form[i].disabled = false;
    }
    document.getElementById("editBtn").style.display = "none";
    document.getElementById("saveBtn").style.display = "inline-block";
});
</script>
</body>
</html>

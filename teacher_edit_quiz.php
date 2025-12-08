<?php
session_start();
require 'db.php';

// ✅ Check if logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$error = '';

// ✅ Get quiz ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid quiz ID.");
}
$quiz_id = intval($_GET['id']);

// ✅ Fetch quiz info
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND created_by = ?");
$stmt->bind_param("ii", $quiz_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$quiz = $result->fetch_assoc();

if (!$quiz) {
    die("❌ Quiz not found or unauthorized access.");
}

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;

    // ✅ If "Other" is selected, use custom subject
    if ($subject === 'Other') {
        $subject = trim($_POST['custom_subject']);
    }

    if (empty($title) || empty($subject)) {
        $error = "⚠️ Title and Subject are required.";
    } else {
        $update = $conn->prepare("UPDATE quizzes SET title = ?, subject = ?, description = ?, is_premium = ? WHERE id = ? AND created_by = ?");
        $update->bind_param("sssiii", $title, $subject, $description, $is_premium, $quiz_id, $teacher_id);

        if ($update->execute()) {
            $message = "✅ Quiz updated successfully!";
            // Refresh quiz data
            $stmt->execute();
            $quiz = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "⚠️ Error updating quiz: " . $update->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Edit Quiz</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .form-container {
      background: #fff;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      max-width: 600px;
      margin: 30px auto;
    }
    input, textarea, select {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border-radius: 6px;
      border: 1px solid #ddd;
    }
    button {
      background: #2563eb;
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    button:hover {
      background: #1e40af;
    }
    .msg { color: green; font-weight: bold; }
    .err { color: red; font-weight: bold; }
  </style>
</head>
<body>

  <!-- Navbar -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      Quiz Campus - Teacher
    </div>
    <div class="logout"><a href="logout.php">Logout</a></div>
  </div>

  <div class="container">
    <div class="sidebar">
      <ul>
        <li><a href="teacher_dashboard.php">🏠 Dashboard</a></li>
        <li><a href="teacher_create_quiz.php">✏️ Create Quiz</a></li>
        <li><a href="teacher_add_questions.php">➕ Add Questions</a></li>
        <li><a href="teacher_bulk_upload.php">📂 Bulk Upload (CSV)</a></li>
        <li><a href="teacher_manage_quizzes.php"class="active">🧾 Manage My Quizzes</a></li>
        <li><a href="teacher_view_results.php">📈 View Results</a></li>
        <li><a href="teacher_profile.php">👤 Profile</a></li>
    </div>

    <!-- Main Content -->
    <div class="content">
      <h2>✏️ Edit Quiz</h2>
      <div class="form-container">

        <?php if ($error): ?><p class="err"><?= $error ?></p><?php endif; ?>
        <?php if ($message): ?><p class="msg"><?= $message ?></p><?php endif; ?>

        <form method="POST">
          <label>Quiz Title:</label>
          <input type="text" name="title" value="<?= htmlspecialchars($quiz['title']) ?>" required>

          <label>Subject:</label>
          <select name="subject" id="subject-select" onchange="toggleCustomSubject()">
            <option value="Math" <?= ($quiz['subject'] == 'Math') ? 'selected' : '' ?>>Math</option>
            <option value="Science" <?= ($quiz['subject'] == 'Science') ? 'selected' : '' ?>>Science</option>
            <option value="English" <?= ($quiz['subject'] == 'English') ? 'selected' : '' ?>>English</option>
            <option value="Computer" <?= ($quiz['subject'] == 'Computer') ? 'selected' : '' ?>>Computer</option>
            <option value="Other" <?= (!in_array($quiz['subject'], ['Math','Science','English','Computer'])) ? 'selected' : '' ?>>Other</option>
          </select>

          <div id="custom-subject-container" style="display: none;">
            <label>Custom Subject:</label>
            <input type="text" name="custom_subject" placeholder="Enter custom subject" 
              value="<?= (!in_array($quiz['subject'], ['Math','Science','English','Computer'])) ? htmlspecialchars($quiz['subject']) : '' ?>">
          </div>

          <label>Description:</label>
          <textarea name="description" placeholder="Short description..."><?= htmlspecialchars($quiz['description']) ?></textarea>

          <label>
            <input type="checkbox" name="is_premium" <?= $quiz['is_premium'] ? 'checked' : '' ?>>
            Mark as Premium Quiz
          </label><br><br>

          <button type="submit">💾 Update Quiz</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    function toggleCustomSubject() {
      const select = document.getElementById("subject-select");
      const customBox = document.getElementById("custom-subject-container");
      customBox.style.display = select.value === "Other" ? "block" : "none";
    }
    toggleCustomSubject(); // ✅ Initialize on page load
  </script>
</body>
</html>

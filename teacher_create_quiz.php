<?php
session_start();
require 'db.php';

// ✅ Check if user is logged in as teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $teacher_id = $_SESSION['user_id'];
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;

    // ✅ Handle subject (either dropdown or custom)
    $subject = $_POST['subject'];
    if ($subject === 'other') {
        $subject = trim($_POST['custom_subject']);
    }

    // Fetch time limit from form
    $time_limit = isset($_POST['time_limit']) ? (int)$_POST['time_limit'] : 0;

    if (!$title) {
        $error = "Quiz title is required.";
    } elseif (!$subject) {
        $error = "Please select or enter a subject.";
    } else {
        $stmt = $conn->prepare("INSERT INTO quizzes (title, subject, description, created_by, is_premium, time_limit) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("sssiii", $title, $subject, $description, $teacher_id, $is_premium, $time_limit);

        if ($stmt->execute()) {
            $message = "✅ Quiz created successfully!";
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Create Quiz</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    .hidden { display: none; }
    label { font-weight: 600; margin-top: 10px; display: block; }
    select, input[type=text], textarea {
      width: 100%; padding: 10px; margin-top: 6px; border-radius: 6px; border: 1px solid #ccc;
    }
    button {
      margin-top: 15px;
      background: #2563eb;
      color: #fff;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 16px;
    }
    button:hover { background: #1e40af; }
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
    <!-- Sidebar -->
    <div class="sidebar">
      <ul>
        <li><a href="teacher_dashboard.php">
  <i class="fa-solid fa-house"></i> Dashboard
</a></li>

<li><a href="teacher_create_quiz.php" class="active">
  <i class="fa-solid fa-pen-to-square"></i> Create Quiz
</a></li>

<li><a href="teacher_add_questions.php">
  <i class="fa-solid fa-circle-plus"></i> Add Questions
</a></li>

<li><a href="teacher_bulk_upload.php">
  <i class="fa-solid fa-file-csv"></i> Bulk Upload (CSV)
</a></li>

<li><a href="teacher_manage_quizzes.php">
  <i class="fa-solid fa-list-check"></i> Manage My Quizzes
</a></li>

<li><a href="teacher_view_results.php">
  <i class="fa-solid fa-chart-line"></i> View Results
</a></li>

<li><a href="teacher_profile.php">
  <i class="fa-solid fa-user"></i> Profile
</a></li>

      </ul>
    </div>

    <!-- Main Content -->
    <div class="content">
      <h2>
  <i class="fa-solid fa-pen-to-square"></i> Create a New Quiz
</h2>


      <?php if($error): ?>
        <p class="err"><?= $error ?></p>
      <?php endif; ?>

      <?php if($message): ?>
        <p class="msg"><?= $message ?></p>
      <?php endif; ?>

      <form method="post">
        <label>Quiz Title:</label>
        <input type="text" name="title" placeholder="Enter quiz title" required>

        <label>Subject:</label>
        <select name="subject" id="subject-select" required>
          <option value="">-- Select Subject --</option>
          <option value="Mathematics">Mathematics</option>
          <option value="Science">Science</option>
          <option value="English">English</option>
          <option value="Computer">Computer</option>
          <option value="History">History</option>
          <option value="Geography">Geography</option>
          <option value="other">Other</option>
        </select>

        <div id="custom-subject-box" class="hidden">
          <label>Enter Custom Subject:</label>
          <input type="text" name="custom_subject" placeholder="Enter your own subject name">
        </div>

        <label>Description (optional):</label>
        <textarea name="description" placeholder="Short description..." style="height:80px;"></textarea>

        <label for="time_limit">Time Limit (in minutes):</label>
        <input type="number" name="time_limit" id="time_limit" placeholder="Enter time limit in minutes" required min="1">

        <!-- ✅ Premium checkbox -->
        <label class="checkbox-label">
          <input type="checkbox" name="is_premium" value="1">
          <span>Mark this quiz as <strong>Premium</strong></span>
        </label>

        <button type="submit">Create Quiz</button>
      </form>
    </div>
  </div>

  <script>
    // Show custom subject field if "Other" is selected
    const subjectSelect = document.getElementById('subject-select');
    const customBox = document.getElementById('custom-subject-box');

    subjectSelect.addEventListener('change', function() {
      if (this.value === 'other') {
        customBox.classList.remove('hidden');
      } else {
        customBox.classList.add('hidden');
      }
    });
  </script>
</body>
</html>

<?php
session_start();
require 'db.php';

// ✅ Only teachers allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user_id'];
$error = '';
$message = '';

// Get quizzes created by this teacher
$quiz_stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE created_by = ?");
$quiz_stmt->bind_param("i", $teacher_id);
$quiz_stmt->execute();
$quizzes = $quiz_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quiz_id = $_POST['quiz_id'];
    $question_text = trim($_POST['question_text']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_option = $_POST['correct_option'];

    // ✅ Handle image upload
    $image_name = NULL;
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // create folder if not exists
        }
        $image_name = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;

        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $error = "⚠ Image upload failed.";
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("INSERT INTO questions 
            (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, image) 
            VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssssss", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $image_name);

        if ($stmt->execute()) {
            $message = "✅ Question added successfully!";
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
  <title>Add Questions</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>
<body>
  <!-- Navbar -->
  <div class="navbar">
    <div class="logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo" style="width:40px;">
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

<li><a href="teacher_create_quiz.php">
  <i class="fa-solid fa-pen-to-square"></i> Create Quiz
</a></li>

<li><a href="teacher_add_questions.php"class="active">
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

    <!-- Content -->
    <div class="content">
      <h2>
  <i class="fa-solid fa-circle-plus"></i> Add Questions
</h2>


      <?php if($error): ?>
        <p class="err"><?= $error ?></p>
      <?php endif; ?>

      <?php if($message): ?>
        <p class="msg"><?= $message ?></p>
      <?php endif; ?>

      <?php if (count($quizzes) === 0): ?>
        <p class="err">⚠ You haven’t created any quizzes yet. <a href="teacher_create_quiz.php">Create one first</a>.</p>
      <?php else: ?>
      <form method="post" enctype="multipart/form-data">
        <label>Select Quiz:</label><br>
        <select name="quiz_id" required>
          <option value="">-- Select Quiz --</option>
          <?php foreach ($quizzes as $q): ?>
            <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['title']) ?></option>
          <?php endforeach; ?>
        </select><br><br>

        <label>Question Text:</label><br>
        <textarea name="question_text" style="width:100%;height:80px;" required></textarea><br><br>

        <label>Option A:</label><br>
        <input type="text" name="option_a" required><br><br>

        <label>Option B:</label><br>
        <input type="text" name="option_b" required><br><br>

        <label>Option C:</label><br>
        <input type="text" name="option_c" required><br><br>

        <label>Option D:</label><br>
        <input type="text" name="option_d" required><br><br>

        <label>Correct Answer:</label><br>
        <select name="correct_option" required>
          <option value="">-- Select --</option>
          <option value="A">A</option>
          <option value="B">B</option>
          <option value="C">C</option>
          <option value="D">D</option>
        </select><br><br>

        <label>Upload Image (optional):</label><br>
        <input type="file" name="image" accept="image/*"><br><br>

        <button type="submit">Add Question</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

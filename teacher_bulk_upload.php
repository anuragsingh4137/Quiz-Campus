<?php
session_start();
require 'db.php'; // make sure this path is correct and contains your DB connection

// Only teachers allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user_id'];
$error = '';
$message = '';
$details = []; // for info messages

// Prepare uploads directory
$uploads_dir = __DIR__ . '/uploads';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

// Fetch quizzes created by this teacher
$quiz_stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE created_by = ?");
$quiz_stmt->bind_param("i", $teacher_id);
$quiz_stmt->execute();
$quizzes = $quiz_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle upload
if (isset($_POST['upload'])) {
    $quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;

    if (!$quiz_id) {
        $error = "Please select a quiz.";
    } elseif (!is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $error = "Please upload a CSV file.";
    } else {
        // Map of original basename (lowercase) => saved safe filename
        $extracted_map = [];

        // 1) If zip file uploaded, extract images safely and build map
        if (!empty($_FILES['zip_file']['name'])) {
            if (!extension_loaded('zip')) {
                $error = "Zip extension not available on the server. Please enable PHP ZipArchive.";
            } else {
                $zip = new ZipArchive;
                $res = $zip->open($_FILES['zip_file']['tmp_name']);
                if ($res === TRUE) {
                    $countExtracted = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entryName = $zip->getNameIndex($i);
                        // normalize slashes
                        $entryName = str_replace('\\', '/', $entryName);

                        // skip directories
                        if (substr($entryName, -1) === '/') continue;

                        // prevent directory traversal (Zip Slip)
                        if (strpos($entryName, '..') !== false) {
                            // skip suspicious file
                            continue;
                        }

                        $basename = basename($entryName);
                        if (!$basename) continue;

                        // allow only image extensions
                        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                        $allowed = ['jpg','jpeg','png','gif','webp','svg','bmp','tiff'];
                        if (!in_array($ext, $allowed)) {
                            // skip non-image files
                            continue;
                        }

                        // create a safe unique filename to avoid collisions
                        $safeBase = preg_replace('/[^A-Za-z0-9_.-]/', '_', $basename);
                        $safeName = time() . '_' . $safeBase;
                        $targetPath = $uploads_dir . DIRECTORY_SEPARATOR . $safeName;

                        // open stream from zip entry and write to target
                        $stream = $zip->getStream($entryName);
                        if ($stream) {
                            $out = fopen($targetPath, 'w');
                            if ($out) {
                                while (!feof($stream)) {
                                    fwrite($out, fread($stream, 8192));
                                }
                                fclose($out);
                                fclose($stream);
                                $extracted_map[strtolower($basename)] = $safeName;
                                $countExtracted++;
                            } else {
                                // couldn't open target file for writing
                                if (is_resource($stream)) fclose($stream);
                            }
                        }
                    }
                    $zip->close();
                    $details[] = "Extracted {$countExtracted} image(s) from ZIP.";
                } else {
                    $error = "Failed to open ZIP file (error code: $res).";
                }
            }
        }

        // 2) Read CSV and insert questions
        if (!$error) {
            $csvTmp = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($csvTmp, "r")) !== FALSE) {
                $rowCount = 0;
                $inserted = 0;
                $missingImages = 0;

                // Optional: use transaction so all rows insert or none
                $conn->begin_transaction();

                // read header row first (if present)
                $header = fgetcsv($handle);

                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $rowCount++;
                    // Expect: question_text,option_a,option_b,option_c,option_d,correct_option,image
                    if (count($data) < 6) {
                        // skip invalid row
                        continue;
                    }

                    $question_text = trim($data[0]);
                    $option_a = trim($data[1]);
                    $option_b = trim($data[2]);
                    $option_c = trim($data[3]);
                    $option_d = trim($data[4]);
                    $correct_option = strtoupper(trim($data[5]));
                    $image_in_csv = isset($data[6]) ? trim($data[6]) : '';

                    // basic validation
                    if ($question_text === '' || $option_a === '' || $option_b === '' || $option_c === '' || $option_d === '' || !in_array($correct_option, ['A','B','C','D'])) {
                        // skip row if required fields missing or incorrect option
                        continue;
                    }

                    // map image name from CSV to extracted safe name if present
                    $image_to_save = NULL;
                    if ($image_in_csv !== '') {
                        $basename = basename($image_in_csv);
                        $key = strtolower($basename);
                        if (isset($extracted_map[$key])) {
                            $image_to_save = $extracted_map[$key];
                        } else {
                            // check if CSV image name already exists in uploads (teacher may have pre-uploaded)
                            $possible = $uploads_dir . DIRECTORY_SEPARATOR . $basename;
                            if (file_exists($possible)) {
                                $image_to_save = $basename;
                            } else {
                                // image not found - we will still insert question but mark missing
                                $image_to_save = NULL;
                                $missingImages++;
                                $details[] = "Row $rowCount: image '{$image_in_csv}' not found in ZIP or uploads/ — saved question without image.";
                            }
                        }
                    }

                    // insert question
                    $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, image) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->bind_param("isssssss", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $image_to_save);
                    if ($stmt->execute()) {
                        $inserted++;
                    } else {
                        // If you want detailed DB errors, append $stmt->error to $details (dev only)
                        $details[] = "Row $rowCount: DB insert failed: " . $stmt->error;
                        // optional: continue inserting next rows or set an error and break. We continue.
                    }
                }

                // commit
                $conn->commit();
                fclose($handle);

                $message = "Imported {$inserted} question(s) from CSV (processed {$rowCount} rows).";
                if ($missingImages > 0) {
                    $message .= " {$missingImages} row(s) had missing images.";
                }
            } else {
                $error = "Unable to open uploaded CSV file.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Bulk Upload Questions (CSV + ZIP)</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>
<body>
  <div class="navbar">
    <div class="logo"><img src="css/Quiz Campus  logo.png" style="width:24px;"> Quiz Campus - Teacher</div>
    <div class="logout"><a href="logout.php">Logout</a></div>
  </div>

  <div class="container"> 
    <div class="sidebar">
      <ul>
        <li><a href="teacher_dashboard.php">
  <i class="fa-solid fa-house"></i> Dashboard
</a></li>

<li><a href="teacher_create_quiz.php">
  <i class="fa-solid fa-pen-to-square"></i> Create Quiz
</a></li>

<li><a href="teacher_add_questions.php">
  <i class="fa-solid fa-circle-plus"></i> Add Questions
</a></li>

<li><a href="teacher_bulk_upload.php" class="active">
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

    <div class="content">
        <h2>
  <i class="fa-solid fa-file-csv"></i> Bulk Upload Questions (CSV + optional images ZIP)
</h2>

      <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message) ?></p><?php endif; ?>
      <?php if (!empty($details)): ?>
        <div style="background:#fff6eb;border:1px solid #fcd34d;padding:10px;border-radius:6px;margin-bottom:12px;">
          <strong>Notes:</strong>
          <ul>
            <?php foreach ($details as $d): ?>
              <li><?= htmlspecialchars($d) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (count($quizzes) === 0): ?>
        <p class="err">You haven’t created any quizzes yet. <a href="teacher_create_quiz.php">Create one first</a>.</p>
      <?php else: ?>
      <form method="post" enctype="multipart/form-data">
        <label>Select Quiz:</label><br>
        <select name="quiz_id" required>
          <option value="">-- Select Quiz --</option>
          <?php foreach ($quizzes as $q): ?>
            <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['title']) ?></option>
          <?php endforeach; ?>
        </select><br><br>

        <label>Upload CSV File (questions):</label><br>
        <input type="file" name="csv_file" accept=".csv" required><br><br>

        <label>Upload Images (ZIP) - optional:</label><br>
        <input type="file" name="zip_file" accept=".zip"><br>
        <small>If you upload a ZIP, filenames inside ZIP must match the CSV image names (case-insensitive).</small>
        <br><br>

        <button type="submit" name="upload">Upload Questions</button>
      </form>

      <p style="margin-top:14px;"><b>CSV Format (header required):</b></p>
      <pre style="background:#f3f4f6;padding:10px;border-radius:6px;">
question_text,option_a,option_b,option_c,option_d,correct_option,image
What is 2+2?,1,2,3,4,D,
Capital of France?,London,Paris,Berlin,Madrid,B,france.png
Which planet is red?,Earth,Mars,Jupiter,Venus,B,planet.jpg
      </pre>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>

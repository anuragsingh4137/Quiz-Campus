<?php
// teacher_delete_quiz.php
session_start();
require 'config.php'; // make sure this sets up $conn

// Must be logged in and be a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$teacher_id = intval($_SESSION['user_id']);
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quiz_id <= 0) {
    $_SESSION['flash_error'] = "Invalid quiz id.";
    header('Location: teacher_manage_quizzes.php');
    exit;
}

// Check the quiz belongs to this teacher
$stmt = $conn->prepare("SELECT id, title, created_by FROM quizzes WHERE id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$res = $stmt->get_result();
$quiz = $res->fetch_assoc();
$stmt->close();

if (!$quiz) {
    $_SESSION['flash_error'] = "Quiz not found.";
    header('Location: teacher_manage_quizzes.php');
    exit;
}

// If quiz has created_by column and it must match teacher
// If your quizzes table uses another column name for owner, change 'created_by' accordingly.
if (isset($quiz['created_by']) && intval($quiz['created_by']) !== $teacher_id) {
    $_SESSION['flash_error'] = "You are not allowed to delete this quiz.";
    header('Location: teacher_manage_quizzes.php');
    exit;
}

// Begin transaction to delete dependencies then quiz
$conn->begin_transaction();
try {
    // 1) Delete quiz answers (student answers)
    $stmt = $conn->prepare("DELETE FROM quiz_answers WHERE quiz_id = ?");
    if ($stmt) { $stmt->bind_param("i", $quiz_id); $stmt->execute(); $stmt->close(); }

    // 2) Delete quiz attempts
    $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
    if ($stmt) { $stmt->bind_param("i", $quiz_id); $stmt->execute(); $stmt->close(); }

    // 3) Delete results referencing this quiz (if results table stores quiz_id)
    $stmt = $conn->prepare("DELETE FROM results WHERE quiz_id = ?");
    if ($stmt) { $stmt->bind_param("i", $quiz_id); $stmt->execute(); $stmt->close(); }

    // 4) Delete questions (and images if you store files; optional: unlink uploaded files)
    // If questions have image filenames, consider unlinking them here (be cautious).
    $stmt = $conn->prepare("DELETE FROM questions WHERE quiz_id = ?");
    if ($stmt) { $stmt->bind_param("i", $quiz_id); $stmt->execute(); $stmt->close(); }

    // 5) Finally delete the quiz
    $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
    if (!$stmt) throw new Exception("Prepare failed for quizzes delete: " . $conn->error);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        throw new Exception("Quiz deletion failed or quiz not found.");
    }
    $stmt->close();

    // commit
    $conn->commit();
    $_SESSION['flash_success'] = "Quiz deleted successfully.";
    header('Location: teacher_manage_quizzes.php');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    // Log error server-side
    error_log("Teacher delete quiz failed (quiz_id={$quiz_id}, teacher_id={$teacher_id}): " . $e->getMessage());
    $_SESSION['flash_error'] = "Could not delete quiz: " . htmlspecialchars($e->getMessage());
    header('Location: teacher_manage_quizzes.php');
    exit;
}

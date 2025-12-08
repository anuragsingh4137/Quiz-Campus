<?php
// db.php - database connection
$servername = "localhost";
$username = "root";
$password = ""; // default XAMPP password (empty)
$dbname = "quiz_campus_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>

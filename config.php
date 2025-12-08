 <?php
// config.php
// Update DB and Khalti keys below

// Khalti keys (sandbox/test)
define('KHALTI_PUBLIC_KEY', "test_public_key_6fc096d170164854a131bca7d48b74e0");
define('KHALTI_SECRET_KEY', "test_secret_key_fca39f97594140079fcf2b2198ed029e");

// DB connection (adjust values)
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'quiz_campus_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("DB connect error: " . $conn->connect_error);
}



// SMTP Email Settings


define('SMTP_HOST', 'smtp.gmail.com');    // Gmail SMTP server
define('SMTP_PORT', 587);                 // TLS port
define('SMTP_USER', 'quizcampus944@gmail.com');   // Your Gmail address
define('SMTP_PASS', 'kmis bgxq cpkn eisp');     // Your Gmail App Password
define('SMTP_FROM_EMAIL', 'quizcampus944@gmail.com');
define('SMTP_FROM_NAME', 'Quiz Campus');


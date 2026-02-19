 <?php
// config.php
// Update DB and Khalti keys below (this file is already from you; updated to use selected public key)

// SITE URL (no trailing slash)
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/quiz-campus');

// Khalti keys (sandbox Live keys for ePayment)
// PUBLIC key (safe in client)
if (!defined('KHALTI_PUBLIC_KEY')) define('KHALTI_PUBLIC_KEY', '7904f4614e3f4eb6b737e9648798ae0f');

// SECRET key (server only) — you provided this earlier in your config; keep it private
if (!defined('KHALTI_SECRET_KEY')) define('KHALTI_SECRET_KEY', '46348fe03dff4b6abae2cc262c054023');

// DB connection (adjust values if needed)
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'quiz_campus_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("DB connect error: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// SMTP Email Settings (kept from your file)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', 'quizcampus944@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'kmis bgxq cpkn eisp');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'quizcampus944@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Quiz Campus');

// error log while testing
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');



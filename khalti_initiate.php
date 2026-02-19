<?php
// khalti_initiate.php (robust version)
// Replaces previous file. Detects payments table columns dynamically, inserts accordingly,
// calls Khalti /epayment/initiate/ and redirects user to Khalti payment_url.
// Keeps diagnostic output in case Khalti returns an error.

session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$plan_key = $_POST['plan_key'] ?? 'monthly';

// Plan map (NPR)
$plan_map = [
  'monthly' => ['amount'=>10, 'label'=>'Monthly', 'duration_days'=>30],
  'quarter' => ['amount'=>20, 'label'=>'Quarter', 'duration_days'=>90],
  'lifetime'=> ['amount'=>30, 'label'=>'Lifetime', 'duration_days'=>0],
];

if (!isset($plan_map[$plan_key])) $plan_key = 'monthly';
$amount_npr = $plan_map[$plan_key]['amount'];
$amount_paisa = (int) round(floatval($amount_npr) * 100);

if ($amount_paisa < 1000) {
    die('Amount too small (minimum NPR 10).');
}

// create purchase_order_id safely
try {
    $purchase_order_id = 'order-' . time() . '-' . $user_id . '-' . bin2hex(random_bytes(4));
} catch (Exception $e) {
    $purchase_order_id = 'order-' . time() . '-' . $user_id . '-' . substr(md5(uniqid('', true)), 0, 8);
}
$purchase_order_name = 'Quiz Campus - ' . $plan_map[$plan_key]['label'];

// Khalti payload
$payload = [
  "return_url" => rtrim(SITE_URL, '/') . '/khalti_callback.php',
  "website_url" => SITE_URL,
  "amount" => $amount_paisa,
  "purchase_order_id" => $purchase_order_id,
  "purchase_order_name" => $purchase_order_name
];

$init_url = "https://dev.khalti.com/api/v2/epayment/initiate/";

// ensure cURL available
if (!function_exists('curl_init')) {
    die("cURL extension not enabled in PHP. Enable extension=curl in php.ini and restart Apache.");
}

// call Khalti
$ch = curl_init($init_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Key " . KHALTI_SECRET_KEY,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlerr = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log("Khalti initiate curl error: $curlerr");
    die("Payment initiation failed (curl error): " . htmlspecialchars($curlerr));
}

$resp = json_decode($response, true);

// If Khalti didn't return 200 + payment_url, show debug and exit
if ($httpcode !== 200 || empty($resp['payment_url'])) {
    echo "<pre>Khalti Initiate Debug\n\nHTTP CODE: {$httpcode}\n\nRAW RESPONSE:\n";
    echo htmlspecialchars($response, ENT_QUOTES|ENT_SUBSTITUTE);
    echo "\n\nDECODED:\n";
    var_export($resp);
    echo "\n\nCURL ERROR: " . htmlspecialchars($curlerr) . "\n</pre>";
    error_log("Khalti initiate failed: HTTP $httpcode resp=" . $response);
    exit;
}

// success - extract pidx and payment_url
$pidx = $resp['pidx'] ?? null;
$payment_url = $resp['payment_url'] ?? null;

// === DYNAMIC DB INSERT ===
// Detect columns present in payments table
$columns_present = [];
$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments'";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $dbName = $conn->real_escape_string($conn->query("SELECT DATABASE()")->fetch_row()[0]);
    $stmt->bind_param('s', $dbName);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $columns_present[] = $row['COLUMN_NAME'];
    }
    $stmt->close();
} else {
    // If information_schema query failed, log and proceed with best-effort insert
    error_log("Could not query INFORMATION_SCHEMA: " . $conn->error);
}

// Prepare insert fields & values based on available columns
$insertFields = [];
$placeholders = [];
$types = '';
$values = [];

// Common fields we prefer to insert if available
// user_id (int)
if (in_array('user_id', $columns_present)) {
    $insertFields[] = 'user_id';
    $placeholders[] = '?';
    $types .= 'i';
    $values[] = $user_id;
}

// plan_key (string)
if (in_array('plan_key', $columns_present)) {
    $insertFields[] = 'plan_key';
    $placeholders[] = '?';
    $types .= 's';
    $values[] = $plan_key;
}

// purchase_order_id (string)
if (in_array('purchase_order_id', $columns_present)) {
    $insertFields[] = 'purchase_order_id';
    $placeholders[] = '?';
    $types .= 's';
    $values[] = $purchase_order_id;
}

// pidx (string)
if (in_array('pidx', $columns_present)) {
    $insertFields[] = 'pidx';
    $placeholders[] = '?';
    $types .= 's';
    $values[] = $pidx;
}

// amount (int)
if (in_array('amount', $columns_present)) {
    $insertFields[] = 'amount';
    $placeholders[] = '?';
    $types .= 'i';
    $values[] = $amount_paisa;
}

// status (string)
if (in_array('status', $columns_present)) {
    $insertFields[] = 'status';
    $placeholders[] = '?';
    $types .= 's';
    $values[] = 'initiated';
}

// raw_response (string) - store initial resp if column exists
if (in_array('raw_response', $columns_present)) {
    $insertFields[] = 'raw_response';
    $placeholders[] = '?';
    $types .= 's';
    $values[] = $response;
}

// created_at - if present and not auto, we can set NOW(). We'll skip created_at so DB default applies

// Build SQL only if we have at least one field (we should)
if (count($insertFields) > 0) {
    $sql = "INSERT INTO payments (" . implode(',', $insertFields) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // bind params dynamically
        $bind_names[] = $types;
        for ($i=0;$i<count($values);$i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $values[$i];
            $bind_names[] = &$$bind_name;
        }
        // call_user_func_array for bind_param
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
        $ok = $stmt->execute();
        if (!$ok) {
            error_log("DB insert payment failed: " . $stmt->error . " | SQL: ".$sql);
            // do NOT abort; we can still redirect user to Khalti
        }
        $stmt->close();
    } else {
        error_log("DB prepare failed for insert: " . $conn->error . " | SQL: ".$sql);
    }
} else {
    error_log("No suitable payment columns found to insert into payments table.");
}

// finally redirect to Khalti payment page
header("Location: " . $payment_url);
exit;

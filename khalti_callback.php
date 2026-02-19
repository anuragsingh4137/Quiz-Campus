<?php
// khalti_callback.php
session_start();
require 'config.php';

// read pidx from GET
$pidx = $_GET['pidx'] ?? null;
if (!$pidx) {
    die('Missing pidx parameter.');
}

// ensure curl available
if (!function_exists('curl_init')) {
    die("cURL extension not enabled in PHP. Enable extension=curl in php.ini and restart Apache.");
}

// call Khalti lookup API
$lookup_url = "https://dev.khalti.com/api/v2/epayment/lookup/";
$payload = ['pidx' => $pidx];

$ch = curl_init($lookup_url);
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
    error_log("Khalti lookup curl error: $curlerr");
    die("Could not verify payment (curl error): " . htmlspecialchars($curlerr));
}

$resp = json_decode($response, true);
if ($httpcode !== 200) {
    error_log("Khalti lookup failed HTTP $httpcode resp=" . $response);
    die("Payment not completed. Status: " . htmlspecialchars($resp['status'] ?? 'unknown'));
}

// expected response contains fields like pidx, total_amount, status, transaction_id
$status = $resp['status'] ?? '';
$total_amount = $resp['total_amount'] ?? 0; // paisa
$transaction_id = $resp['transaction_id'] ?? null;
$purchase_order_id = $resp['purchase_order_id'] ?? null;

// only proceed on Completed
if ($status !== 'Completed') {
    die("Payment status: " . htmlspecialchars($status));
}

// find payment row by pidx or purchase_order_id
$stmt = $conn->prepare("SELECT id, user_id, plan_key, amount FROM payments WHERE pidx = ? OR purchase_order_id = ? LIMIT 1");
$stmt->bind_param('ss', $pidx, $purchase_order_id);
$stmt->execute();
$stmt->bind_result($payment_id, $user_id, $plan_key, $existing_amount);
$found = $stmt->fetch();
$stmt->close();

$raw_json = $conn->real_escape_string($response);

if (!$found) {
    // fallback: attempt to use session user
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        error_log("Payment completed but no mapping to user: pidx={$pidx}");
        die("Payment completed but unable to map to an account. Contact support.");
    }
    // insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (user_id, plan_key, purchase_order_id, pidx, transaction_id, amount, raw_response, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())");
    $stmt->bind_param('issssis', $user_id, $plan_key, $purchase_order_id, $pidx, $transaction_id, $total_amount, $raw_json);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();
} else {
    // update existing payment row
    $stmt = $conn->prepare("UPDATE payments SET transaction_id = ?, amount = ?, raw_response = ?, status = 'completed' WHERE id = ?");
    $stmt->bind_param('sisi', $transaction_id, $total_amount, $raw_json, $payment_id);
    $stmt->execute();
    $stmt->close();
}

// Now upgrade user: set is_premium_user = 1 and extend premium_expiry accordingly
// Fetch user's current premium_expiry
$q = $conn->prepare("SELECT premium_expiry FROM users WHERE id = ?");
$q->bind_param('i', $user_id);
$q->execute();
$q->bind_result($cur_expiry);
$q->fetch();
$q->close();

$base = new DateTime();
if ($cur_expiry && strtotime($cur_expiry) > time()) {
    $base = new DateTime($cur_expiry);
}

// compute new expiry by plan
$duration_days = 0;
if ($plan_key === 'monthly') $duration_days = 30;
elseif ($plan_key === 'quarter') $duration_days = 90;
elseif ($plan_key === 'lifetime') $duration_days = 0; // lifetime -> set NULL

if ($plan_key === 'lifetime') {
    $u = $conn->prepare("UPDATE users SET is_premium_user = 1, premium_expiry = NULL WHERE id = ?");
    $u->bind_param('i', $user_id);
    $u->execute();
    $u->close();
} else {
    $base->modify("+{$duration_days} days");
    $new_expiry = $base->format('Y-m-d H:i:s');

    $u = $conn->prepare("UPDATE users SET is_premium_user = 1, premium_expiry = ? WHERE id = ?");
    $u->bind_param('si', $new_expiry, $user_id);
    $u->execute();
    $u->close();
}

// success — redirect to student dashboard or success page
header('Location: payment_success.php');
exit;

<?php
// initiate_payment.php
session_start();
require 'config.php'; // must define $conn and KHALTI_SECRET_KEY

// require logged-in student
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Student';
$user_email = $_SESSION['email'] ?? '';

$plan = $_POST['plan'] ?? 'monthly';
$plans = [
    'monthly' => ['amount' => 299,  'label' => '1 Month'],
    'quarter' => ['amount' => 799,  'label' => '3 Months'],
    'lifetime'=> ['amount' => 1999, 'label' => 'Lifetime']
];
if (!isset($plans[$plan])) $plan = 'monthly';

$amount_npr = (int) $plans[$plan]['amount'];
$amount_paisa = $amount_npr * 100;

// generate unique purchase order id
$purchase_order_id = 'qc_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4));

// store a pending payment record (so we can map later)
$stmt = $conn->prepare("INSERT INTO payments (user_id, plan, amount, purchase_order_id, status) VALUES (?, ?, ?, ?, 'initiated')");
if ($stmt) {
    $stmt->bind_param("isiss", $user_id, $plan, $amount_npr, $purchase_order_id);
    $stmt->execute();
    $stmt->close();
}

// return url includes our purchase_order_id so we can look up after redirect
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$return_url = "{$scheme}://{$host}/quiz-campus/payment_success.php?poid=" . urlencode($purchase_order_id);

// Initiate payment with Khalti (sandbox/dev endpoint)
$payload = [
    'return_url' => $return_url,
    'website_url' => "{$scheme}://{$host}",
    'amount' => $amount_paisa,
    'purchase_order_id' => $purchase_order_id,
    'purchase_order_name' => $plans[$plan]['label'],
    'customer_info' => [
        'name' => $user_name,
        'email' => $user_email
    ],
    // include plan as extra data (Khalti may return it back in lookup)
    'merchant_extra' => $plan
];

$ch = curl_init('https://dev.khalti.com/api/v2/epayment/initiate/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Key ' . KHALTI_SECRET_KEY,
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// parse response and redirect if payment_url present
$data = json_decode($response, true);
if ($httpcode === 200 && !empty($data['payment_url'])) {
    header("Location: " . $data['payment_url']);
    exit;
} else {
    // show debug info so you can see the failure
    echo "<h2>Failed to initiate Khalti payment</h2>";
    echo "<pre>" . htmlspecialchars($response ?: 'No response') . "</pre>";
    echo "<p><a href='payment.php'>Back</a></p>";
    exit;
}

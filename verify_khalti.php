<?php
// verify_khalti.php (sets premium_until based on plan)
session_start();
require 'config.php'; // must define $conn and KHALTI_SECRET_KEY

header('Content-Type: application/json');

function khalti_log($msg) {
    $path = __DIR__ . '/khalti_verify.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

// require logged-in student
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success'=>false, 'message'=>'Not logged in.']);
    exit;
}

$raw = @file_get_contents('php://input');
$input = json_decode($raw, true);
$token = trim($input['token'] ?? '');
$amount = (int) ($input['amount'] ?? 0); // NPR
$plan = substr(trim($input['plan'] ?? 'custom'), 0, 50);
$user_id = (int) $_SESSION['user_id'];

if (!$token || $amount < 1 || empty($plan)) {
    echo json_encode(['success'=>false, 'message'=>'Invalid request.']);
    exit;
}

// Khalti verify
$verify_url = "https://khalti.com/api/v2/payment/verify/";
$post_fields = http_build_query(['token' => $token, 'amount' => $amount * 100]);

khalti_log("VERIFY REQ user={$user_id} plan={$plan} amount={$amount} token={$token}");

$ch = curl_init($verify_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
$headers = [
    "Authorization: Key " . KHALTI_SECRET_KEY,
    "Content-Type: application/x-www-form-urlencoded"
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    $err = curl_error($ch);
    curl_close($ch);
    khalti_log("CURL ERROR: $err");
    echo json_encode(['success'=>false, 'message'=>'Network error verifying payment: '.$err]);
    exit;
}
curl_close($ch);

$resp = json_decode($response, true);
khalti_log("KHALTI RESP HTTP={$httpcode} RAW=" . ($response ?: '[empty]'));

// determine success (accept Completed or presence of idx+amount)
$isSuccess = false;
if ($httpcode === 200 && is_array($resp)) {
    $status = strtolower($resp['status'] ?? '');
    if ($status === 'completed') $isSuccess = true;
    if (!empty($resp['idx']) && !empty($resp['amount'])) $isSuccess = true;
}

if (!$isSuccess) {
    $detail = is_array($resp) ? ($resp['detail'] ?? $resp['message'] ?? json_encode($resp)) : $response;
    khalti_log("VERIFICATION FAILED detail={$detail}");
    echo json_encode(['success'=>false, 'message'=>'Khalti verification failed: '.$detail, 'raw'=>$resp]);
    exit;
}

// compute new premium_until based on plan
// get current premium_until
$stmt = $conn->prepare("SELECT premium_until FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$current_until = null;
if ($res && $row = $res->fetch_assoc()) {
    $current_until = $row['premium_until'];
}
$stmt->close();

$now = new DateTime('now', new DateTimeZone(date_default_timezone_get() ?: 'UTC'));
$start = $now;
if (!empty($current_until) && strtotime($current_until) > time()) {
    // extend from existing expiry
    $start = new DateTime($current_until, new DateTimeZone(date_default_timezone_get() ?: 'UTC'));
}

// plan intervals (match the payment.php plan keys)
$plan_intervals = [
    'monthly' => 'P1M',   // 1 month
    'quarter' => 'P3M',   // 3 months
    'lifetime'=> null     // lifetime
];

$premium_until = null;
if (isset($plan_intervals[$plan]) && $plan_intervals[$plan] !== null) {
    // add ISO interval
    $interval = new DateInterval($plan_intervals[$plan]);
    $start->add($interval);
    $premium_until = $start->format('Y-m-d H:i:s');
} else {
    // lifetime -> set premium_until far in the future (50 years)
    $future = new DateTime('now');
    $future->add(new DateInterval('P50Y'));
    $premium_until = $future->format('Y-m-d H:i:s');
}

// Save payment + update user
$token_db = $conn->real_escape_string($token);
$khalti_idx = $conn->real_escape_string($resp['idx'] ?? '');
$amount_db = (int)$amount;

try {
    // insert payment
    $stmt = $conn->prepare("INSERT INTO payments (user_id, plan, amount, token, khalti_idx, premium_until) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isisss", $user_id, $plan, $amount_db, $token_db, $khalti_idx, $premium_until);
        $stmt->execute();
        $stmt->close();
    } else {
        khalti_log("PAY INSERT PREPARE ERROR: " . $conn->error);
    }

    // update users table: set is_premium_user = 1 and premium_until
    $u = $conn->prepare("UPDATE users SET is_premium_user = 1, premium_until = ? WHERE id = ?");
    if ($u) {
        $u->bind_param("si", $premium_until, $user_id);
        $u->execute();
        $u->close();
    } else {
        khalti_log("USER UPDATE PREPARE ERROR: " . $conn->error);
    }

    khalti_log("UPGRADED user={$user_id} plan={$plan} premium_until={$premium_until}");
    echo json_encode(['success'=>true, 'message'=>'Payment verified and account upgraded.', 'premium_until' => $premium_until]);
    exit;
} catch (Exception $ex) {
    khalti_log("EXCEPTION: " . $ex->getMessage());
    echo json_encode(['success'=>false, 'message'=>'Server error saving payment.']);
    exit;
}

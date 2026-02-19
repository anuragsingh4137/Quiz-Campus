<?php
// update_premium.php
session_start();
require 'db.php';
header('Content-Type: application/json');

// admin only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

// basic input validation
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
if ($user_id <= 0 || $payment_id <= 0) {
    echo json_encode(['status'=>'error','message'=>'Missing parameters']);
    exit;
}

// Optional: verify payment exists and is completed
$stmt = $conn->prepare("SELECT payment_status FROM payments WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['status'=>'error','message'=>'Payment record not found']);
    exit;
}
$row = $res->fetch_assoc();
$status = strtolower(trim($row['payment_status'] ?? ''));
if (!in_array($status, ['completed','success'])) {
    echo json_encode(['status'=>'error','message'=>'Payment not completed. Only completed payments can grant premium.']);
    exit;
}

// Now update user to premium (and set expiry if you want — here we set 1 year expiry example)
$is_premium = 1;
$expiry_dt = (new DateTime('+1 year'))->format('Y-m-d H:i:s');

$u = $conn->prepare("UPDATE users SET is_premium_user = 1, premium_expiry = ? WHERE id = ?");
$u->bind_param("si", $expiry_dt, $user_id);
if ($u->execute()) {
    // Optionally mark payment row as processed by admin (add column admin_processed)
    $p = $conn->prepare("UPDATE payments SET admin_processed = 1 WHERE id = ?");
    $p->bind_param("i", $payment_id);
    $p->execute();

    echo json_encode(['status'=>'success','message'=>'User upgraded to premium. Expiry: ' . $expiry_dt]);
    exit;
} else {
    echo json_encode(['status'=>'error','message'=>'DB error: could not update user']);
    exit;
}

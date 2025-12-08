<?php
// payment_success.php
session_start();
require 'config.php'; // must define $conn and KHALTI_SECRET_KEY

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit;
}
$user_id = (int) $_SESSION['user_id'];

// get purchase_order_id we attached in return_url
$poid = $_GET['poid'] ?? '';
$pidx = $_GET['pidx'] ?? ''; // Khalti transaction token (sometimes present)
$status_q = $_GET['status'] ?? '';

// Basic check
if (!$poid) {
    echo "Missing purchase id (poid). Cannot verify payment.";
    exit;
}

// call Khalti lookup API to fetch transaction status
$payload = ['pidx' => $pidx, 'purchase_order_id' => $poid];

// We will try lookup by pidx if provided; else we send purchase_order_id (some Khalti versions accept that)
$ch = curl_init('https://dev.khalti.com/api/v2/epayment/lookup/');
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

$resp = json_decode($response, true);

// debug: you can log $resp to file if needed:
// @file_put_contents(__DIR__.'/khalti_lookup_debug.log', date('c')." ".print_r($resp,true).PHP_EOL, FILE_APPEND);

if (!is_array($resp)) {
    echo "<h3>Verification error</h3><pre>" . htmlspecialchars($response) . "</pre>";
    exit;
}

// success condition (Khalti returns status Completed on success)
if (($httpcode === 200 && isset($resp['status']) && strtolower($resp['status']) === 'completed')
    || !empty($resp['transaction']) || !empty($resp['idx'])) {

    // Determine amount in NPR (Khalti uses paisa)
    $total_amount_paisa = (int) ($resp['total_amount'] ?? $resp['amount'] ?? 0);
    $amount_npr = intdiv($total_amount_paisa, 100); // paisa -> NPR

    // find the payment record by purchase_order_id
    $stmt = $conn->prepare("SELECT id, plan, amount, premium_until FROM payments WHERE purchase_order_id = ? LIMIT 1");
    $stmt->bind_param("s", $poid);
    $stmt->execute();
    $res = $stmt->get_result();
    $paymentRow = $res->fetch_assoc();
    $stmt->close();

    $plan = $paymentRow['plan'] ?? null;
    $payment_id = $paymentRow['id'] ?? null;

    // If not found, fallback: detect plan by amount
    if (!$plan) {
        if ($amount_npr >= 1999) $plan = 'lifetime';
        elseif ($amount_npr >= 799) $plan = 'quarter';
        else $plan = 'monthly';
    }

    // compute new premium expiry
    // fetch current expiry
    $curStmt = $conn->prepare("SELECT premium_expiry FROM users WHERE id = ? LIMIT 1");
    $curStmt->bind_param("i", $user_id);
    $curStmt->execute();
    $curRes = $curStmt->get_result();
    $curRow = $curRes->fetch_assoc();
    $curStmt->close();

    $existing_until = $curRow['premium_expiry'] ?? null;
    $now = new DateTime('now');
    $start = $now;
    if (!empty($existing_until) && strtotime($existing_until) > time()) {
        $start = new DateTime($existing_until);
    }

    if ($plan === 'monthly') {
        $start->add(new DateInterval('P1M'));
        $premium_until = $start->format('Y-m-d H:i:s');
    } elseif ($plan === 'quarter') {
        $start->add(new DateInterval('P3M'));
        $premium_until = $start->format('Y-m-d H:i:s');
    } else { // lifetime
        $tmp = new DateTime();
        $tmp->add(new DateInterval('P50Y'));
        $premium_until = $tmp->format('Y-m-d H:i:s');
    }

    // update payments row with token, khalti_idx, status, premium_until, amount
    $token_val = $resp['idx'] ?? ($resp['transaction'] ?? $pidx);
    $khalti_idx = $resp['transaction_id'] ?? ($resp['tidx'] ?? '');
    $upd = $conn->prepare("UPDATE payments SET token = ?, khalti_idx = ?, status = 'completed', amount = ?, premium_until = ? WHERE purchase_order_id = ?");
    if ($upd) {
        $upd->bind_param("ssiss", $token_val, $khalti_idx, $amount_npr, $premium_until, $poid);
        $upd->execute();
        $upd->close();
    }

    // update user: set is_premium_user and premium_expiry
    $u = $conn->prepare("UPDATE users SET is_premium_user = 1, premium_expiry = ? WHERE id = ?");
    if ($u) {
        $u->bind_param("si", $premium_until, $user_id);
        $u->execute();
        $u->close();
    }

    // success page
    echo "<h2>Payment successful</h2>";
    echo "<p>Plan: <strong>" . htmlspecialchars($plan) . "</strong></p>";
    echo "<p>Amount: Rs " . htmlspecialchars($amount_npr) . "</p>";
    echo "<p>Premium valid until: <strong>" . htmlspecialchars($premium_until) . "</strong></p>";
    echo "<p><a href='student_dashboard.php'>Go to Dashboard</a></p>";
    exit;
} else {
    echo "<h2>Payment verification failed</h2>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    echo "<p><a href='payment.php'>Back to payment</a></p>";
    exit;
}

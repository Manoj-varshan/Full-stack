<?php
// =============================================================
// StreamVault — API: Subscribe / Upgrade Plan
// POST: { plan_id, card_number, card_expiry, card_cvv }
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin('/login.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userId  = $_SESSION['user_id'];
$planId  = (int)($_POST['plan_id'] ?? 0);
$cardNum = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
$last4   = strlen($cardNum) >= 4 ? substr($cardNum, -4) : '0000';

if ($planId < 1) {
    jsonResponse(['success' => false, 'message' => 'Invalid plan selected']);
}

// Verify plan exists
$stmt = db()->prepare("SELECT * FROM plans WHERE id = ?");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    jsonResponse(['success' => false, 'message' => 'Plan not found']);
}

// Simulate payment validation
if ($plan['price'] > 0) {
    if (strlen($cardNum) < 12) {
        jsonResponse(['success' => false, 'message' => 'Invalid card number']);
    }
}

try {
    // Call stored procedure
    $txnId = '';
    $call = db()->prepare("CALL sp_upgrade_plan(?, ?, ?, @txn_id)");
    $call->execute([$userId, $planId, $last4]);
    $call->closeCursor();

    $result = db()->query("SELECT @txn_id AS txn_id")->fetch();
    $txnId = $result['txn_id'] ?? 'TXN' . time();

    // Update session
    $_SESSION['plan_id']   = $planId;
    $_SESSION['plan_name'] = $plan['name'];

    jsonResponse([
        'success'   => true,
        'message'   => "Successfully subscribed to {$plan['name']} plan!",
        'plan_name' => $plan['name'],
        'txn_id'    => $txnId,
        'redirect'  => '/streamvault/browse.php'
    ]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Subscription failed: ' . $e->getMessage()], 500);
}


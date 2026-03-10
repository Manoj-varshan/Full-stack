<?php
// =============================================================
// StreamVault — API: Cancel Subscription
// POST: {} (user_id from session)
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin('/login.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userId = $_SESSION['user_id'];

try {
    $call = db()->prepare("CALL sp_cancel_subscription(?)");
    $call->execute([$userId]);
    $call->closeCursor();

    $_SESSION['plan_name'] = 'Free';
    jsonResponse([
        'success'  => true,
        'message'  => 'Subscription cancelled. You have been moved to the Free plan.',
        'redirect' => '/streamvault/account.php'
    ]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Cancellation failed: ' . $e->getMessage()], 500);
}


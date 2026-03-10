<?php
// =============================================================
// StreamVault: Authentication & Session Guards
// =============================================================
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------
// Require the user to be logged in; redirect if not
// ----------------------------------------------------------
function requireLogin(string $redirect = '/login.php'): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $redirect);
        exit;
    }
}

// ----------------------------------------------------------
// Require admin role; redirect if not
// ----------------------------------------------------------
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: /browse.php');
        exit;
    }
}

// ----------------------------------------------------------
// Get detailed info about the currently logged-in user
// (includes active plan/subscription details)
// ----------------------------------------------------------
function getCurrentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;

    $stmt = db()->prepare("
        SELECT * FROM active_subscriptions_view
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) return null;

    // Default to Free plan info if no subscription found
    if (empty($user['plan_id'])) {
        $free = db()->query("SELECT * FROM plans WHERE LOWER(name)='free' LIMIT 1")->fetch();
        $user = array_merge($user, $free ?? [], ['plan_name' => 'Free']);
    }

    return $user;
}

// ----------------------------------------------------------
// Get the active profile from session
// ----------------------------------------------------------
function getActiveProfile(): ?array {
    if (empty($_SESSION['profile_id'])) return null;

    $stmt = db()->prepare("SELECT * FROM profiles WHERE id = ? AND user_id = ?");
    $stmt->execute([$_SESSION['profile_id'], $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// ----------------------------------------------------------
// Log the user in: set session variables
// ----------------------------------------------------------
function loginUser(array $user): void {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
}

// ----------------------------------------------------------
// Log the user out
// ----------------------------------------------------------
function logoutUser(): void {
    session_unset();
    session_destroy();
}

// ----------------------------------------------------------
// Utility: return JSON response + exit (for API endpoints)
// ----------------------------------------------------------
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ----------------------------------------------------------
// Utility: redirect with a flash message
// ----------------------------------------------------------
function redirectWithMessage(string $url, string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $message, 'type' => $type];
    header("Location: $url");
    exit;
}

// ----------------------------------------------------------
// Utility: pop and show flash message HTML
// ----------------------------------------------------------
function getFlashMessage(): string {
    if (empty($_SESSION['flash'])) return '';
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $cls = $flash['type'] === 'error' ? 'flash-error' : 'flash-success';
    return '<div class="flash-message ' . $cls . '">' . htmlspecialchars($flash['msg']) . '</div>';
}


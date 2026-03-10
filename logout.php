<?php
// StreamVault — Logout
require_once __DIR__ . '/includes/auth.php';
logoutUser();
header('Location: /streamvault/index.php');
exit;


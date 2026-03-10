<?php
// =============================================================
// StreamVault — API: Watchlist (My List)
// POST: { content_id, action: 'add'|'remove' }
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin('/login.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false], 405);
}

$profileId = (int)($_SESSION['profile_id'] ?? 0);
$contentId = (int)($_POST['content_id'] ?? 0);
$action    = $_POST['action'] ?? 'add';

if (!$profileId || !$contentId) {
    jsonResponse(['success' => false, 'message' => 'Missing profile or content']);
}

try {
    if ($action === 'add') {
        $stmt = db()->prepare("
            INSERT IGNORE INTO watchlist (profile_id, content_id) VALUES (?, ?)
        ");
        $stmt->execute([$profileId, $contentId]);
        jsonResponse(['success' => true, 'action' => 'added', 'message' => 'Added to My List']);
    } else {
        $stmt = db()->prepare("
            DELETE FROM watchlist WHERE profile_id = ? AND content_id = ?
        ");
        $stmt->execute([$profileId, $contentId]);
        jsonResponse(['success' => true, 'action' => 'removed', 'message' => 'Removed from My List']);
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}


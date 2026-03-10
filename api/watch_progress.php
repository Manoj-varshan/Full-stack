<?php
// =============================================================
// StreamVault — API: Watch Progress (AJAX / Continue Watching)
// POST: { content_id, progress_seconds, duration_seconds }
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin('/login.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false], 405);
}

$profileId       = (int)($_SESSION['profile_id'] ?? 0);
$contentId       = (int)($_POST['content_id']       ?? 0);
$progressSeconds = (int)($_POST['progress_seconds'] ?? 0);
$durationSeconds = (int)($_POST['duration_seconds'] ?? 0);

if (!$profileId || !$contentId) {
    jsonResponse(['success' => false, 'message' => 'Missing profile or content']);
}

try {
    // Upsert watch history
    $stmt = db()->prepare("
        INSERT INTO watch_history (profile_id, content_id, progress_seconds, duration_seconds)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            progress_seconds = VALUES(progress_seconds),
            duration_seconds = VALUES(duration_seconds),
            last_watched     = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$profileId, $contentId, $progressSeconds, $durationSeconds]);

    jsonResponse(['success' => true, 'saved_at' => date('Y-m-d H:i:s')]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}


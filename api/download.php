<?php
// =============================================================
// StreamVault — API: Download Content (simulated)
// POST: content_id
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tier.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$userId    = $_SESSION['user_id'];
$contentId = (int)($_POST['content_id'] ?? 0);

if (!$contentId) {
    echo json_encode(['success' => false, 'message' => 'Invalid content']);
    exit;
}

// Check download permission
if (!canDownload($userId)) {
    echo json_encode(['success' => false, 'message' => 'Upgrade your plan to download content.']);
    exit;
}

// Check monthly limit
$remaining = getRemainingDownloads($userId);
if ($remaining === 0) {
    echo json_encode(['success' => false, 'message' => 'Monthly download limit reached. Upgrade to Premium for unlimited downloads.']);
    exit;
}

// Check if already downloaded this month
$stmt = db()->prepare("
    SELECT id FROM downloads
    WHERE user_id = ? AND content_id = ?
    AND MONTH(downloaded_at) = MONTH(NOW())
    AND YEAR(downloaded_at)  = YEAR(NOW())
");
$stmt->execute([$userId, $contentId]);
if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'message' => 'Already downloaded this month!', 'already' => true]);
    exit;
}

// Save download
$ins = db()->prepare("INSERT INTO downloads (user_id, content_id) VALUES (?, ?)");
$ins->execute([$userId, $contentId]);

// Get updated remaining
$newRemaining = getRemainingDownloads($userId);

echo json_encode([
    'success'   => true,
    'message'   => 'Downloaded successfully!',
    'remaining' => $newRemaining
]);


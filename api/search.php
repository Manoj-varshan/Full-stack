<?php
// =============================================================
// StreamVault — API: Live Search
// GET: ?q=search_term  ? returns JSON array of matching content
// =============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tier.php';
requireLogin('/login.php');

header('Content-Type: application/json');

$q      = trim($_GET['q'] ?? '');
$userId = $_SESSION['user_id'];

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $canExclusive = canWatchExclusive($userId);
    $exclusiveSQL = $canExclusive ? '' : 'AND c.is_exclusive = 0';

    $stmt = db()->prepare("
        SELECT
            c.id, c.title, c.genre, c.type, c.thumbnail_url,
            c.release_year, c.rating, c.is_exclusive, c.is_early_access, c.is_trending
        FROM content c
        WHERE (c.title LIKE ? OR c.description LIKE ? OR c.genre LIKE ?)
        $exclusiveSQL
        ORDER BY c.is_trending DESC, c.rating DESC
        LIMIT 12
    ");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like]);
    $results = $stmt->fetchAll();

    // Add lock flag for frontend
    foreach ($results as &$r) {
        $r['locked'] = ($r['is_exclusive'] && !$canExclusive) ||
                       ($r['is_early_access'] && !canWatchEarlyAccess($userId));
        $r['quality_badge'] = getVideoQuality($userId);
    }

    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}


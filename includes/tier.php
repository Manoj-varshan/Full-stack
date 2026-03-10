<?php
// =============================================================
// StreamVault: Tier / Feature Gate Logic
// =============================================================
require_once __DIR__ . '/db.php';

// ----------------------------------------------------------
// Get user's full plan info (cached in session)
// ----------------------------------------------------------
function getUserPlan(int $userId): array {
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];

    // -- Admin bypass: always treat as Premium --------------
    $roleStmt = db()->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $roleStmt->execute([$userId]);
    $role = $roleStmt->fetchColumn();
    if ($role === 'admin') {
        $plan = db()->query("SELECT * FROM plans WHERE LOWER(name)='premium' LIMIT 1")->fetch();
        if ($plan) {
            $cache[$userId] = $plan;
            return $plan;
        }
    }
    // ------------------------------------------------------

    $stmt = db()->prepare("
        SELECT p.* FROM plans p
        INNER JOIN subscriptions s ON s.plan_id = p.id
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        $plan = db()->query("SELECT * FROM plans WHERE LOWER(name)='free' LIMIT 1")->fetch();
    }
    $cache[$userId] = $plan ?? [];
    return $cache[$userId];
}

// ----------------------------------------------------------
// Feature gates
// ----------------------------------------------------------
function canWatchExclusive(int $userId): bool {
    return (bool) (getUserPlan($userId)['has_exclusives'] ?? false);
}

function canWatchEarlyAccess(int $userId): bool {
    return (bool) (getUserPlan($userId)['early_access'] ?? false);
}

function getVideoQuality(int $userId): string {
    return getUserPlan($userId)['quality'] ?? '480p';
}

function hasAds(int $userId): bool {
    return (bool) (getUserPlan($userId)['has_ads'] ?? true);
}

function getMaxProfiles(int $userId): int {
    return (int) (getUserPlan($userId)['max_profiles'] ?? 1);
}

function getMaxScreens(int $userId): int {
    return (int) (getUserPlan($userId)['max_screens'] ?? 1);
}

function getMaxDownloads(int $userId): int {
    // -1 = unlimited
    return (int) (getUserPlan($userId)['max_downloads'] ?? 0);
}

function canDownload(int $userId): bool {
    $max = getMaxDownloads($userId);
    if ($max === 0) return false;   // Free – no downloads
    if ($max === -1) return true;  // Premium – unlimited

    // Standard: check this month's download count
    $stmt = db()->prepare("
        SELECT COUNT(*) FROM downloads
        WHERE user_id = ?
          AND MONTH(downloaded_at) = MONTH(NOW())
          AND YEAR(downloaded_at) = YEAR(NOW())
    ");
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();
    return $count < $max;
}

function getRemainingDownloads(int $userId): string {
    $max = getMaxDownloads($userId);
    if ($max === 0)  return '0';
    if ($max === -1) return 'Unlimited';

    $stmt = db()->prepare("
        SELECT COUNT(*) FROM downloads
        WHERE user_id = ?
          AND MONTH(downloaded_at) = MONTH(NOW())
          AND YEAR(downloaded_at) = YEAR(NOW())
    ");
    $stmt->execute([$userId]);
    $used = (int)$stmt->fetchColumn();
    return max(0, $max - $used) . ' / ' . $max;
}

// ----------------------------------------------------------
// Check if a specific content item is accessible to a user
// ----------------------------------------------------------
function isContentAccessible(int $userId, array $content): array {
    $plan = getUserPlan($userId);

    if ($content['is_exclusive'] && !($plan['has_exclusives'] ?? false)) {
        return ['accessible' => false, 'reason' => 'exclusive', 'required_plan' => 'Premium'];
    }
    if ($content['is_early_access'] && !($plan['early_access'] ?? false)) {
        return ['accessible' => false, 'reason' => 'early_access', 'required_plan' => 'Premium'];
    }
    return ['accessible' => true, 'reason' => ''];
}

// ----------------------------------------------------------
// Get tier badge HTML
// ----------------------------------------------------------
function tierBadge(int $userId): string {
    $plan = getUserPlan($userId);
    $name  = htmlspecialchars($plan['name'] ?? 'Free');
    $color = htmlspecialchars($plan['badge_color'] ?? '#888');
    return "<span class='tier-badge' style='background:$color'>$name</span>";
}

// ----------------------------------------------------------
// Get user's profile count
// ----------------------------------------------------------
function getUserProfileCount(int $userId): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}


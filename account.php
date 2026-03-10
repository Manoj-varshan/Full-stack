<?php
// =============================================================
// StreamVault � Account Settings
// =============================================================
$pageTitle = 'Account — StreamVault';
require_once __DIR__ . '/includes/header.php';
requireLogin('/streamvault/login.php');

$userId  = $_SESSION['user_id'];
$plan    = getUserPlan($userId);

// Handle name update
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_name') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            db()->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $userId]);
            $_SESSION['name'] = $name;
            $msg = 'success:Name updated successfully!';
        }
    }
    if ($_POST['action'] === 'update_password') {
        $old  = $_POST['old_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_new']  ?? '';
        $user = db()->prepare("SELECT password FROM users WHERE id=?")->execute([$userId]);
        $row  = db()->prepare("SELECT password FROM users WHERE id=?")->execute([$userId]) ? null : null;
        $stmt = db()->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $row  = $stmt->fetch();
        if (!password_verify($old, $row['password'])) {
            $msg = 'error:Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $msg = 'error:New password must be at least 6 characters.';
        } elseif ($new !== $conf) {
            $msg = 'error:Passwords do not match.';
        } else {
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT), $userId]);
            $msg = 'success:Password updated successfully!';
        }
    }
    header("Location: /streamvault/account.php?msg=" . urlencode($msg));
    exit;
}

// Fetch full user info
$stmt = db()->prepare("SELECT * FROM active_subscriptions_view WHERE user_id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();

// Fetch profiles
$stmt2 = db()->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY is_default DESC");
$stmt2->execute([$userId]);
$profiles = $stmt2->fetchAll();

// Stats
$watchCount = 0;
$stmt3 = db()->prepare("SELECT COUNT(DISTINCT content_id) FROM watch_history wh JOIN profiles p ON wh.profile_id=p.id WHERE p.user_id=?");
$stmt3->execute([$userId]);
$watchCount = $stmt3->fetchColumn();

$dlLeft = getRemainingDownloads($userId);

// Flash from redirect
$flashParam = $_GET['msg'] ?? '';
$flashType  = '';
$flashText  = '';
if ($flashParam) {
    [$flashType, $flashText] = explode(':', $flashParam, 2);
}
?>
<main style="padding-top:calc(var(--nav-height) + 40px);min-height:100vh;padding-bottom:60px;">
<div class="container" style="max-width:900px;">
  <h1 style="font-size:1.8rem;font-weight:800;margin-bottom:28px;"> Account Settings</h1>

  <?php if ($flashText): ?>
  <div class="flash-message flash-<?= $flashType ?>" style="margin-bottom:20px;"><?= htmlspecialchars($flashText) ?></div>
  <?php endif; ?>

  <!-- Stats Row -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:36px;">
    <?php $statsData = [
      ['', 'Member Since', date('M Y', strtotime($userInfo['member_since'] ?? 'now'))],
      ['', 'Titles Watched', $watchCount],
      ['', 'Profiles', count($profiles) . ' / ' . ($plan['max_profiles'] ?? 1)],
      ['', 'Downloads Left', $dlLeft],
    ];
    foreach ($statsData as [$icon, $label, $val]): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;text-align:center;">
      <div style="font-size:1.8rem;margin-bottom:8px;"><?= $icon ?></div>
      <div style="font-size:1.2rem;font-weight:800;margin-bottom:4px;"><?= $val ?></div>
      <div style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

    <!-- Plan Info -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;grid-column:1/-1;">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;"> Current Plan</h3>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
          <span class="tier-badge" style="background:<?= $isAdmin ? '#f5c518' : ($plan['badge_color'] ?? '#888') ?>;font-size:0.85rem;padding:5px 14px;color:<?= $isAdmin ? '#000' : '#fff' ?>">
            <?= $isAdmin ? 'Admin' : ($plan['name'] ?? 'Free') ?>
          </span>
          <div style="margin-top:10px;font-size:0.9rem;color:var(--text-secondary);">
            <?= $plan['quality'] ?? '480p' ?> · <?= $plan['max_screens'] ?? 1 ?> screen<?= ($plan['max_screens'] ?? 1) > 1 ? 's' : '' ?>
            · <?php
              $dl = $plan['max_downloads'] ?? 0;
              echo $dl === 0 ? 'No downloads' : ($dl === -1 ? 'Unlimited downloads' : "$dl downloads/mo");
            ?>
          </div>
          <?php if ($userInfo['end_date']): ?>
          <div style="margin-top:6px;font-size:0.82rem;color:var(--text-muted);">
            Renews: <?= date('d M Y', strtotime($userInfo['end_date'])) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($isAdmin): ?>
          <a href="/streamvault/admin/" class="btn btn-primary">?? Admin Panel</a>
        <?php else: ?>
          <a href="/streamvault/upgrade.php" class="btn btn-primary">
            <?= ($plan['name'] ?? '') === 'Premium' ? ' Manage' : '? Upgrade Plan' ?>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Profile Info -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;"> Profiles</h3>
      <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:16px;">
        <?php foreach ($profiles as $prof): ?>
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:36px;height:36px;border-radius:6px;background:<?= htmlspecialchars($prof['avatar_color']) ?>;
                      display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
            <?= $prof['avatar_icon'] ?>
          </div>
          <div>
            <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($prof['name']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);">
              <?= $prof['is_default'] ? 'Default' : '' ?>
              <?= $prof['is_kids'] ? ' · Kids' : '' ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="/streamvault/profiles.php" class="btn btn-secondary btn-sm btn-block">Manage Profiles</a>
    </div>

    <!-- Update Name -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;"> Profile Info</h3>
      <form method="POST">
        <input type="hidden" name="action" value="update_name">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input class="form-control" type="text" name="name" value="<?= htmlspecialchars($userInfo['user_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input class="form-control" type="email" value="<?= htmlspecialchars($userInfo['email'] ?? '') ?>" disabled style="opacity:0.6;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
      </form>
    </div>

  </div><!-- /grid -->

  <!-- Change Password -->
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;margin-top:24px;">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;"> Change Password</h3>
    <form method="POST" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;align-items:end;">
      <input type="hidden" name="action" value="update_password">
      <div class="form-group" style="margin:0;">
        <label class="form-label">Current Password</label>
        <input class="form-control" type="password" name="old_password" placeholder="••••••••" required>
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label">New Password</label>
        <input class="form-control" type="password" name="new_password" placeholder="Min 6 chars" required>
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Confirm New</label>
        <input class="form-control" type="password" name="confirm_new" placeholder="Repeat" required>
      </div>
      <div style="grid-column:1/-1;">
        <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
      </div>
    </form>
  </div>

  <!-- Danger Zone -->
  <div style="background:rgba(229,9,20,0.06);border:1px solid rgba(229,9,20,0.25);
              border-radius:var(--radius-lg);padding:28px;margin-top:24px;">
    <h3 style="font-size:1rem;font-weight:700;color:var(--accent);margin-bottom:8px;"> Danger Zone</h3>
    <p style="font-size:0.88rem;color:var(--text-secondary);margin-bottom:16px;">
      Cancel your current subscription. You'll be moved to the Free plan.
    </p>
    <a href="/streamvault/upgrade.php" class="btn btn-sm" style="background:var(--accent);color:#fff;">
      Manage Subscription
    </a>
  </div>
</div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>


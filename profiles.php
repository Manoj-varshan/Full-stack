<?php
// =============================================================
// StreamVault � Profiles Selection Page (Netflix-style)
// =============================================================
$pageTitle = 'Who\'s Watching? — StreamVault';
$extraCss  = 'browse.css';
require_once __DIR__ . '/includes/header.php';
requireLogin('/streamvault/login.php');

$userId       = $_SESSION['user_id'];
$maxProfiles  = getMaxProfiles($userId);
$planName     = $currentUser['plan_name'] ?? 'Free';
$isNew        = isset($_GET['new']);

// Handle profile selection
if (isset($_GET['select'])) {
    $profileId = (int)$_GET['select'];
    $verify = db()->prepare("SELECT id FROM profiles WHERE id = ? AND user_id = ?");
    $verify->execute([$profileId, $userId]);
    if ($verify->fetch()) {
        $_SESSION['profile_id']   = $profileId;
        header('Location: /streamvault/browse.php');
        exit;
    }
}

// Handle new profile creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_profile_name'])) {
    $currentCount = getUserProfileCount($userId);
    if ($currentCount < $maxProfiles) {
        $stmt = db()->prepare("
            INSERT INTO profiles (user_id, name, avatar_icon, avatar_color, is_kids)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            substr(trim($_POST['new_profile_name']), 0, 80),
            $_POST['avatar_icon'] ?? '⭐',
            $_POST['avatar_color'] ?? '#E50914',
            isset($_POST['is_kids']) ? 1 : 0
        ]);
        header('Location: /streamvault/profiles.php');
        exit;
    }
}

// Handle profile deletion
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    // Don't delete default profile
    $stmt = db()->prepare("DELETE FROM profiles WHERE id = ? AND user_id = ? AND is_default = 0");
    $stmt->execute([$delId, $userId]);
    header('Location: /streamvault/profiles.php');
    exit;
}

// Fetch profiles
$stmt = db()->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->execute([$userId]);
$profiles = $stmt->fetchAll();

$avatarIcons  = ['🎬','🎭','🎮','🎵','🏆','🌟','⚡','🔥','💫','🎯'];
$avatarColors = ['#E50914','#f5c518','#46d369','#4f8ef7','#a855f7','#f97316','#06b6d4'];
?>

<main style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px 20px;">
  <div class="profiles-page">
    <?php if ($isNew): ?>
    <div style="text-align:center;margin-bottom:10px;padding:10px 20px;
                background:rgba(70,211,105,0.1);border:1px solid rgba(70,211,105,0.3);
                border-radius:var(--radius);color:#46d369;font-size:0.9rem;max-width:500px;">
      🎉 Welcome to Streamora! Your account is ready.
    </div>
    <?php endif; ?>

    <h1 style="font-size:clamp(1.8rem,4vw,3rem);font-weight:800;text-align:center;margin-bottom:10px;">
      Who's Watching?
    </h1>
    <p style="color:var(--text-muted);text-align:center;margin-bottom:48px;">
      Select your profile to continue
    </p>

    <!-- Profile Grid -->
    <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:24px;margin-bottom:40px;">
      <?php foreach ($profiles as $profile): ?>
      <div class="profile-item">
        <a href="/streamvault/profiles.php?select=<?= $profile['id'] ?>" class="profile-avatar-wrap"
           title="Switch to <?= htmlspecialchars($profile['name']) ?>">
          <div class="profile-avatar" style="background:<?= htmlspecialchars($profile['avatar_color']) ?>;">
            <?= $profile['avatar_icon'] ?>
            <?php if ($profile['is_kids']): ?>
              <div class="kids-badge">KIDS</div>
            <?php endif; ?>
          </div>
        </a>
        <div class="profile-name"><?= htmlspecialchars($profile['name']) ?></div>
        <?php if (!$profile['is_default']): ?>
        <a href="/streamvault/profiles.php?delete=<?= $profile['id'] ?>"
           style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;"
           onclick="return confirm('Delete this profile?')">? Delete</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <!-- Add Profile Button -->
      <?php $currentCount = count($profiles); ?>
      <?php if ($currentCount < $maxProfiles): ?>
      <div class="profile-item" id="addProfileTrigger" style="cursor:pointer;" onclick="document.getElementById('addProfileModal').style.display='flex'">
        <div class="profile-avatar" style="background:var(--bg-hover);color:var(--text-muted);font-size:2rem;border:2px dashed var(--border);">
          +
        </div>
        <div class="profile-name" style="color:var(--text-muted);">Add Profile</div>
      </div>
      <?php else: ?>
      <div class="profile-item">
        <div class="profile-avatar" style="background:var(--bg-card);color:var(--text-muted);font-size:1.2rem;border:2px dashed var(--border);cursor:not-allowed;opacity:0.5;">
          🔒
        </div>
        <div class="profile-name" style="color:var(--text-muted);">Profile Limit</div>
        <a href="/streamvault/upgrade.php" style="font-size:0.75rem;color:var(--gold);">Upgrade for more ?</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Plan Info -->
    <div style="text-align:center;font-size:0.85rem;color:var(--text-muted);margin-bottom:20px;">
      <span class="tier-badge" style="background:<?= $badgeColor ?>;"><?= $planName ?></span>
      &nbsp; <?= $currentCount ?> / <?= $maxProfiles ?> profiles used
      <?php if ($planName !== 'Premium'): ?>
      · <a href="/streamvault/upgrade.php" style="color:var(--gold);">Upgrade for more profiles</a>
      <?php endif; ?>
    </div>

    <a href="/streamvault/account.php" class="btn btn-secondary btn-sm" style="margin:0 auto;">
       Manage Profiles
    </a>
  </div>
</main>

<!-- Add Profile Modal -->
<div id="addProfileModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);
     z-index:3000;align-items:center;justify-content:center;backdrop-filter:blur(8px);">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);
              padding:40px;max-width:440px;width:90%;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <h2 style="font-size:1.4rem;font-weight:800;">Add Profile</h2>
      <button onclick="document.getElementById('addProfileModal').style.display='none'"
              style="font-size:1.4rem;color:var(--text-muted);">?</button>
    </div>
    <form method="POST" action="">
      <!-- Avatar Selector -->
      <div style="margin-bottom:20px;">
        <label class="form-label">Choose Avatar</label>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
          <?php foreach ($avatarIcons as $i => $icon): ?>
          <label style="cursor:pointer;">
            <input type="radio" name="avatar_icon" value="<?= $icon ?>"
                   <?= $i===0?'checked':'' ?> style="display:none;">
            <div class="avatar-option" style="width:42px;height:42px;border-radius:8px;background:var(--bg-hover);
                 display:flex;align-items:center;justify-content:center;font-size:1.4rem;
                 border:2px solid transparent;transition:all 0.2s;cursor:pointer;"
                 onclick="this.parentNode.querySelector('input').checked=true;selectIcon(this)">
              <?= $icon ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Color -->
      <div style="margin-bottom:20px;">
        <label class="form-label">Border Color</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
          <?php foreach ($avatarColors as $i => $col): ?>
          <label style="cursor:pointer;">
            <input type="radio" name="avatar_color" value="<?= $col ?>"
                   <?= $i===0?'checked':'' ?> style="display:none;">
            <div style="width:28px;height:28px;border-radius:50%;background:<?= $col ?>;cursor:pointer;
                 border:3px solid transparent;transition:all 0.2s;"
                 onclick="this.parentNode.querySelector('input').checked=true;"></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="new_profile_name">Profile Name</label>
        <input class="form-control" type="text" id="new_profile_name" name="new_profile_name"
               placeholder="e.g. Mom, Dad, Kids…" maxlength="80" required />
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="is_kids" name="is_kids" style="accent-color:var(--accent);" />
        <label for="is_kids" style="font-size:0.88rem;color:var(--text-secondary);cursor:pointer;">
          Kids profile (content restrictions)
        </label>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create Profile</button>
    </form>
  </div>
</div>

<style>
.profiles-page { display:flex;flex-direction:column;align-items:center;width:100%; }
.profile-item  { display:flex;flex-direction:column;align-items:center;gap:6px; }
.profile-avatar-wrap { text-decoration:none; }
.profile-avatar {
  width:110px; height:110px;
  border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-size:3rem;
  cursor:pointer;
  transition:all 0.3s ease;
  position:relative;
  border:3px solid transparent;
}
.profile-avatar-wrap:hover .profile-avatar {
  border-color:#fff;
  box-shadow:0 0 24px rgba(255,255,255,0.2);
  transform:scale(1.06);
}
.profile-name {
  font-size:0.9rem;
  font-weight:600;
  color:var(--text-secondary);
  text-align:center;
  max-width:110px;
  transition:color 0.2s;
}
.profile-avatar-wrap:hover + .profile-name,
.profile-item:hover .profile-name { color:#fff; }
.kids-badge {
  position:absolute;bottom:4px;right:4px;
  background:var(--accent);color:#fff;
  font-size:0.55rem;font-weight:800;
  padding:2px 5px;border-radius:3px;
}
.avatar-option:hover, .avatar-option.selected { border-color:#fff!important; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


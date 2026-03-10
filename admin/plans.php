<?php
// =============================================================
// StreamVault � Admin: Plans Management
// =============================================================
$pageTitle = 'Plans — Admin';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['plan_id'];
    db()->prepare("
        UPDATE plans SET
          name=?, price=?, quality=?, max_screens=?, max_profiles=?,
          max_downloads=?, has_ads=?, has_exclusives=?, early_access=?,
          badge_color=?, description=?
        WHERE id=?
    ")->execute([
        trim($_POST['name']), (float)$_POST['price'], $_POST['quality'],
        (int)$_POST['max_screens'], (int)$_POST['max_profiles'],
        (int)$_POST['max_downloads'],
        isset($_POST['has_ads'])?1:0, isset($_POST['has_exclusives'])?1:0,
        isset($_POST['early_access'])?1:0,
        trim($_POST['badge_color']), trim($_POST['description']), $id
    ]);
    $msg = 'success';
}

$plans = db()->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll();
?>
<main style="padding-top:100px;min-height:100vh;padding-bottom:60px;">
<div class="container" style="max-width:900px;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px;">
    <h1 style="font-size:1.6rem;font-weight:800;"> Plans Management</h1>
    <a href="/streamvault/admin/index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
  </div>
  <?php if ($msg === 'success'): ?>
    <div class="flash-message flash-success" style="margin-bottom:16px;"> Plan updated</div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
  <?php foreach ($plans as $plan): ?>
  <div style="background:var(--bg-card);border:1px solid <?= $plan['badge_color'] ?>;border-radius:var(--radius-lg);padding:28px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
      <span class="tier-badge" style="background:<?= $plan['badge_color'] ?>"><?= $plan['name'] ?></span>
      <span style="font-size:1.4rem;font-weight:900;color:<?= $plan['badge_color'] ?>;">₹<?= number_format($plan['price'],0) ?>/mo</span>
    </div>
    <form method="POST">
      <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
      <div class="form-group">
        <label class="form-label">Plan Name</label>
        <input class="form-control" name="name" value="<?= htmlspecialchars($plan['name']) ?>" required />
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="form-group">
          <label class="form-label">Price (₹/mo)</label>
          <input class="form-control" type="number" name="price" value="<?= $plan['price'] ?>" min="0" step="0.01" />
        </div>
        <div class="form-group">
          <label class="form-label">Quality</label>
          <select class="form-control" name="quality">
            <?php foreach (['480p','1080p','4K'] as $q): ?>
            <option <?= $plan['quality']===$q?'selected':'' ?>><?= $q ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Max Screens</label>
          <input class="form-control" type="number" name="max_screens" value="<?= $plan['max_screens'] ?>" min="1" max="10" />
        </div>
        <div class="form-group">
          <label class="form-label">Max Profiles</label>
          <input class="form-control" type="number" name="max_profiles" value="<?= $plan['max_profiles'] ?>" min="1" max="10" />
        </div>
        <div class="form-group">
          <label class="form-label">Max Downloads</label>
          <input class="form-control" type="number" name="max_downloads" value="<?= $plan['max_downloads'] ?>" min="-1" title="-1 = Unlimited, 0 = None" />
          <div class="form-hint">0=None, -1=Unlimited</div>
        </div>
        <div class="form-group">
          <label class="form-label">Badge Color</label>
          <input class="form-control" type="color" name="badge_color" value="<?= $plan['badge_color'] ?>" />
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
        <?php foreach (['has_ads'=>'Shows Ads','has_exclusives'=>'Exclusive Content','early_access'=>'Early Access'] as $col=>$label): ?>
        <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;">
          <input type="checkbox" name="<?= $col ?>" <?= $plan[$col]?'checked':'' ?> style="accent-color:var(--accent);" />
          <?= $label ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($plan['description']) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-sm btn-block">Save Changes</button>
    </form>
  </div>
  <?php endforeach; ?>
  </div>
</div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


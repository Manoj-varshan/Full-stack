<?php
// =============================================================
// StreamVault � Admin: Subscribers List
// =============================================================
$pageTitle = 'Subscribers — Admin';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$search   = trim($_GET['search'] ?? '');
$planFilt = trim($_GET['plan'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

$where = "WHERE u.role = 'user'";
$params = [];
if ($search) { $where .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($planFilt) { $where .= " AND p.name = ?"; $params[] = $planFilt; }

$countStmt = db()->prepare("SELECT COUNT(*) FROM users u LEFT JOIN subscriptions s ON s.user_id=u.id AND s.status='active' LEFT JOIN plans p ON p.id=s.plan_id $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = ceil($total / $perPage);

$stmt = db()->prepare("
    SELECT u.id, u.name, u.email, u.created_at, p.name AS plan, p.badge_color, s.start_date, s.end_date,
           (SELECT COUNT(*) FROM profiles WHERE user_id=u.id) AS profile_count,
           (SELECT COUNT(DISTINCT content_id) FROM watch_history wh JOIN profiles pr ON wh.profile_id=pr.id WHERE pr.user_id=u.id) AS watched_count
    FROM users u
    LEFT JOIN subscriptions s ON s.user_id=u.id AND s.status='active'
    LEFT JOIN plans p ON p.id=s.plan_id
    $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$subscribers = $stmt->fetchAll();

$plans = db()->query("SELECT name FROM plans")->fetchAll(PDO::FETCH_COLUMN);
?>
<main style="padding-top:calc(var(--nav-height) + 30px);min-height:100vh;padding-bottom:60px;">
<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px;">
    <div>
      <h1 style="font-size:1.6rem;font-weight:800;"> Subscribers</h1>
      <p style="color:var(--text-muted);font-size:0.88rem;"><?= $total ?> total users</p>
    </div>
    <a href="/streamvault/admin/index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
  </div>

  <!-- Search & Filter -->
  <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
    <input class="form-control" type="text" name="search" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search name or email…" style="max-width:280px;" />
    <select name="plan" style="max-width:160px;background:var(--bg-card);color:var(--text-primary);
            border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;
            font-size:0.95rem;font-family:inherit;cursor:pointer;outline:none;">
      <option value="" style="background:var(--bg-card);">All Plans</option>
      <?php foreach ($plans as $p): ?>
        <option value="<?= $p ?>" <?= $planFilt === $p ? 'selected' : '' ?> style="background:var(--bg-card);"><?= $p ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if ($search || $planFilt): ?>
    <a href="/streamvault/admin/subscribers.php" class="btn btn-secondary btn-sm">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Table -->
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:var(--bg-hover);">
          <?php foreach (['Name / Email', 'Plan', 'Member Since', 'Profiles', 'Watched', 'Sub Expires'] as $h): ?>
          <th style="padding:12px 16px;text-align:left;font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;"><?= $h ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subscribers as $u): ?>
        <tr style="border-top:1px solid var(--border);">
          <td style="padding:12px 16px;">
            <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($u['name']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
          </td>
          <td style="padding:12px 16px;">
            <span class="tier-badge" style="background:<?= $u['badge_color'] ?? '#888' ?>;font-size:0.65rem;">
              <?= $u['plan'] ?? 'Free' ?>
            </span>
          </td>
          <td style="padding:12px 16px;font-size:0.85rem;color:var(--text-secondary);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td style="padding:12px 16px;font-size:0.9rem;text-align:center;"><?= $u['profile_count'] ?></td>
          <td style="padding:12px 16px;font-size:0.9rem;text-align:center;"><?= $u['watched_count'] ?></td>
          <td style="padding:12px 16px;font-size:0.82rem;color:var(--text-muted);">
            <?= $u['end_date'] ? date('d M Y', strtotime($u['end_date'])) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!count($subscribers)): ?>
        <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--text-muted);">No subscribers found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;margin-top:24px;">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&plan=<?= urlencode($planFilt) ?>"
       class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


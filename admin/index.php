<?php
// =============================================================
// StreamVault � Admin Dashboard
// =============================================================
$pageTitle = 'Admin Dashboard — StreamVault';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

// --- Aggregate Stats ---------------------------------------
$totalUsers     = db()->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$activeStandard = db()->query("SELECT COUNT(*) FROM subscriptions s JOIN plans p ON s.plan_id=p.id WHERE s.status='active' AND p.name='Standard'")->fetchColumn();
$activePremium  = db()->query("SELECT COUNT(*) FROM subscriptions s JOIN plans p ON s.plan_id=p.id WHERE s.status='active' AND p.name='Premium'")->fetchColumn();
$totalRevenue   = db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success'")->fetchColumn();
$monthRevenue   = db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='success' AND MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())")->fetchColumn();
$totalContent   = db()->query("SELECT COUNT(*) FROM content")->fetchColumn();
$newUsers7      = db()->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// --- Plan Distribution (for pie chart) ---------------------
$dist = db()->query("SELECT * FROM plan_distribution_view")->fetchAll();

// --- Monthly Revenue (last 6 months) - kept for pie chart data -------
$monthlyRev = [];

// --- Top Watched Content ----------------------------------------
$topWatched = db()->query("
    SELECT c.title, c.genre, c.type, c.rating,
           COUNT(wh.id) AS watch_count
    FROM watch_history wh
    JOIN content c ON c.id = wh.content_id
    GROUP BY c.id, c.title, c.genre, c.type, c.rating
    ORDER BY watch_count DESC
    LIMIT 8
")->fetchAll();

// --- Recent Signups -----------------------------------------
$recentUsers = db()->query("
    SELECT u.name, u.email, u.created_at, p.name AS plan_name, p.badge_color
    FROM users u
    JOIN subscriptions s ON s.user_id=u.id AND s.status='active'
    JOIN plans p ON p.id=s.plan_id
    WHERE u.role='user'
    ORDER BY u.created_at DESC LIMIT 8
")->fetchAll();

// --- Recent Payments ----------------------------------------
$recentPayments = db()->query("
    SELECT pay.*, u.name AS user_name, pl.name AS plan_name
    FROM payments pay JOIN users u ON u.id=pay.user_id JOIN plans pl ON pl.id=pay.plan_id
    ORDER BY pay.payment_date DESC LIMIT 6
")->fetchAll();
?>
<main style="padding-top:calc(var(--nav-height) + 30px);min-height:100vh;padding-bottom:60px;">
<div class="container">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:36px;">
    <div>
      <h1 style="font-size:1.8rem;font-weight:800;"> Admin Dashboard</h1>
      <p style="color:var(--text-muted);font-size:0.88rem;">streamora — Membership Engine Analytics</p>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="/streamvault/admin/content.php"      class="btn btn-secondary btn-sm"> Content</a>
      <a href="/streamvault/admin/subscribers.php"  class="btn btn-secondary btn-sm"> Subscribers</a>
      <a href="/streamvault/admin/plans.php"         class="btn btn-secondary btn-sm"> Plans</a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:36px;">
    <?php
    $kpis = [
      ['', 'Total Users',       $totalUsers,     '+' . $newUsers7 . ' this week', '#4f8ef7'],
      ['', 'Standard Subs',     $activeStandard, 'Active',                          '#c0c0c0'],
      ['', 'Premium Subs',      $activePremium,  'Active',                          '#f5c518'],
      ['', 'Total Revenue',     '₹' . number_format($totalRevenue), 'All time',   '#46d369'],
      ['', 'Monthly Revenue',   '₹' . number_format($monthRevenue), date('M Y'),  '#f97316'],
      ['', 'Content Library',   $totalContent,   'Titles',                          '#a855f7'],
    ];
    foreach ($kpis as [$icon, $label, $val, $sub, $color]): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:22px;">
      <div style="font-size:1.5rem;margin-bottom:10px;"><?= $icon ?></div>
      <div style="font-size:1.6rem;font-weight:900;color:<?= $color ?>;"><?= $val ?></div>
      <div style="font-size:0.78rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--text-secondary);"><?= $label ?></div>
      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;"><?= $sub ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts Row -->
  <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:24px;margin-bottom:36px;">

    <!-- Plan Distribution Pie Chart (Canvas API) -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:20px;"> Plan Distribution</h3>
      <canvas id="planChart" width="220" height="220" style="display:block;margin:0 auto;"></canvas>
      <div id="planLegend" style="margin-top:16px;display:flex;flex-direction:column;gap:10px;"></div>
    </div>

    <!-- Top Watched Content -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:20px;">Top Watched Content</h3>
      <?php if (empty($topWatched)): ?>
        <div style="color:var(--text-muted);font-size:0.88rem;text-align:center;padding:40px 0;">No watch data yet</div>
      <?php else: ?>
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="border-bottom:1px solid var(--border);">
            <th style="padding:8px 10px;text-align:left;font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;">#</th>
            <th style="padding:8px 10px;text-align:left;font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Title</th>
            <th style="padding:8px 10px;text-align:left;font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Genre</th>
            <th style="padding:8px 10px;text-align:right;font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Views</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topWatched as $i => $row): ?>
          <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td style="padding:10px;font-size:0.8rem;color:var(--text-muted);width:28px;"><?= $i + 1 ?></td>
            <td style="padding:10px;">
              <div style="font-size:0.88rem;font-weight:600;"><?= htmlspecialchars($row['title']) ?></div>
              <div style="font-size:0.72rem;color:var(--text-muted);"><?= ucfirst($row['type']) ?> &middot; &#9733; <?= $row['rating'] ?></div>
            </td>
            <td style="padding:10px;font-size:0.8rem;color:var(--text-secondary);"><?= htmlspecialchars($row['genre']) ?></td>
            <td style="padding:10px;text-align:right;">
              <span style="background:rgba(124,58,237,0.15);color:var(--accent);font-weight:700;
                           font-size:0.82rem;padding:3px 10px;border-radius:20px;">
                <?= number_format($row['watch_count']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tables Row -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

    <!-- Recent Signups -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
      <div style="padding:18px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;">
        <h3 style="font-size:0.95rem;font-weight:700;"> Recent Signups</h3>
        <a href="/streamvault/admin/subscribers.php" style="font-size:0.8rem;color:var(--accent);">View all →</a>
      </div>
      <table style="width:100%;border-collapse:collapse;">
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
          <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td style="padding:12px 16px;">
              <div style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars($u['name']) ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
            </td>
            <td style="padding:12px 16px;text-align:right;">
              <span class="tier-badge" style="background:<?= $u['badge_color'] ?>;font-size:0.65rem;"><?= $u['plan_name'] ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Payments -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
      <div style="padding:18px 20px;border-bottom:1px solid var(--border);">
        <h3 style="font-size:0.95rem;font-weight:700;">💳 Recent Payments</h3>
      </div>
      <table style="width:100%;border-collapse:collapse;">
        <tbody>
          <?php foreach ($recentPayments as $pay): ?>
          <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td style="padding:12px 16px;">
              <div style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars($pay['user_name']) ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?= $pay['plan_name'] ?> · <?= date('d M', strtotime($pay['payment_date'])) ?></div>
            </td>
            <td style="padding:12px 16px;text-align:right;font-weight:700;color:#46d369;">
              ₹<?= number_format($pay['amount'], 0) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /container -->
</main>

<script>
// -------- PIE CHART (Canvas API � Pure ES6) --------
const planData = <?= json_encode(array_map(fn($d) => [
  'label' => $d['plan_name'],
  'value' => (int)$d['subscriber_count'],
  'color' => $d['badge_color']
], $dist)) ?>;

(function drawPieChart() {
  const canvas  = document.getElementById('planChart');
  const ctx     = canvas.getContext('2d');
  const cx      = canvas.width  / 2;
  const cy      = canvas.height / 2;
  const radius  = Math.min(cx, cy) - 20;
  const total   = planData.reduce((s, d) => s + d.value, 0) || 1;
  const legend  = document.getElementById('planLegend');
  let startAngle = -Math.PI / 2;

  planData.forEach(({ label, value, color }) => {
    const slice = (value / total) * 2 * Math.PI;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, radius, startAngle, startAngle + slice);
    ctx.closePath();
    ctx.fillStyle = color;
    ctx.fill();
    ctx.strokeStyle = '#141414';
    ctx.lineWidth = 2;
    ctx.stroke();

    // Legend
    const pct  = total > 0 ? Math.round((value / total) * 100) : 0;
    const item = document.createElement('div');
    item.style.cssText = 'display:flex;align-items:center;gap:10px;font-size:0.85rem;';
    item.innerHTML = `
      <span style="width:14px;height:14px;border-radius:3px;background:${color};flex-shrink:0;"></span>
      <span>${label}</span>
      <span style="margin-left:auto;font-weight:700;">${value} (${pct}%)</span>
    `;
    legend.appendChild(item);

    startAngle += slice;
  });

  // Center label
  ctx.fillStyle = '#1f1f1f';
  ctx.beginPath();
  ctx.arc(cx, cy, radius * 0.5, 0, Math.PI * 2);
  ctx.fill();
  ctx.fillStyle = '#e5e5e5';
  ctx.font = 'bold 20px Inter, sans-serif';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(total, cx, cy - 8);
  ctx.font = '11px Inter, sans-serif';
  ctx.fillStyle = '#888';
  ctx.fillText('MEMBERS', cx, cy + 12);
})();


</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


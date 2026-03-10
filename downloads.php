<?php
// =============================================================
// StreamVault � Downloads Page
// =============================================================
$pageTitle = 'My Downloads — StreamVault';
require_once __DIR__ . '/includes/header.php';
requireLogin('/streamvault/login.php');

$userId   = $_SESSION['user_id'];
$canDl    = canDownload($userId);
$dlLeft   = getRemainingDownloads($userId);
$planName = $currentUser['plan_name'] ?? 'Free';

// Handle remove download
if (isset($_GET['remove'])) {
    $removeId = (int)$_GET['remove'];
    db()->prepare("DELETE FROM downloads WHERE id = ? AND user_id = ?")->execute([$removeId, $userId]);
    header('Location: /streamvault/downloads.php');
    exit;
}

// Fetch all downloads (this month + older)
$stmt = db()->prepare("
    SELECT d.id AS download_id, d.downloaded_at,
           c.id, c.title, c.thumbnail_url, c.genre, c.type,
           c.rating, c.release_year, c.duration_min, c.total_episodes,
           c.is_exclusive,
           CASE WHEN MONTH(d.downloaded_at) = MONTH(NOW())
                AND  YEAR(d.downloaded_at)  = YEAR(NOW())
                THEN 1 ELSE 0 END AS this_month
    FROM downloads d
    JOIN content c ON c.id = d.content_id
    WHERE d.user_id = ?
    ORDER BY d.downloaded_at DESC
");
$stmt->execute([$userId]);
$downloads = $stmt->fetchAll();

// This month count
$thisMonthCount = count(array_filter($downloads, fn($d) => $d['this_month']));

// Get plan limits
$planInfo  = getUserPlan($userId);
$maxDl     = $planInfo['max_downloads'] ?? 0;
?>
<main style="padding-top:calc(var(--nav-height) + 40px);min-height:100vh;padding-bottom:60px;">
<div class="container" style="max-width:1000px;">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:32px;">
    <div>
      <h1 style="font-size:1.8rem;font-weight:800;"> My Downloads</h1>
      <p style="color:var(--text-muted);font-size:0.88rem;margin-top:4px;">
        <?php if ($maxDl === -1): ?>
          Unlimited downloads · <?= $thisMonthCount ?> downloaded this month
        <?php elseif ($maxDl > 0): ?>
          <?= $thisMonthCount ?> / <?= $maxDl ?> downloads used this month
        <?php else: ?>
          Downloads not available on your plan
        <?php endif; ?>
      </p>
    </div>
    <a href="/streamvault/browse.php" class="btn btn-secondary btn-sm">← Browse</a>
  </div>

  <!-- No download access (Free plan only) -->
  <?php if ($maxDl === 0): ?>
  <div style="text-align:center;padding:80px 20px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);">
    <div style="font-size:4rem;margin-bottom:16px;">🔒</div>
    <h2 style="font-size:1.4rem;font-weight:800;margin-bottom:8px;">Downloads Not Available</h2>
    <p style="color:var(--text-secondary);margin-bottom:24px;max-width:400px;margin-left:auto;margin-right:auto;">
      Downloads are available on Standard (5/month) and Premium (unlimited) plans.
    </p>
    <a href="/streamvault/upgrade.php" class="btn btn-primary btn-lg">⬆ Upgrade Plan</a>
  </div>

  <!-- No downloads yet -->
  <?php elseif (empty($downloads)): ?>
  <div style="text-align:center;padding:80px 20px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);">
    <div style="font-size:4rem;margin-bottom:16px;">📭</div>
    <h2 style="font-size:1.4rem;font-weight:800;margin-bottom:8px;">No Downloads Yet</h2>
    <p style="color:var(--text-secondary);margin-bottom:24px;">
      Press the Download button on any movie or series to save it here.
    </p>
    <a href="/streamvault/browse.php" class="btn btn-primary">Browse Content</a>
  </div>

  <?php else: ?>

  <!-- Monthly progress bar (if limited) -->
  <?php if ($maxDl > 0 && $maxDl !== -1): ?>
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:8px;">
      <span style="font-weight:600;">Monthly Downloads</span>
      <span style="color:var(--text-muted);"><?= $thisMonthCount ?>/<?= $maxDl ?></span>
    </div>
    <div style="background:rgba(255,255,255,0.08);border-radius:6px;height:8px;overflow:hidden;">
      <?php $pct = min(100, round($thisMonthCount / $maxDl * 100)); ?>
      <div style="height:100%;width:<?= $pct ?>%;border-radius:6px;
                  background:var(--accent);
                  transition:width 0.5s;"></div>
    </div>
    <?php if ($pct >= 100): ?>
    <div style="margin-top:8px;font-size:0.8rem;color:var(--accent);">
      Limit reached · <a href="/streamvault/upgrade.php" style="color:var(--gold);">Upgrade to Premium for unlimited</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Downloads Grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
    <?php foreach ($downloads as $dl): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;
                transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='var(--shadow-hover)'"
                onmouseout="this.style.transform='';this.style.boxShadow=''">
      <!-- Thumbnail -->
      <div style="position:relative;">
        <img src="<?= htmlspecialchars($dl['thumbnail_url']) ?>" alt="<?= htmlspecialchars($dl['title']) ?>"
             style="width:100%;aspect-ratio:2/3;object-fit:cover;display:block;"
             onerror="this.src='https://via.placeholder.com/300x450/1f1f1f/666?text=🎬'">

        <!-- Downloaded badge -->
        <div style="position:absolute;top:8px;left:8px;background:rgba(124,58,237,0.9);color:#fff;
                    font-size:0.65rem;font-weight:800;padding:3px 8px;border-radius:4px;">
          ✓ DOWNLOADED
        </div>

        <!-- This month indicator -->
        <?php if ($dl['this_month']): ?>
        <div style="position:absolute;top:8px;right:8px;background:rgba(245,197,24,0.9);color:#000;
                    font-size:0.6rem;font-weight:800;padding:3px 6px;border-radius:4px;">
          THIS MONTH
        </div>
        <?php endif; ?>

        <!-- Exclusive -->
        <?php if ($dl['is_exclusive']): ?>
        <div style="position:absolute;bottom:8px;left:8px;background:var(--gold);color:#000;
                    font-size:0.6rem;font-weight:800;padding:2px 6px;border-radius:3px;">
          EXCLUSIVE
        </div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div style="padding:14px;">
        <div style="font-weight:700;font-size:0.95rem;margin-bottom:4px;line-height:1.3;">
          <?= htmlspecialchars($dl['title']) ?>
        </div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;">
          <span><?= htmlspecialchars($dl['genre']) ?></span>·
          <span style="color:var(--gold);">★ <?= $dl['rating'] ?></span>·
          <span><?= $dl['release_year'] ?></span>
        </div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:12px;">
          📅 <?= date('d M Y, g:ia', strtotime($dl['downloaded_at'])) ?>
        </div>
        <div style="display:flex;gap:8px;">
          <a href="/streamvault/watch.php?id=<?= $dl['id'] ?>"
             class="btn btn-primary btn-sm" style="flex:1;text-align:center;">▶ Play</a>
          <a href="/streamvault/downloads.php?remove=<?= $dl['download_id'] ?>"
             class="btn btn-secondary btn-sm"
             onclick="return confirm('Remove from downloads?')"
             title="Remove download" style="padding:6px 10px;">🗑</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
</div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>


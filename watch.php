<?php
// =============================================================
// StreamVault � Watch / Player Page
// =============================================================
$pageTitle = 'Watch — StreamVault';
$extraCss  = 'player.css';
$extraJs   = 'player.js';
require_once __DIR__ . '/includes/header.php';
requireLogin('/streamvault/login.php');

$userId      = $_SESSION['user_id'];
$profileId   = $_SESSION['profile_id'] ?? null;
$contentId   = (int)($_GET['id'] ?? 0);
$infoOnly    = isset($_GET['info']);

if (!$contentId) { header('Location: /streamvault/browse.php'); exit; }

// Fetch content
$stmt = db()->prepare("SELECT * FROM content WHERE id = ?");
$stmt->execute([$contentId]);
$content = $stmt->fetch();
if (!$content) { header('Location: /streamvault/browse.php'); exit; }

// Check tier access
$access  = isContentAccessible($userId, $content);
$locked  = !$access['accessible'];
$quality = getVideoQuality($userId);
$showAds = hasAds($userId);
$canDl   = canDownload($userId);
$dlLeft  = getRemainingDownloads($userId);
$planName= $currentUser['plan_name'] ?? 'Free';

// Fetch previous progress
$watchedPct = 0;
$prevProgress = 0;
if ($profileId) {
    $stmt2 = db()->prepare("SELECT progress_seconds, duration_seconds FROM watch_history WHERE profile_id = ? AND content_id = ?");
    $stmt2->execute([$profileId, $contentId]);
    $prev = $stmt2->fetch();
    if ($prev && $prev['duration_seconds'] > 0) {
        $prevProgress = $prev['progress_seconds'];
        $watchedPct   = round($prev['progress_seconds'] / $prev['duration_seconds'] * 100);
    }
}

// Watchlist check
$inList = false;
if ($profileId) {
    $stmt3 = db()->prepare("SELECT id FROM watchlist WHERE profile_id = ? AND content_id = ?");
    $stmt3->execute([$profileId, $contentId]);
    $inList = (bool)$stmt3->fetch();
}

// Already downloaded check (this month)
$alreadyDownloaded = false;
$dlCheck = db()->prepare("
    SELECT id FROM downloads
    WHERE user_id = ? AND content_id = ?
    AND MONTH(downloaded_at) = MONTH(NOW())
    AND YEAR(downloaded_at)  = YEAR(NOW())
");
$dlCheck->execute([$userId, $contentId]);
$alreadyDownloaded = (bool)$dlCheck->fetch();

// Simulated duration: movies = 90-180 min, series episodes = 40-60 min
$simDuration = $content['type'] === 'movie'
    ? ($content['duration_min'] ?? 120) * 60
    : 2700; // 45 min per episode

// Related content (same genre)
$stmt4 = db()->prepare("
    SELECT id, title, thumbnail_url, rating, genre, type
    FROM content WHERE genre = ? AND id != ? AND is_exclusive <= ?
    ORDER BY RAND() LIMIT 6
");
$stmt4->execute([$content['genre'], $contentId, canWatchExclusive($userId) ? 1 : 0]);
$related = $stmt4->fetchAll();

// Extract YouTube video ID from URL
$trailerUrl = $content['trailer_url'] ?? '';
$ytId = '';
if (preg_match('/embed\/([a-zA-Z0-9_-]+)/', $trailerUrl, $m)) {
    $ytId = $m[1];
}
?>

<main class="player-page" style="padding-top:var(--nav-height);min-height:100vh;">

<?php if ($locked && !$infoOnly): ?>
<!-- =================== LOCKED =================== -->
<div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;">
  <div style="text-align:center;max-width:450px;">
    <div style="font-size:5rem;margin-bottom:20px;">🔒</div>
    <h2 style="font-size:2rem;font-weight:800;margin-bottom:10px;">Premium Content</h2>
    <p style="color:var(--text-secondary);margin-bottom:30px;">
      <strong><?= htmlspecialchars($content['title']) ?></strong> is exclusively available to
      <strong><?= $access['required_plan'] ?></strong> subscribers.
    </p>
    <a href="/streamvault/upgrade.php" class="btn btn-gold btn-lg btn-block" style="margin-bottom:12px;">
      👑 Upgrade to <?= $access['required_plan'] ?>
    </a>
    <a href="/streamvault/browse.php" class="btn btn-secondary btn-block">? Back to Browse</a>
  </div>
</div>

<?php else: ?>
<!-- =================== PLAYER =================== -->
<div class="player-container">

  <!-- Ad Overlay (Free users) -->
  <?php if ($showAds && !$infoOnly): ?>
  <div id="adOverlay" class="ad-overlay">
    <div class="ad-content">
      <div style="position:absolute;top:12px;left:12px;background:var(--accent);color:#fff;
                  font-size:0.65rem;font-weight:800;padding:3px 8px;border-radius:3px;letter-spacing:1px;">AD</div>
      <div style="font-size:1.4rem;font-weight:800;margin-bottom:8px;">Streamora Premium</div>
      <div style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:16px;">
        Enjoy ad-free streaming, 4K quality, and exclusive content from just ₹199/mo.
      </div>
      <div style="display:flex;gap:10px;">
        <a href="/streamvault/upgrade.php" class="btn btn-primary">Upgrade Now</a>
        <button id="skipAdBtn" class="btn btn-secondary" disabled>
          Skip Ad in <span id="skipCountdown">15</span>s
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Video Player -->
  <?php if (!$infoOnly && $ytId): ?>
  <div class="video-wrapper" id="videoWrapper">
    <!-- YouTube IFrame Player API target -->
    <div id="ytPlayer"></div>

    <!-- Quality Badge stays inside (top-right corner, doesn't block controls) -->
    <div class="quality-badge quality-<?= strtolower(str_replace(' ', '', $quality)) ?>">
      <?= $quality ?>
    </div>
  </div>

  <!-- Player Controls Bar � BELOW the video, not overlapping it -->
  <div class="player-controls" id="playerControls">
    <div class="controls-left">
      <div style="font-weight:700;font-size:0.95rem;"><?= htmlspecialchars($content['title']) ?></div>
      <div style="font-size:0.78rem;color:var(--text-muted);">
        <?= ucfirst($content['type']) ?> · <?= $content['genre'] ?> · <?= $content['release_year'] ?>
      </div>
    </div>
    <div class="controls-right">
      <button class="watchlist-btn ctrl-btn" data-id="<?= $contentId ?>" data-in-list="<?= $inList ? 1 : 0 ?>">
        <?= $inList ? '✓ In List' : '+ My List' ?>
      </button>
      <?php if ($alreadyDownloaded): ?>
      <button class="ctrl-btn" disabled style="opacity:0.7;cursor:default;color:#7C3AED;">
        ✓ Already Downloaded
      </button>
      <?php elseif ($canDl): ?>
      <button class="ctrl-btn" id="downloadBtn" data-id="<?= $contentId ?>">
        ⬇ Download (<?= $dlLeft ?> left)
      </button>
      <?php else: ?>
      <a href="/streamvault/upgrade.php" class="ctrl-btn" style="opacity:0.5;cursor:not-allowed;" title="Upgrade to download">
        Download
      </a>
      <?php endif; ?>
    </div>
  </div>
  <?php elseif ($infoOnly && $ytId): ?>
  <!-- Trailer / Info mode -->
  <div class="video-wrapper" style="max-width:900px;margin:0 auto;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-hover);">
    <iframe
      src="https://www.youtube.com/embed/<?= $ytId ?>?rel=0&modestbranding=1"
      allow="fullscreen"
      allowfullscreen
      style="width:100%;height:100%;border:none;"
    ></iframe>
  </div>
  <?php endif; ?>

</div><!-- /player-container -->

<!-- =================== CONTENT INFO =================== -->
<div class="content-info-section">
  <div class="info-grid">
    <!-- Main Info -->
    <div class="info-main">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;">
        <div>
          <h1 style="font-size:clamp(1.6rem,3vw,2.4rem);font-weight:900;margin-bottom:8px;">
            <?= htmlspecialchars($content['title']) ?>
            <?php if ($content['is_exclusive']): ?>
              <span style="background:var(--gold);color:#000;font-size:0.65rem;font-weight:800;
                           padding:3px 8px;border-radius:4px;vertical-align:middle;margin-left:8px;">EXCLUSIVE</span>
            <?php endif; ?>
          </h1>
          <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;color:var(--text-secondary);font-size:0.9rem;">
            <span style="color:var(--gold);font-weight:700;font-size:1rem;">★ <?= $content['rating'] ?></span>
            <span><?= $content['release_year'] ?></span>
            <span><?= ucfirst($content['type']) ?></span>
            <span><?= htmlspecialchars($content['genre']) ?></span>
            <?php if ($content['duration_min']): ?>
              <span><?= $content['duration_min'] ?> min</span>
            <?php endif; ?>
            <?php if ($content['total_episodes']): ?>
              <span><?= $content['total_episodes'] ?> episodes</span>
            <?php endif; ?>
            <span class="tier-badge" style="background:<?php
              echo match($quality) { '4K' => 'var(--gold)', '1080p' => 'var(--silver)', default => '#6b6b6b' };
            ?>;color:<?= $quality === '4K' ? '#000' : '#fff' ?>;"><?= $quality ?></span>
          </div>
        </div>

        <?php if ($infoOnly): ?>
        <a href="/streamvault/watch.php?id=<?= $contentId ?>" class="btn btn-primary btn-lg">
          ▶ Play Now
        </a>
        <?php endif; ?>
      </div>

      <p style="color:var(--text-secondary);margin-top:20px;font-size:0.95rem;line-height:1.7;max-width:700px;">
        <?= htmlspecialchars($content['description']) ?>
      </p>

      <!-- Progress bar (if watched before) -->
      <?php if ($watchedPct > 0): ?>
      <div style="margin-top:16px;">
        <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:6px;">
          <?= $watchedPct ?>% watched
        </div>
        <div style="background:rgba(255,255,255,0.1);border-radius:3px;height:4px;max-width:300px;">
          <div style="background:var(--accent);height:100%;width:<?= $watchedPct ?>%;border-radius:3px;"></div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Stats Sidebar -->
    <div class="info-sidebar">
      <div class="info-stat-row"><span class="stat-label">Language</span><span><?= htmlspecialchars($content['language']) ?></span></div>
      <div class="info-stat-row"><span class="stat-label">Genre</span><span><?= htmlspecialchars($content['genre']) ?></span></div>
      <div class="info-stat-row"><span class="stat-label">Type</span><span><?= ucfirst($content['type']) ?></span></div>
      <div class="info-stat-row"><span class="stat-label">Rating</span><span style="color:var(--gold);">★ <?= $content['rating'] ?></span></div>
      <div class="info-stat-row"><span class="stat-label">Year</span><span><?= $content['release_year'] ?></span></div>
      <div class="info-stat-row">
        <span class="stat-label">Your Quality</span>
        <span style="font-weight:700;color:<?php
          echo match($quality) { '4K' => 'var(--gold)', '1080p' => 'var(--silver)', default => '#888' };
        ?>;"><?= $quality ?></span>
      </div>
      <?php if ($planName !== 'Premium'): ?>
      <div style="margin-top:16px;padding:12px;background:rgba(245,197,24,0.08);border:1px solid rgba(245,197,24,0.2);border-radius:var(--radius);">
        <div style="font-size:0.8rem;color:var(--gold);margin-bottom:8px;">Upgrade for better quality</div>
        <a href="/streamvault/upgrade.php" class="btn btn-gold btn-sm btn-block">⬆ Upgrade Plan</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- =================== RELATED CONTENT =================== -->
  <?php if (count($related) > 0): ?>
  <div style="margin-top:48px;">
    <h3 style="font-size:1.3rem;font-weight:700;margin-bottom:20px;">More Like This</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;">
      <?php foreach ($related as $rel): ?>
      <div class="content-card">
        <a href="/streamvault/watch.php?id=<?= $rel['id'] ?>&info=1">
          <img src="<?= htmlspecialchars($rel['thumbnail_url']) ?>"
               alt="<?= htmlspecialchars($rel['title']) ?>"
               loading="lazy"
               onerror="this.src='https://via.placeholder.com/300x450/1f1f1f/666?text=🎬'">
          <div class="card-overlay">
            <div class="card-title"><?= htmlspecialchars($rel['title']) ?></div>
            <div class="card-meta">
              <span class="card-rating">★ <?= $rel['rating'] ?></span>
            </div>
            <div class="card-actions">
              <a href="/streamvault/watch.php?id=<?= $rel['id'] ?>" class="card-btn play">▶</a>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; // end !locked ?>
</main>

<script>
const CONTENT_ID   = <?= $contentId ?>;
const PROFILE_ID   = <?= $profileId ?? 'null' ?>;
const SIM_DURATION = <?= $simDuration ?>;
const HAS_ADS      = <?= $showAds ? 'true' : 'false' ?>;
const YT_VIDEO_ID  = <?= json_encode($ytId) ?>;
const PREV_PROGRESS= <?= (int)$prevProgress ?>;
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>


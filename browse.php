<?php
// =============================================================
// StreamVault � Browse Page (Netflix-style)
// =============================================================
$pageTitle = 'Browse — StreamVault';
$extraCss  = 'browse.css';
$extraJs   = 'browse.js';
require_once __DIR__ . '/includes/header.php';
requireLogin('/streamvault/login.php');

$userId       = $_SESSION['user_id'];
$profileId    = $_SESSION['profile_id'] ?? null;
$canExclusive = canWatchExclusive($userId);
$canEarly     = canWatchEarlyAccess($userId);
$quality      = getVideoQuality($userId);
$showAds      = hasAds($userId);

// Filter by type or exclusive
$typeFilter   = isset($_GET['type'])      ? $_GET['type']      : null;
$showList     = isset($_GET['list'])      ? (bool)$_GET['list'] : false;
$exclusiveOnly= isset($_GET['exclusive']) ? (bool)$_GET['exclusive'] : false;

// ----------------------------------------------
// Fetch Featured / Hero Banner (random trending)
// ----------------------------------------------
$heroQuery = "SELECT * FROM content WHERE is_trending = 1";
if (!$canExclusive) $heroQuery .= " AND is_exclusive = 0";
$heroQuery .= " ORDER BY RAND() LIMIT 1";
$hero = db()->query($heroQuery)->fetch();

// ----------------------------------------------
// Fetch Continue Watching (for this profile)
// ----------------------------------------------
$continueWatching = [];
if ($profileId) {
    $stmt = db()->prepare("
        SELECT c.*, wh.progress_seconds, wh.duration_seconds,
               ROUND((wh.progress_seconds / NULLIF(wh.duration_seconds, 0)) * 100) AS pct
        FROM watch_history wh
        JOIN content c ON wh.content_id = c.id
        WHERE wh.profile_id = ?
        ORDER BY wh.last_watched DESC LIMIT 10
    ");
    $stmt->execute([$profileId]);
    $continueWatching = $stmt->fetchAll();
}

// ----------------------------------------------
// Fetch My List
// ----------------------------------------------
$myList = [];
if ($profileId) {
    $stmt = db()->prepare("
        SELECT c.* FROM watchlist wl
        JOIN content c ON wl.content_id = c.id
        WHERE wl.profile_id = ? ORDER BY wl.added_at DESC LIMIT 20
    ");
    $stmt->execute([$profileId]);
    $myList = $stmt->fetchAll();
}

// ----------------------------------------------
// Helper: Build content row query
// ----------------------------------------------
function fetchRow(string $label, string $whereExtra = '', array $params = [], int $limit = 16): array {
    global $canExclusive, $typeFilter, $showList;
    $sql = "SELECT * FROM content WHERE 1=1 $whereExtra";
    if (!$canExclusive) $sql .= " AND is_exclusive = 0";
    if ($typeFilter)    $sql .= " AND type = " . db()->quote($typeFilter);
    $sql .= " ORDER BY RAND() LIMIT $limit";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return ['label' => $label, 'items' => $stmt->fetchAll()];
}

// Build carousel rows
$rows = [];
if ($showList) {
    $rows[] = ['label' => ' My List', 'items' => $myList];
} elseif ($exclusiveOnly && $canExclusive) {
    $rows[] = ['label' => ' StreamVault Originals', 'items' => fetchRow('', 'AND is_exclusive = 1')['items']];
} else {
    if (count($continueWatching) > 0)  $rows[] = ['label' => ' Continue Watching',   'items' => $continueWatching];
    if (count($myList) > 0)            $rows[] = ['label' => ' My List',               'items' => $myList];
    $rows[] = fetchRow(' Trending Now',       'AND is_trending = 1');
    $rows[] = fetchRow(' Top Movies',          'AND type = \'movie\'');
    $rows[] = fetchRow(' Popular Series',      'AND type = \'series\'');
    $rows[] = fetchRow(' Action & Adventure',  'AND genre IN (\'Action\',\'Sci-Fi\')');
    $rows[] = fetchRow(' Crime & Drama',       'AND genre IN (\'Crime\',\'Drama\',\'Thriller\')');
    $rows[] = fetchRow(' Top Rated',           'AND rating >= 8.5');
    if ($canExclusive) {
        $rows[] = fetchRow(' StreamVault Originals', 'AND is_exclusive = 1');
    }
    if ($canEarly)     $rows[] = fetchRow(' Early Access',   'AND is_early_access = 1');
    $rows[] = fetchRow(' International',       'AND language != \'English\'');
}

// Get user watchlist IDs for JS
$watchlistIds = [];
if ($profileId) {
    $stmt = db()->prepare("SELECT content_id FROM watchlist WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $watchlistIds = array_column($stmt->fetchAll(), 'content_id');
}
?>
<?php if (!$profileId): ?>
<!-- No profile selected -->
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:16px;padding-top:80px;">
  <div style="font-size:3rem;">??</div>
  <h2>No profile selected</h2>
  <p style="color:var(--text-muted);">Please select a profile to browse content.</p>
  <a href="/streamvault/profiles.php" class="btn btn-primary">Choose Profile</a>
</div>
<?php else: ?>

<!-- =================== HERO BANNER =================== -->
<?php if ($hero): ?>
<section class="browse-hero" style="
  min-height: 85vh;
  background: linear-gradient(to right, rgba(20,20,20,0.95) 30%, rgba(20,20,20,0.3) 70%, transparent),
              linear-gradient(to top, rgba(20,20,20,1) 0%, transparent 60%),
              url('<?= htmlspecialchars($hero['thumbnail_url']) ?>') center/cover no-repeat;
  display:flex; align-items:flex-end; padding:0 4% 60px;
  position:relative;
">
  <!-- Ad Banner for Free users -->
  <?php if ($showAds): ?>
  <div id="adBanner" style="position:absolute;top:var(--nav-height);left:0;right:0;
       background:rgba(0,0,0,0.9);border-bottom:2px solid var(--accent);
       padding:10px 20px;display:flex;align-items:center;justify-content:space-between;
       font-size:0.85rem;backdrop-filter:blur(4px);z-index:50;">
    <span> <strong style="color:var(--accent)">Ad</strong> — "Download our App today! Streamora Premium."</span>
    <div style="display:flex;align-items:center;gap:12px;">
      <a href="/streamvault/upgrade.php" style="color:var(--gold);font-weight:700;font-size:0.8rem;">
        Skip Ads — Upgrade to Standard
      </a>
      <button onclick="document.getElementById('adBanner').style.display='none'"
              style="color:#888;font-size:0.9rem;cursor:pointer;"></button>
    </div>
  </div>
  <?php endif; ?>

  <div class="hero-info" style="max-width:550px;">
    <?php if ($hero['is_exclusive']): ?>
      <span style="background:var(--gold);color:#000;font-size:0.7rem;font-weight:800;
                   padding:4px 10px;border-radius:4px;display:inline-block;margin-bottom:12px;">
         Streamora ORIGINAL
      </span>
    <?php endif; ?>
    <h2 style="font-size:clamp(2rem,5vw,4rem);font-weight:900;line-height:1.1;margin-bottom:12px;
               text-shadow:2px 2px 20px rgba(0,0,0,0.8);">
      <?= htmlspecialchars($hero['title']) ?>
    </h2>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap;">
      <span style="color:var(--gold);font-weight:700;">★ <?= $hero['rating'] ?></span>
      <span style="color:var(--text-muted);"><?= $hero['release_year'] ?></span>
      <span style="color:var(--text-muted);"><?= ucfirst($hero['type']) ?></span>
      <span class="tier-badge" style="background:<?php
        echo match($quality) { '4K' => 'var(--gold)', '1080p' => 'var(--silver)', default => '#6b6b6b' };
      ?>;color:<?= $quality === '4K' ? '#000' : '#fff' ?>;"><?= $quality ?></span>
    </div>
    <p style="color:var(--text-secondary);font-size:0.95rem;line-height:1.6;margin-bottom:24px;
              text-shadow:1px 1px 8px rgba(0,0,0,0.9);">
      <?= htmlspecialchars(substr($hero['description'], 0, 180)) ?>…
    </p>
    <div style="display:flex;gap:12px;flex-wrap:nowrap;align-items:center;">
      <a href="/streamvault/watch.php?id=<?= $hero['id'] ?>" class="btn btn-primary btn-lg">
        &#9654; Play Now
      </a>
      <button class="btn btn-secondary btn-lg" id="heroWatchlist"
              data-id="<?= $hero['id'] ?>"
              data-in-list="<?= in_array($hero['id'], $watchlistIds) ? 1 : 0 ?>"
              style="width:160px;white-space:nowrap;">
        <?= in_array($hero['id'], $watchlistIds) ? '&#10003; In My List' : '+ My List' ?>
      </button>
      <a href="/streamvault/watch.php?id=<?= $hero['id'] ?>&info=1" class="btn btn-secondary btn-lg" style="white-space:nowrap;">
        &#9432; More Info
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =================== CONTENT CAROUSELS =================== -->
<main class="browse-main">
  <?php foreach ($rows as $row):
    if (empty($row['items'])) continue; ?>
  <section class="carousel-section">
    <h3 class="carousel-label"><?= htmlspecialchars($row['label']) ?></h3>
    <div class="carousel-wrapper">
      <button class="carousel-arrow left" aria-label="Scroll left">‹</button>
      <div class="carousel-track">
        <?php foreach ($row['items'] as $item):
          $access = isContentAccessible($userId, $item);
          $locked = !$access['accessible'];
          $inList = in_array($item['id'], $watchlistIds);
          $progress = $item['progress_seconds'] ?? 0;
          $duration = $item['duration_seconds'] ?? 0;
        ?>
        <div class="content-card" data-id="<?= $item['id'] ?>"
             onclick="<?= $locked ? 'showUpgradeModal()' : "window.location='/streamvault/watch.php?id={$item['id']}&info=1'" ?>"
             style="cursor:pointer;">
          <a href="<?= $locked ? '#' : '/streamvault/watch.php?id=' . $item['id'] . '&info=1' ?>"
             <?= $locked ? 'onclick="event.preventDefault();showUpgradeModal()"' : 'onclick="event.stopPropagation()"' ?>>
            <img
              src="<?= htmlspecialchars($item['thumbnail_url']) ?>"
              alt="<?= htmlspecialchars($item['title']) ?>"
              loading="lazy"
              onerror="this.src='https://via.placeholder.com/300x450/1f1f1f/666?text=🎬'"
            />
          </a>

          <!-- Ribbons -->
          <?php if ($item['is_exclusive']): ?>
            <div class="exclusive-ribbon">EXCLUSIVE</div>
          <?php endif; ?>
          <?php if ($item['is_early_access']): ?>
            <div class="new-ribbon">EARLY</div>
          <?php endif; ?>
          <?php if ($item['is_trending'] && !$item['is_exclusive']): ?>
            <div class="new-ribbon">TRENDING</div>
          <?php endif; ?>

          <!-- Progress bar for Continue Watching -->
          <?php if ($progress > 0 && $duration > 0): ?>
          <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:rgba(255,255,255,0.15);">
            <div style="height:100%;background:var(--accent);width:<?= min(100, round($progress/$duration*100)) ?>%;"></div>
          </div>
          <?php endif; ?>

          <!-- Lock overlay -->
          <?php if ($locked): ?>
          <div class="lock-overlay" onclick="showUpgradeModal()">
            <span class="lock-icon">🔒</span>
            <span class="lock-badge"><?= $access['required_plan'] ?></span>
            <span style="font-size:0.7rem;padding:0 10px;">Upgrade to unlock</span>
          </div>
          <?php else: ?>
          <!-- Hover overlay -->
          <div class="card-overlay">
            <div class="card-title"><?= htmlspecialchars($item['title']) ?></div>
            <div class="card-meta">
              <span><?= htmlspecialchars($item['genre']) ?></span>·
              <span class="card-rating">★ <?= $item['rating'] ?></span>·
              <span><?= $item['release_year'] ?></span>
            </div>
            <div class="card-actions">
              <a href="/streamvault/watch.php?id=<?= $item['id'] ?>" class="card-btn play" title="Play">▶</a>
              <button class="card-btn watchlist-btn"
                      data-id="<?= $item['id'] ?>"
                      data-in-list="<?= $inList ? 1 : 0 ?>"
                      title="<?= $inList ? 'Remove from list' : 'Add to list' ?>"
                      onclick="event.stopPropagation()">
                <?= $inList ? '&#10003;' : '+' ?>
              </button>
              <a href="/streamvault/watch.php?id=<?= $item['id'] ?>&info=1" class="card-btn" title="More info">ⓘ</a>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="carousel-arrow right" aria-label="Scroll right">›</button>
    </div>
  </section>
  <?php endforeach; ?>
</main>

<!-- =================== UPGRADE MODAL =================== -->
<div id="upgradeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);
     z-index:3000;align-items:center;justify-content:center;backdrop-filter:blur(8px);">
  <div style="background:var(--bg-card);border:1px solid var(--gold);border-radius:var(--radius-lg);
              padding:40px;max-width:420px;width:90%;text-align:center;">
    <div style="font-size:3rem;margin-bottom:16px;"></div>
    <h2 style="font-size:1.6rem;margin-bottom:8px;">Premium Exclusive</h2>
    <p style="color:var(--text-secondary);margin-bottom:24px;">
      This content is only available to Premium subscribers. Upgrade to unlock all exclusives, 4K quality, and more.
    </p>
    <a href="/streamvault/upgrade.php" class="btn btn-gold btn-block" style="margin-bottom:12px;">
       Upgrade to Premium
    </a>
    <button onclick="document.getElementById('upgradeModal').style.display='none'"
            class="btn btn-secondary btn-block">Maybe Later</button>
  </div>
</div>

<script>
// Watchlist IDs from PHP
const myWatchlistIds = new Set(<?= json_encode($watchlistIds) ?>);
const PROFILE_ID = <?= $profileId ?? 'null' ?>;

function showUpgradeModal() {
  document.getElementById('upgradeModal').style.display = 'flex';
}

// Close upgrade modal on backdrop click
document.getElementById('upgradeModal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget)
    e.currentTarget.style.display = 'none';
});

// Card watchlist buttons
document.querySelectorAll('.watchlist-btn').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    e.stopPropagation();
    if (!PROFILE_ID) return;
    const id     = btn.dataset.id;
    const inList = btn.dataset.inList === '1';
    const fd     = new FormData();
    fd.append('content_id', id);
    fd.append('action', inList ? 'remove' : 'add');
    try {
      const res  = await fetch('/streamvault/api/watchlist.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        btn.dataset.inList = inList ? '0' : '1';
        btn.textContent    = inList ? '+' : '\u2713';
        btn.title          = inList ? 'Add to list' : 'Remove from list';
      }
    } catch (e) { console.error(e); }
  });
});

// Hero watchlist button
const heroWL = document.getElementById('heroWatchlist');
if (heroWL) {
  heroWL.addEventListener('click', async () => {
    const id     = heroWL.dataset.id;
    const inList = heroWL.dataset.inList === '1';
    const action = inList ? 'remove' : 'add';
    const fd     = new FormData();
    fd.append('content_id', id);
    fd.append('action',     action);
    const res = await fetch('/streamvault/api/watchlist.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
      heroWL.dataset.inList = inList ? '0' : '1';
      heroWL.textContent    = inList ? '+ My List' : '✓ In My List';
    }
  });
}
</script>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>


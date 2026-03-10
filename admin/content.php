<?php
// =============================================================
// StreamVault � Admin: Content Management (CRUD)
// =============================================================
$pageTitle = 'Content — Admin';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$msg = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $stmt = db()->prepare("
        INSERT INTO content (title, description, genre, type, thumbnail_url, trailer_url, release_year, rating, duration_min, total_episodes, is_exclusive, is_early_access, is_trending, is_featured, language)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        trim($_POST['title']), trim($_POST['description']), trim($_POST['genre']),
        $_POST['type'], trim($_POST['thumbnail_url']), trim($_POST['trailer_url']),
        (int)$_POST['release_year'], (float)$_POST['rating'],
        $_POST['type']==='movie' ? (int)$_POST['duration_min'] : null,
        $_POST['type']==='series' ? (int)$_POST['total_episodes'] : null,
        isset($_POST['is_exclusive'])?1:0, isset($_POST['is_early_access'])?1:0,
        isset($_POST['is_trending'])?1:0, isset($_POST['is_featured'])?1:0,
        trim($_POST['language'] ?: 'English')
    ]);
    $msg = 'success';
}
// Handle Delete
if (isset($_GET['delete'])) {
    db()->prepare("DELETE FROM content WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: /streamvault/admin/content.php');
    exit;
}
// Handle Toggle Trending
if (isset($_GET['toggle_trending'])) {
    $id = (int)$_GET['toggle_trending'];
    db()->prepare("UPDATE content SET is_trending = 1 - is_trending WHERE id=?")->execute([$id]);
    header('Location: /streamvault/admin/content.php');
    exit;
}

$search     = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12; $offset = ($page-1)*$perPage;
$where  = "WHERE 1=1";
$params = [];
if ($search)     { $where .= " AND (title LIKE ? OR genre LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($typeFilter) { $where .= " AND type = ?"; $params[] = $typeFilter; }

$total = db()->prepare("SELECT COUNT(*) FROM content $where");
$total->execute($params); $total = (int)$total->fetchColumn();
$pages = ceil($total / $perPage);

$contents = db()->prepare("SELECT * FROM content $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$contents->execute($params);
$contents = $contents->fetchAll();
?>
<main style="padding-top:100px;min-height:100vh;padding-bottom:60px;">
<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px;">
    <div>
      <h1 style="font-size:1.6rem;font-weight:800;"> Content Library</h1>
      <p style="color:var(--text-muted);font-size:0.88rem;"><?= $total ?> titles</p>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="/streamvault/admin/index.php" class="btn btn-secondary btn-sm"> Dashboard</a>
      <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary btn-sm">
        + Add Title
      </button>
    </div>
  </div>
  <?php if ($msg === 'success'): ?>
    <div class="flash-message flash-success" style="margin-bottom:16px;"> Title added successfully!</div>
  <?php endif; ?>

  <!-- Filter Bar -->
  <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
    <input class="form-control" type="text" name="search" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search title or genre…" style="max-width:250px;" />
    <select name="type" style="max-width:140px;background:var(--bg-card);color:var(--text-primary);
            border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;
            font-size:0.95rem;font-family:inherit;cursor:pointer;outline:none;">
      <option value="" style="background:var(--bg-card);">All Types</option>
      <option value="movie"  <?= $typeFilter==='movie'?'selected':'' ?> style="background:var(--bg-card);">Movies</option>
      <option value="series" <?= $typeFilter==='series'?'selected':'' ?> style="background:var(--bg-card);">Series</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  </form>

  <!-- Content Table -->
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:var(--bg-hover);">
          <?php foreach (['', 'Title', 'Genre', 'Type', 'Year', 'Rating', 'Excl.', 'Trending', ''] as $h): ?>
          <th style="padding:10px 12px;text-align:left;font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;"><?= $h ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contents as $c): ?>
        <tr style="border-top:1px solid var(--border);">
          <td style="padding:8px 12px;width:44px;">
            <img src="<?= htmlspecialchars($c['thumbnail_url']) ?>" alt="" style="width:36px;height:54px;object-fit:cover;border-radius:3px;"
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzYiIGhlaWdodD0iNTQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjM2IiBoZWlnaHQ9IjU0IiBmaWxsPSIjMWYxZjFmIi8+PC9zdmc+'">
          </td>
          <td style="padding:8px 12px;font-size:0.88rem;font-weight:600;max-width:200px;">
            <?= htmlspecialchars(mb_strimwidth($c['title'], 0, 35, '…')) ?>
            <?php if ($c['is_exclusive']): ?>
            <span style="background:var(--gold);color:#000;font-size:0.58rem;font-weight:800;padding:1px 5px;border-radius:2px;margin-left:4px;">EXC</span>
            <?php endif; ?>
          </td>
          <td style="padding:8px 12px;font-size:0.82rem;color:var(--text-muted);"><?= htmlspecialchars($c['genre']) ?></td>
          <td style="padding:8px 12px;"><span style="background:rgba(255,255,255,0.08);padding:2px 8px;border-radius:3px;font-size:0.75rem;"><?= $c['type'] ?></span></td>
          <td style="padding:8px 12px;font-size:0.82rem;color:var(--text-muted);"><?= $c['release_year'] ?></td>
          <td style="padding:8px 12px;color:var(--gold);font-weight:700;font-size:0.82rem;"> <?= $c['rating'] ?></td>
          <td style="padding:8px 12px;text-align:center;"><?= $c['is_exclusive'] ? '✓' : '—' ?></td>
          <td style="padding:8px 12px;text-align:center;">
            <a href="?toggle_trending=<?= $c['id'] ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>"
               style="font-size:0.75rem;color:<?= $c['is_trending'] ? 'var(--success)' : 'var(--text-muted)' ?>;">
              <?= $c['is_trending'] ? 'Yes' : 'No' ?>
            </a>
          </td>
          <td style="padding:8px 12px;">
            <a href="?delete=<?= $c['id'] ?>" style="color:var(--accent);font-size:0.8rem;"
               onclick="return confirm('Delete \'<?= htmlspecialchars(addslashes($c['title'])) ?>\'?')">✕</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($typeFilter) ?>"
       class="btn btn-sm <?= $i===$page?'btn-primary':'btn-secondary' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</main>

<!-- Add Title Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:3000;
     align-items:flex-start;justify-content:center;padding:80px 20px;overflow-y:auto;backdrop-filter:blur(8px);">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);
              padding:36px;max-width:600px;width:100%;">
    <div style="display:flex;justify-content:space-between;margin-bottom:24px;">
      <h2 style="font-size:1.3rem;font-weight:800;">➕ Add New Title</h2>
      <button onclick="document.getElementById('addModal').style.display='none'" style="color:var(--text-muted);font-size:1.4rem;">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Title *</label>
          <input class="form-control" name="title" required placeholder="Movie or Series title" />
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Description *</label>
          <textarea class="form-control" name="description" rows="3" required placeholder="Synopsis…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Genre</label>
          <input class="form-control" name="genre" placeholder="Action, Drama, Sci-Fi…" />
        </div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select name="type" style="background:var(--bg-card);color:var(--text-primary);
                  border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;
                  font-size:0.95rem;font-family:inherit;cursor:pointer;outline:none;width:100%;">
            <option value="movie"  style="background:var(--bg-card);">Movie</option>
            <option value="series" style="background:var(--bg-card);">Series</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Thumbnail URL</label>
          <input class="form-control" name="thumbnail_url" placeholder="https://image.tmdb.org/…" />
        </div>
        <div class="form-group">
          <label class="form-label">Trailer URL (YouTube embed)</label>
          <input class="form-control" name="trailer_url" placeholder="https://www.youtube.com/embed/…" />
        </div>
        <div class="form-group">
          <label class="form-label">Year</label>
          <input class="form-control" type="number" name="release_year" value="<?= date('Y') ?>" min="1900" max="<?= date('Y')+2 ?>" />
        </div>
        <div class="form-group">
          <label class="form-label">Rating (0–10)</label>
          <input class="form-control" type="number" name="rating" step="0.1" min="0" max="10" value="7.5" />
        </div>
        <div class="form-group">
          <label class="form-label">Duration (min, movies only)</label>
          <input class="form-control" type="number" name="duration_min" placeholder="120" />
        </div>
        <div class="form-group">
          <label class="form-label">Episodes (series only)</label>
          <input class="form-control" type="number" name="total_episodes" placeholder="10" />
        </div>
        <div class="form-group">
          <label class="form-label">Language</label>
          <input class="form-control" name="language" value="English" />
        </div>
        <div class="form-group" style="display:flex;flex-direction:column;gap:8px;justify-content:center;">
          <?php foreach (['is_exclusive'=>'Premium Only (Exclusive)','is_early_access'=>'Early Access','is_trending'=>'Trending','is_featured'=>'Featured'] as $name=>$label): ?>
          <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;">
            <input type="checkbox" name="<?= $name ?>" style="accent-color:var(--accent);" />
            <?= $label ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:20px;">Add Title</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


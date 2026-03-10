<?php
// =============================================================
// StreamVault � Header Include
// =============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tier.php';

$currentUser   = getCurrentUser();
$isLoggedIn    = !empty($_SESSION['user_id']);
$isAdmin       = ($currentUser['role'] ?? '') === 'admin';
$activeProfile = getActiveProfile();
$planName      = $isAdmin ? 'Admin' : ($currentUser['plan_name'] ?? 'Free');
$badgeColor    = $isAdmin ? '#f5c518' : ($currentUser['badge_color'] ?? '#6b6b6b');
$initials      = strtoupper(substr($currentUser['user_name'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="streamvault — Subscription-based streaming with tiered membership." />
  <title><?= htmlspecialchars($pageTitle ?? 'streamvault') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/streamvault/assets/css/style.css">
  <?php if (!empty($extraCss)): ?>
    <link rel="stylesheet" href="/streamvault/assets/css/<?= $extraCss ?>">
  <?php endif; ?>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<nav class="navbar" id="mainNav">
  <a href="<?= $isLoggedIn ? '/streamvault/browse.php' : '/streamvault/index.php' ?>" class="navbar-brand">
     Streamora
  </a>

  <?php if ($isLoggedIn): ?>
  <ul class="navbar-nav">
    <li><a href="/streamvault/browse.php">Home</a></li>
    <li><a href="/streamvault/browse.php?type=series">TV Shows</a></li>
    <li><a href="/streamvault/browse.php?type=movie">Movies</a></li>
    <?php if ($planName === 'Premium'): ?>
    <li><a href="/streamvault/browse.php?exclusive=1" style="color:#f5c518"> Originals</a></li>
    <?php endif; ?>
    <li><a href="/streamvault/browse.php?list=1">My List</a></li>
    <li><a href="/streamvault/downloads.php">Downloads</a></li>
  </ul>
  <?php endif; ?>

  <div class="navbar-actions">
    <?php if ($isLoggedIn): ?>
    <!-- Search icon -->
    <button class="nav-search-icon" id="searchToggle" title="Search" aria-label="Search">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
    </button>

    <!-- Avatar Dropdown -->
    <div class="nav-avatar" id="navAvatar" aria-label="Account menu">
      <?= $initials ?>
      <div class="nav-dropdown">
        <div style="padding:10px 16px;border-bottom:1px solid var(--border);font-size:0.82rem;">
          <div style="font-weight:700"><?= htmlspecialchars($currentUser['user_name'] ?? '') ?></div>
          <div>
            <span class="tier-badge" style="background:<?= $badgeColor ?>;font-size:0.65rem;"><?= $planName ?></span>
          </div>
        </div>
        <?php if (!$isAdmin): ?>
        <a href="/streamvault/profiles.php">Switch Profile</a>
        <?php endif; ?>
        <a href="/streamvault/account.php">Account</a>
        <a href="/streamvault/downloads.php">Downloads</a>
        <a href="/streamvault/upgrade.php">Manage Plan</a>
        <?php if ($isAdmin): ?>
          <div class="divider"></div>
          <a href="/streamvault/admin/index.php">Admin Panel</a>
        <?php endif; ?>
        <div class="divider"></div>
        <form method="post" action="/streamvault/logout.php">
          <button type="submit">Sign Out</button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <a href="/streamvault/login.php" class="btn btn-secondary btn-sm">Sign In</a>
    <a href="/streamvault/register.php" class="btn btn-primary btn-sm">Get Started</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ===================== SEARCH OVERLAY ===================== -->
<?php if ($isLoggedIn): ?>
<div class="search-overlay" id="searchOverlay" role="dialog" aria-label="Search">
  <span class="search-close" id="searchClose" aria-label="Close search">✕</span>
  <input type="text" id="searchInput" placeholder="Search movies, series, genres…" autocomplete="off" />
  <div class="search-results" id="searchResults"></div>
</div>
<?php endif; ?>

<!-- ===================== FLASH MESSAGE ===================== -->
<div style="position:relative;z-index:100">
  <?= getFlashMessage() ?>
</div>

<!-- Page content starts here -->


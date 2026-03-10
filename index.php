<?php
// =============================================================
// StreamVault � Landing Page (index.php)
// The "Netflix Homepage" that wows visitors
// =============================================================
$pageTitle = 'streamvault — Unlimited Streaming, Your Tier';
require_once __DIR__ . '/includes/header.php';

// If logged in ? redirect to browse
if ($isLoggedIn) {
    header('Location: /streamvault/browse.php');
    exit;
}

// Fetch plans for pricing section
$plans = db()->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll();

// Fetch featured content (some teasers for home)
$featured = db()->query("
    SELECT id, title, thumbnail_url, genre, rating, type, is_exclusive
    FROM content WHERE is_featured = 1 ORDER BY id LIMIT 8
")->fetchAll();
?>

<!-- =================== HERO =================== -->
<section class="hero">
  <span class="hero-tag"> Premium Streaming · Tiered Membership</span>
  <h1 class="hero-title">
    Unlimited Movies,<br>
    TV Shows & More.<br>
    <span class="brand-red">Watch on Streamora.</span>
  </h1>
  <p class="hero-subtitle">
    Start with our Free tier and upgrade anytime. Cancel whenever you like. No surprises.
  </p>
  <form class="hero-form" action="/streamvault/register.php" method="get" id="heroForm">
    <input type="email" name="email" id="heroEmail" placeholder="Enter your email to get started" autocomplete="email" />
    <button type="submit" class="btn btn-primary btn-lg">
      Get Started &rsaquo;
    </button>
  </form>
  <div class="hero-scroll-hint">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="6 9 12 15 18 9"/>
    </svg>
    <span>Scroll to explore</span>
  </div>
</section>

<!-- =================== FEATURES =================== -->
<section class="features-section">
  <div class="text-center">
    <span class="section-label">Why Streamora?</span>
    <h2 class="section-title">Everything you need, nothing you don't</h2>
  </div>
  <div class="features-grid">
    <div class="feature-card fade-in">
      <span class="feature-icon"></span>
      <h3>Watch Anywhere</h3>
      <p>Stream on your TV, computer, tablet or phone. Perfectly optimized for every screen size.</p>
    </div>
    <div class="feature-card fade-in">
      <span class="feature-icon"></span>
      <h3>Multiple Profiles</h3>
      <p>Create up to 5 viewer profiles per account. Everyone gets their own personalized experience.</p>
    </div>
    <div class="feature-card fade-in">
      <span class="feature-icon"></span>
      <h3>Download & Go</h3>
      <p>Save your favourite shows offline and watch them wherever you are — no Wi-Fi needed.</p>
    </div>
    <div class="feature-card fade-in">
      <span class="feature-icon"></span>
      <h3>4K HDR Quality</h3>
      <p>Experience cinema-grade 4K + HDR quality on your Premium plan. Watch in stunning detail.</p>
    </div>
    <div class="feature-card fade-in">
      <span class="feature-icon"></span>
      <h3>Exclusive Originals</h3>
      <p>Unlock Streamora Originals and early-access titles only available on our Premium tier.</p>
    </div>
    <div class="feature-card fade-in">
      <span class="feature-icon"></span>
      <h3>Ad-Free Streaming</h3>
      <p>Upgrade to Standard or Premium and enjoy completely uninterrupted entertainment.</p>
    </div>
  </div>
</section>

<!-- =================== PRICING =================== -->
<section class="pricing-section">
  <span class="section-label">Membership Plans</span>
  <h2 class="section-title">Choose Your Tier</h2>
  <p class="section-subtitle">Start free or go all in. Upgrade or downgrade anytime — your call.</p>

  <div class="pricing-grid">
    <?php
    $planStyles = [
      'Free'     => ['var(--card-accent:#6b6b6b)', '#6b6b6b', '🆓'],
      'Standard' => ['var(--card-accent:#c0c0c0)', '#c0c0c0', '⭐'],
      'Premium'  => ['var(--card-accent:#f5c518)', '#f5c518', '👑'],
    ];
    foreach ($plans as $plan):
      $style  = $planStyles[$plan['name']] ?? ['#888', '#888', '✅'];
      $color  = $plan['badge_color'];
      $isPopular = $plan['name'] === 'Premium';
    ?>
    <div class="pricing-card <?= $isPopular ? 'popular' : '' ?>"
         style="--card-accent:<?= $color ?>">
      <?php if ($isPopular): ?>
        <div style="position:absolute;top:-1px;left:50%;transform:translateX(-50%);">
          <span style="background:var(--gold);color:#000;font-size:0.65rem;font-weight:800;
                       padding:4px 14px;border-radius:0 0 8px 8px;text-transform:uppercase;letter-spacing:1px;">
            Most Popular
          </span>
        </div>
      <?php endif; ?>

      <div class="plan-badge" style="background:<?= $color ?>"><?= $plan['name'] ?></div>
      <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>

      <div class="plan-price">
        <?php if ($plan['price'] == 0): ?>
          <sup>₹</sup>0 <span>/ forever</span>
        <?php else: ?>
          <sup>₹</sup><?= number_format($plan['price'], 0) ?><span>/ mo</span>
        <?php endif; ?>
      </div>

      <p class="plan-description"><?= htmlspecialchars($plan['description']) ?></p>

      <ul class="plan-features">
        <li class="has"><span class="check-icon">✓</span> <?= htmlspecialchars($plan['quality']) ?> Video Quality</li>
        <li class="has"><span class="check-icon">✓</span> <?= $plan['max_screens'] ?> Simultaneous Screen<?= $plan['max_screens'] > 1 ? 's' : '' ?></li>
        <li class="has"><span class="check-icon">✓</span> <?= $plan['max_profiles'] ?> Viewer Profile<?= $plan['max_profiles'] > 1 ? 's' : '' ?></li>

        <?php if ($plan['max_downloads'] == 0): ?>
          <li><span class="x-icon">✕</span> No Downloads</li>
        <?php elseif ($plan['max_downloads'] == -1): ?>
          <li class="has"><span class="check-icon">✓</span> Unlimited Downloads</li>
        <?php else: ?>
          <li class="has"><span class="check-icon">✓</span> <?= $plan['max_downloads'] ?> Downloads / Month</li>
        <?php endif; ?>

        <?php if ($plan['has_ads']): ?>
          <li><span class="x-icon">✕</span> Includes Ads</li>
        <?php else: ?>
          <li class="has"><span class="check-icon">✓</span> Ad-Free</li>
        <?php endif; ?>

        <?php if ($plan['has_exclusives']): ?>
          <li class="has"><span class="check-icon">✓</span> Exclusive Originals</li>
        <?php else: ?>
          <li><span class="x-icon">✕</span> No Exclusives</li>
        <?php endif; ?>

        <?php if ($plan['early_access']): ?>
          <li class="has"><span class="check-icon">✓</span> Early Access Titles</li>
        <?php else: ?>
          <li><span class="x-icon">✕</span> No Early Access</li>
        <?php endif; ?>
      </ul>

      <a href="/streamvault/register.php?plan=<?= $plan['id'] ?>"
         class="btn btn-block <?= $isPopular ? 'btn-gold' : ($plan['price'] == 0 ? 'btn-secondary' : 'btn-primary') ?>">
        <?= $plan['price'] == 0 ? 'Start for Free' : 'Get ' . $plan['name'] ?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- =================== CONTENT PREVIEW =================== -->
<section style="padding:60px 4%;background:var(--bg-secondary);">
  <span class="section-label">Content Library</span>
  <h2 class="section-title">Start watching today</h2>
  <p class="section-subtitle">Hundreds of titles across every genre. New additions every week.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;max-width:1100px;margin:40px auto 0;">
    <?php foreach ($featured as $item): ?>
    <div class="content-card" onclick="window.location='/streamvault/login.php'" style="cursor:pointer;">
      <img src="<?= htmlspecialchars($item['thumbnail_url']) ?>"
           alt="<?= htmlspecialchars($item['title']) ?>"
           loading="lazy"
           onerror="this.src='https://via.placeholder.com/300x450/1f1f1f/666?text=🎬'">
      <?php if ($item['is_exclusive']): ?>
        <div class="exclusive-ribbon">EXCLUSIVE</div>
      <?php endif; ?>
      <div class="card-overlay">
        <div class="card-title"><?= htmlspecialchars($item['title']) ?></div>
        <div class="card-meta">
          <span><?= htmlspecialchars($item['genre']) ?></span>
          <span>·</span>
          <span class="card-rating">★ <?= $item['rating'] ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-3">
    <a href="/streamvault/register.php" class="btn btn-primary btn-lg">
       Unlock Full Library 
    </a>
  </div>
</section>

<!-- =================== FAQ =================== -->
<section class="faq-section">
  <span class="section-label">FAQ</span>
  <h2 class="section-title" style="text-align:center;">Frequently Asked Questions</h2>
  <div class="faq-list">
    <?php
    $faqs = [
      ["What is Streamora?",
       "streamora is a subscription-based streaming platform offering movies and TV shows across three membership tiers: Free, Standard, and Premium. Each tier unlocks progressively better features."],
      ["Can I change my plan anytime?",
       "Absolutely! You can upgrade or downgrade your plan at any time from your Account settings. Changes take effect immediately. If you downgrade, you won't be charged the difference."],
      ["What's the difference between tiers?",
       "Free gives you 480p SD quality with ads and 1 screen. Standard unlocks HD 1080p, 2 screens, 5 downloads per month, and no ads. Premium gives you 4K HDR, 4 screens, unlimited downloads, exclusive originals, and early access titles."],
      ["How many profiles can I create?",
       "Free accounts get 1 profile. Standard accounts get 2 profiles. Premium accounts can create up to 5 individual profiles, each with their own watch history and watchlist."],
      ["Is streaming actually possible?",
       "streamora simulates a real streaming platform for academic demonstration. Content plays YouTube trailers in a Netflix-style player with quality badges, ad overlays for Free users, and all tier-gating logic working as a real platform would."],
      ["How does the payment work?",
       "Payments are fully simulated for this academic project. Enter any 12+ digit card number in the checkout form — no real charges are made. Transactions are recorded in the database for analytical demonstration."],
    ];
    foreach ($faqs as $i => [$q, $a]): ?>
    <div class="faq-item" id="faq<?= $i ?>">
      <div class="faq-question" onclick="toggleFAQ(<?= $i ?>)">
        <span><?= $q ?></span>
        <span class="faq-plus">+</span>
      </div>
      <div class="faq-answer"><?= $a ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- =================== TESTIMONIALS =================== -->
<section class="testimonials-section">
  <span class="section-label" style="display:block;text-align:center;">What Members Say</span>
  <h2 class="section-title" style="text-align:center;">Loved by viewers</h2>
  <div class="testimonial-grid" style="margin-top:40px;">
    <?php
    $testimonials = [
      ["Upgraded to Premium and the 4K quality is jaw-dropping. The exclusive originals alone are worth it.", "Arjun K.", "Premium Member", "⭐⭐⭐⭐⭐"],
      ["Started with the Free plan to test it out. Within a week I was on Standard — no ads is life-changing!", "Priya M.", "Standard Member", "⭐⭐⭐⭐⭐"],
      ["The multiple profiles feature is brilliant. My whole family uses the same Premium account.", "Rahul S.", "Premium Member", "⭐⭐⭐⭐⭐"],
      ["The download feature saved me on my last flight. Downloaded 3 episodes and watched them offline.", "Sneha R.", "Standard Member", "⭐⭐⭐⭐⭐"],
    ];
    foreach ($testimonials as [$text, $name, $plan, $stars]): ?>
    <div class="testimonial-card fade-in">
      <div class="testimonial-stars"><?= $stars ?></div>
      <div class="testimonial-text">"<?= $text ?>"</div>
      <div class="testimonial-author"><?= $name ?></div>
      <div class="testimonial-plan"><?= $plan ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- =================== CTA BANNER =================== -->
<section style="padding:80px 4%;text-align:center;">
  <h2 style="font-size:clamp(2rem,5vw,3.5rem);font-weight:900;margin-bottom:16px;">
    Ready to watch?
  </h2>
  <p style="color:var(--text-secondary);font-size:1.1rem;margin-bottom:40px;">
    Join Streamora today. Start free, upgrade anytime.
  </p>
  <a href="/streamvault/register.php" class="btn btn-primary btn-lg">
     Start Streaming Now
  </a>
</section>

<script>
function toggleFAQ(index) {
  const item   = document.getElementById('faq' + index);
  const answer = item.querySelector('.faq-answer');
  const isOpen = item.classList.contains('open');

  // Close all
  document.querySelectorAll('.faq-item').forEach(i => {
    i.classList.remove('open');
    i.querySelector('.faq-answer').classList.remove('open');
  });

  if (!isOpen) {
    item.classList.add('open');
    answer.classList.add('open');
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


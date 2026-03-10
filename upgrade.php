<?php
// =============================================================
// StreamVault � Upgrade / Plan Selection Page
// =============================================================
$pageTitle = 'Manage Your Plan — StreamVault';
$extraJs   = 'pricing.js';
require_once __DIR__ . '/includes/header.php';
requireLogin('/streamvault/login.php');

$userId  = $_SESSION['user_id'];
$planNow = getUserPlan($userId);
$plans   = db()->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll();

// Fetch last payment for display
$stmt = db()->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 1");
$stmt->execute([$userId]);
$lastPayment = $stmt->fetch();

// Fetch payment history
$stmt2 = db()->prepare("
    SELECT pay.*, p.name AS plan_name FROM payments pay
    JOIN plans p ON pay.plan_id = p.id
    WHERE pay.user_id = ? ORDER BY pay.payment_date DESC LIMIT 5
");
$stmt2->execute([$userId]);
$payHistory = $stmt2->fetchAll();
?>
<main style="padding-top:calc(var(--nav-height) + 40px);min-height:100vh;padding-bottom:60px;">
  <div class="container">
    <div class="text-center" style="margin-bottom:50px;">
      <span class="section-label">Your Membership</span>
      <h1 class="section-title">Choose Your Plan</h1>
      <p class="section-subtitle">
        <?php if ($isAdmin): ?>
          Role: <strong style="color:#f5c518;">Admin</strong> — Full access to all content
        <?php else: ?>
          Current plan: <strong style="color:<?= $planNow['badge_color'] ?? '#888' ?>;"><?= $planNow['name'] ?? 'Free' ?></strong>
        <?php endif; ?>
      </p>
    </div>

    <!-- Plan Cards -->
    <div class="pricing-grid" style="margin-bottom:60px;">
      <?php
      $badge = ['Free' => '#6b6b6b','Standard' => '#c0c0c0','Premium' => '#f5c518'];
      foreach ($plans as $plan):
        $isCurrent = !$isAdmin && $plan['id'] === ($planNow['id'] ?? 0);
        $isPopular  = $plan['name'] === 'Premium';
        $color      = $plan['badge_color'];
      ?>
      <div class="pricing-card <?= $isCurrent ? 'current-plan' : '' ?> <?= $isPopular ? 'popular' : '' ?>"
           style="--card-accent:<?= $color ?>;">
        <?php if ($isCurrent): ?>
          <div style="position:absolute;top:0;left:50%;transform:translateX(-50%);
               background:var(--success);color:#000;font-size:0.65rem;font-weight:800;
               padding:4px 14px;border-radius:0 0 8px 8px;text-transform:uppercase;letter-spacing:1px;">
            Current Plan
          </div>
        <?php elseif ($isPopular): ?>
          <div style="position:absolute;top:0;left:50%;transform:translateX(-50%);
               background:var(--gold);color:#000;font-size:0.65rem;font-weight:800;
               padding:4px 14px;border-radius:0 0 8px 8px;text-transform:uppercase;letter-spacing:1px;">
            Most Popular
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
          <li class="has"><span class="check-icon">✓</span><?= $plan['quality'] ?> Quality</li>
          <li class="has"><span class="check-icon">✓</span><?= $plan['max_screens'] ?> Screen<?= $plan['max_screens']>1?'s':'' ?></li>
          <li class="has"><span class="check-icon">✓</span><?= $plan['max_profiles'] ?> Profile<?= $plan['max_profiles']>1?'s':'' ?></li>
          <?php if ($plan['max_downloads']==0): ?>
          <li><span class="x-icon">✕</span>No Downloads</li>
          <?php elseif($plan['max_downloads']==-1): ?>
          <li class="has"><span class="check-icon">✓</span>Unlimited Downloads</li>
          <?php else: ?>
          <li class="has"><span class="check-icon">✓</span><?= $plan['max_downloads'] ?> Downloads/mo</li>
          <?php endif; ?>
          <?php if($plan['has_ads']): ?>
          <li><span class="x-icon">✕</span>Includes Ads</li>
          <?php else: ?>
          <li class="has"><span class="check-icon">✓</span>Ad-Free</li>
          <?php endif; ?>
          <?php if($plan['has_exclusives']): ?>
          <li class="has"><span class="check-icon">✓</span>Exclusive Originals</li>
          <?php else: ?>
          <li><span class="x-icon">✕</span>No Exclusives</li>
          <?php endif; ?>
          <?php if($plan['early_access']): ?>
          <li class="has"><span class="check-icon">✓</span>Early Access</li>
          <?php else: ?>
          <li><span class="x-icon">✕</span>No Early Access</li>
          <?php endif; ?>
        </ul>

        <?php if ($isAdmin): ?>
          <?php // Admin: no button shown ?>
        <?php elseif ($isCurrent): ?>
          <button class="btn btn-secondary btn-block" id="cancelBtn" data-plan="<?= $plan['id'] ?>">
            ✕ Cancel Subscription
          </button>
        <?php else: ?>
          <button class="btn btn-block <?= $isPopular ? 'btn-gold' : 'btn-primary' ?>"
                  id="choosePlan-<?= $plan['id'] ?>"
                  onclick="openCheckout(<?= $plan['id'] ?>, '<?= $plan['name'] ?>', <?= $plan['price'] ?>)">
            <?= $plan['price'] == 0 ? 'Downgrade to Free' : 'Subscribe to ' . $plan['name'] ?>
          </button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Payment History -->
    <?php if (count($payHistory) > 0): ?>
    <div style="max-width:700px;margin:0 auto;">
      <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:16px;">Payment History</h3>
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:var(--bg-hover);">
              <th style="padding:12px 16px;text-align:left;font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;">Date</th>
              <th style="padding:12px 16px;text-align:left;font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;">Plan</th>
              <th style="padding:12px 16px;text-align:right;font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;">Amount</th>
              <th style="padding:12px 16px;text-align:left;font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;">TXN ID</th>
              <th style="padding:12px 16px;text-align:left;font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payHistory as $pay): ?>
            <tr style="border-top:1px solid var(--border);">
              <td style="padding:12px 16px;font-size:0.88rem;"><?= date('d M Y', strtotime($pay['payment_date'])) ?></td>
              <td style="padding:12px 16px;font-size:0.88rem;"><?= htmlspecialchars($pay['plan_name']) ?></td>
              <td style="padding:12px 16px;font-size:0.88rem;text-align:right;font-weight:700;">₹<?= number_format($pay['amount'], 0) ?></td>
              <td style="padding:12px 16px;font-size:0.78rem;color:var(--text-muted);font-family:monospace;"><?= htmlspecialchars($pay['txn_id']) ?></td>
              <td style="padding:12px 16px;">
                <span style="background:rgba(70,211,105,0.15);color:#46d369;padding:2px 8px;border-radius:3px;font-size:0.75rem;font-weight:700;">
                  <?= strtoupper($pay['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>

<!-- Checkout Modal -->
<div id="checkoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);
     z-index:3000;align-items:center;justify-content:center;backdrop-filter:blur(8px);">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);
              padding:40px;max-width:480px;width:90%;max-height:90vh;overflow-y:auto;">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <div>
        <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">Subscribe to</div>
        <h2 id="modalPlanName" style="font-size:1.6rem;font-weight:800;margin:0;"></h2>
      </div>
      <button onclick="document.getElementById('checkoutModal').style.display='none'"
              style="color:var(--text-muted);font-size:1.4rem;">✕</button>
    </div>

    <div id="checkoutPrice" style="font-size:2.4rem;font-weight:900;margin-bottom:6px;"></div>
    <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:24px;">Billed monthly · Cancel anytime</div>

   

    <form id="checkoutForm">
      <input type="hidden" name="plan_id" id="checkoutPlanId" />
      <div class="form-group">
        <label class="form-label">Cardholder Name</label>
        <input class="form-control" type="text" name="card_name" placeholder="Name on card" value="<?= htmlspecialchars($currentUser['user_name'] ?? '') ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Card Number</label>
        <input class="form-control" type="text" id="cardNum" name="card_number"
               placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="cc-number" />
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label">Expiry</label>
          <input class="form-control" type="text" name="card_expiry" placeholder="MM / YY" maxlength="7" />
        </div>
        <div class="form-group">
          <label class="form-label">CVV</label>
          <input class="form-control" type="password" name="card_cvv" placeholder="•••" maxlength="4" />
        </div>
      </div>
      <div id="checkoutError" style="display:none;color:#ff6b6b;font-size:0.85rem;margin-bottom:12px;"></div>
      <button type="submit" class="btn btn-primary btn-block btn-lg" id="payBtn">
         Pay Securely
      </button>
    </form>
  </div>
</div>

<!-- Cancel Confirmation Modal -->
<div id="cancelModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);
     z-index:3000;align-items:center;justify-content:center;backdrop-filter:blur(8px);">
  <div style="background:var(--bg-card);border:1px solid var(--danger);border-radius:var(--radius-lg);
              padding:40px;max-width:420px;width:90%;text-align:center;">
    <div style="font-size:3rem;margin-bottom:16px;">⚠️</div>
    <h2 style="margin-bottom:8px;">Cancel Subscription?</h2>
    <p style="color:var(--text-secondary);margin-bottom:24px;">
      You'll be moved to the Free plan immediately. Your watch history and profiles will be kept.
    </p>
    <div style="display:flex;gap:10px;">
      <button id="confirmCancel" class="btn btn-block" style="background:var(--danger);color:#fff;">
        Yes, Cancel
      </button>
      <button onclick="document.getElementById('cancelModal').style.display='none'"
              class="btn btn-secondary btn-block">Keep Subscription</button>
    </div>
  </div>
</div>

<script src="/streamvault/assets/js/pricing.js" type="module"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>


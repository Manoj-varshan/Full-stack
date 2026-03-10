<?php // StreamVault — Footer Include ?>
<footer class="site-footer">
  <div class="footer-brand">Streamora</div>
  <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:32px;">
    Subscription-Based Membership Engine with Tiered Logic
  </p>
  <div class="footer-grid">
    <div class="footer-col">
      <h4>streamora</h4>
      <a href="/streamvault/browse.php">Home</a>
      <a href="/streamvault/upgrade.php">Pricing</a>
      <a href="/streamvault/account.php">My Account</a>
    </div>
    <div class="footer-col">
      <h4>Browse</h4>
      <a href="/streamvault/browse.php?type=movie">Movies</a>
      <a href="/streamvault/browse.php?type=series">TV Shows</a>
      <a href="/streamvault/browse.php?exclusive=1">Originals</a>
    </div>
    <div class="footer-col">
      <h4>Plans</h4>
      <a href="/streamvault/upgrade.php">Free</a>
      <a href="/streamvault/upgrade.php">Standard</a>
      <a href="/streamvault/upgrade.php">Premium</a>
    </div>
    <div class="footer-col">
      <h4>Help</h4>
      <a href="#">FAQ</a>
      <a href="#">Contact Us</a>
      <a href="#">Privacy Policy</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> Streamora. College Academic Project.</p>
    
  </div>
</footer>

<!-- ===================== GLOBAL JS ===================== -->
<script src="/streamvault/assets/js/main.js" type="module"></script>
<?php if (!empty($extraJs)): ?>
  <script src="/streamvault/assets/js/<?= $extraJs ?>" type="module"></script>
<?php endif; ?>
</body>
</html>


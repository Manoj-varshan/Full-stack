<?php
// =============================================================
// StreamVault � Register Page
// =============================================================
session_start();
if (!empty($_SESSION['user_id'])) { header('Location: /streamvault/browse.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error   = '';
$success = '';
$prefillEmail = htmlspecialchars($_GET['email'] ?? '');
$prefillPlan  = (int)($_GET['plan'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$name || !$email || !$pass || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check email uniqueness
        $chk = db()->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'This email is already registered. <a href="/streamvault/login.php" style="color:#fff;text-decoration:underline;">Sign in?</a>';
        } else {
            // Insert user (triggers auto-assign Free plan + default profile)
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $ins  = db()->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $ins->execute([$name, $email, $hash]);
            $newUserId = (int)db()->lastInsertId();

            // Log in the new user
            $user = db()->prepare("SELECT * FROM users WHERE id = ?")->execute([$newUserId]);
            $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$newUserId]);
            $newUser = $stmt->fetch();
            loginUser($newUser);

            // Redirect to profiles page
            header('Location: /streamvault/profiles.php?new=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — StreamVault</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/streamvault/assets/css/style.css">
</head>
<body>
<nav class="navbar scrolled">
  <a href="/streamvault/index.php" class="navbar-brand">Streamora</a>
</nav>

<main class="auth-page" style="min-height:100vh;align-items:flex-start;padding-top:100px;">
  <div class="auth-card" style="max-width:480px;">
    <h1>Create Account</h1>
    <p class="subtitle">Start streaming in under a minute</p>

    <?php if ($error): ?>
      <div class="flash-message flash-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="registerForm" novalidate>
      <div class="form-group">
        <label class="form-label" for="name">Full Name</label>
        <input class="form-control" type="text" id="name" name="name"
               placeholder="Your name" autocomplete="name" required />
      </div>
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input class="form-control" type="email" id="email" name="email"
               value="<?= $prefillEmail ?>"
               placeholder="you@example.com" autocomplete="email" required />
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password"
               placeholder="Min. 6 characters" autocomplete="new-password" required />
        <div class="password-strength" id="passStrength" style="margin-top:6px;display:none;">
          <div style="height:3px;border-radius:2px;background:var(--border);overflow:hidden;">
            <div id="strengthBar" style="height:100%;width:0;transition:all 0.3s;border-radius:2px;"></div>
          </div>
          <span id="strengthLabel" style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;display:block;"></span>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm Password</label>
        <input class="form-control" type="password" id="confirm_password" name="confirm_password"
               placeholder="Repeat your password" autocomplete="new-password" required />
        <div class="form-error" id="confirmError" style="display:none;">Passwords do not match.</div>
      </div>

      

      <div class="form-group" style="display:flex;align-items:flex-start;gap:10px;">
        <input type="checkbox" id="terms" name="terms" required
               style="width:16px;height:16px;margin-top:3px;flex-shrink:0;accent-color:var(--accent);" />
        <label for="terms" style="font-size:0.83rem;color:var(--text-secondary);cursor:pointer;">
          I agree to the Terms of Service and Privacy Policy
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" id="registerBtn">
        Create Account →
      </button>
    </form>

    <div class="form-divider">Already a member?</div>
    <a href="/streamvault/login.php" class="btn btn-secondary btn-block">Sign In</a>
  </div>
</main>

<script>
const passInput    = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const strengthDiv  = document.getElementById('passStrength');
const strengthBar  = document.getElementById('strengthBar');
const strengthLbl  = document.getElementById('strengthLabel');
const confirmErr   = document.getElementById('confirmError');

passInput.addEventListener('input', () => {
  const val = passInput.value;
  strengthDiv.style.display = val ? 'block' : 'none';
  let score = 0;
  if (val.length >= 6) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const colors = ['#E50914', '#f5c518', '#46ae70', '#46d369'];
  const labels = ['Weak', 'Fair', 'Good', 'Strong'];
  const widths = ['25%', '50%', '75%', '100%'];
  const idx    = Math.max(0, score - 1);
  strengthBar.style.width      = widths[idx];
  strengthBar.style.background = colors[idx];
  strengthLbl.textContent      = labels[idx];
});

confirmInput.addEventListener('input', () => {
  confirmErr.style.display = confirmInput.value &&
    confirmInput.value !== passInput.value ? 'block' : 'none';
});

document.getElementById('registerForm').addEventListener('submit', (e) => {
  const terms = document.getElementById('terms');
  if (!terms.checked) {
    e.preventDefault();
    alert('Please accept the terms to continue.');
    return;
  }
  if (confirmInput.value !== passInput.value) {
    e.preventDefault();
    return;
  }
  const btn = document.getElementById('registerBtn');
  btn.textContent = 'Creating account…';
  btn.disabled    = true;
});
</script>
</body>
</html>


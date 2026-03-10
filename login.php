<?php
// =============================================================
// StreamVault � Login Page
// =============================================================
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? '/streamvault/admin/' : '/streamvault/profiles.php'));
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$prefillEmail = htmlspecialchars($_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            loginUser($user);
            // Auto-set default profile for admin (skips profile selection page)
            if ($user['role'] === 'admin') {
                $pStmt = db()->prepare(
                    "SELECT id FROM profiles WHERE user_id = ? ORDER BY is_default DESC LIMIT 1"
                );
                $pStmt->execute([$user['id']]);
                $profileId = $pStmt->fetchColumn();
                if ($profileId) $_SESSION['profile_id'] = $profileId;
            }
            header('Location: ' . ($user['role'] === 'admin' ? '/streamvault/admin/' : '/streamvault/profiles.php'));
            exit;
        } else {
            $error = 'Incorrect email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Streamora</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/streamvault/assets/css/style.css">
</head>
<body>
<!-- Minimal nav on auth pages -->
<nav class="navbar scrolled">
  <a href="/streamvault/index.php" class="navbar-brand">Streamora</a>
</nav>

<main class="auth-page" style="min-height:100vh;">
  <div class="auth-card" style="margin-top:var(--nav-height);">
    <h1>Sign In</h1>
    <p class="subtitle">Welcome back to Streamora</p>

    <?php if ($error): ?>
      <div class="flash-message flash-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm" novalidate>
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input
          class="form-control"
          type="email"
          id="email"
          name="email"
          value="<?= $prefillEmail ?>"
          placeholder="you@example.com"
          autocomplete="email"
          required
        />
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div style="position:relative;">
          <input
            class="form-control"
            type="password"
            id="password"
            name="password"
            placeholder="Your password"
            autocomplete="current-password"
            required
            style="padding-right:50px;"
          />
          <button type="button" id="togglePass"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);
                   background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.1rem;">
            👁
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
        Sign In
      </button>
    </form>

    <div class="form-divider">or</div>

    <p style="text-align:center;font-size:0.9rem;color:var(--text-secondary);">
      New to Streamora?
      <a href="/streamvault/register.php" style="color:var(--text-primary);font-weight:700;text-decoration:underline;">
        Sign up now
      </a>
    </p>

    <div style="margin-top:24px;padding:14px;background:rgba(255,255,255,0.05);
                border-radius:var(--radius);font-size:0.82rem;color:var(--text-muted);">
      <strong style="color:var(--text-secondary);">Demo Credentials:</strong><br>
      Admin: admin@streamvault.com / Admin@123<br>
      User: demo@streamvault.com / Demo@123
    </div>
  </div>
</main>

<script>
// Toggle password visibility
document.getElementById('togglePass').addEventListener('click', () => {
  const input = document.getElementById('password');
  input.type = input.type === 'password' ? 'text' : 'password';
});

// Client-side validation
document.getElementById('loginForm').addEventListener('submit', (e) => {
  const email = document.getElementById('email').value.trim();
  const pass  = document.getElementById('password').value;
  if (!email || !pass) {
    e.preventDefault();
    alert('Please fill in all fields.');
    return;
  }
  const btn = document.getElementById('loginBtn');
  btn.textContent = 'Signing in…';
  btn.classList.add('loading');
  btn.disabled = true;
});
</script>
</body>
</html>


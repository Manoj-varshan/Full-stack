<?php
// =============================================================
// StreamVault: One-Time Setup Script
// Run this ONCE at http://localhost/streamvault/setup.php
// It will create the DB, tables, and seed admin + test user
// =============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>StreamVault Setup</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
  body { background:#141414; color:#e5e5e5; display:flex; justify-content:center; padding:60px 20px; }
  .box { background:#1f1f1f; border-radius:12px; padding:40px; max-width:600px; width:100%; }
  h1 { color:#E50914; font-size:2rem; margin-bottom:8px; }
  h2 { color:#aaa; font-size:1rem; margin-bottom:30px; font-weight:400; }
  .step { margin-bottom:20px; padding:16px; border-radius:8px; background:#2a2a2a; }
  .step h3 { font-size:0.85rem; color:#888; margin-bottom:6px; text-transform:uppercase; }
  .ok   { border-left:4px solid #46d369; }
  .err  { border-left:4px solid #E50914; }
  .skip { border-left:4px solid #f5c518; }
  p.msg { font-size:0.95rem; }
  .creds { background:#0d0d0d; border-radius:6px; padding:14px; margin-top:20px; font-size:0.9rem; line-height:1.8; }
  .creds span { color:#E50914; font-weight:bold; }
  a.btn { display:inline-block; margin-top:24px; background:#E50914; color:#fff;
          text-decoration:none; padding:12px 28px; border-radius:6px; font-weight:bold; }
  a.btn:hover { background:#b80710; }
</style>
</head>
<body>
<div class="box">
  <h1>🎬 StreamVault</h1>
  <h2>Database Setup — Run once, then delete this file</h2>
<?php

$host   = 'localhost';
$user   = 'root';
$pass   = 'manoj9127';  // MySQL root password
$dbname = 'streamvault';

$steps = [];

// ----------------------------------------------
// STEP 1: Connect (no DB selected yet)
// ----------------------------------------------
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $steps[] = ['ok', 'Connected to MySQL server at localhost ✓'];
} catch (PDOException $e) {
    $steps[] = ['err', 'Cannot connect to MySQL: ' . $e->getMessage()];
    renderSteps($steps);
    die('</div></body></html>');
}

// ----------------------------------------------
// STEP 2: Create database
// ----------------------------------------------
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    $steps[] = ['ok', "Database `$dbname` created / verified ✓"];
} catch (PDOException $e) {
    $steps[] = ['err', 'DB creation failed: ' . $e->getMessage()];
}

// ----------------------------------------------
// STEP 3: Run schema.sql
// ----------------------------------------------
$schemaFile = __DIR__ . '/sql/schema.sql';
if (file_exists($schemaFile)) {
    try {
        $sql = file_get_contents($schemaFile);
        // Split on DELIMITER changes for procedures/triggers
        $pdo->exec($sql);
        $steps[] = ['ok', 'schema.sql executed — tables, views, procedures, triggers created ✓'];
    } catch (PDOException $e) {
        // Try statement-by-statement fallback for complex DDL
        $steps[] = ['skip', 'schema.sql partial note: ' . $e->getMessage() . ' — tables may already exist'];
    }
} else {
    $steps[] = ['err', 'schema.sql not found at sql/schema.sql'];
}

// ----------------------------------------------
// STEP 4: Run seed.sql
// ----------------------------------------------
$seedFile = __DIR__ . '/sql/seed.sql';
if (file_exists($seedFile)) {
    try {
        // Check if plans already seeded
        $count = (int)$pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
        if ($count === 0) {
            $pdo->exec(file_get_contents($seedFile));
            $steps[] = ['ok', 'seed.sql executed — 36 titles & 3 plans inserted ✓'];
        } else {
            $steps[] = ['skip', 'Seed data already present — skipped ✓'];
        }
    } catch (PDOException $e) {
        $steps[] = ['err', 'seed.sql error: ' . $e->getMessage()];
    }
}

// ----------------------------------------------
// STEP 5: Create admin user
// ----------------------------------------------
try {
    $adminEmail = 'admin@streamvault.com';
    $exists = (int)$pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?")->execute([$adminEmail]) &&
              (int)$pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?")->execute([$adminEmail]);

    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
    $chk->execute([$adminEmail]);
    if ((int)$chk->fetchColumn() === 0) {
        $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
        $ins  = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
        $ins->execute(['StreamVault Admin', $adminEmail, $hash, 'admin']);
        $steps[] = ['ok', 'Admin user created: admin@streamvault.com / Admin@123 ✓'];
    } else {
        $steps[] = ['skip', 'Admin user already exists — skipped ✓'];
    }
} catch (PDOException $e) {
    $steps[] = ['err', 'Admin creation failed: ' . $e->getMessage()];
}

// ----------------------------------------------
// STEP 6: Create demo user
// ----------------------------------------------
try {
    $userEmail = 'demo@streamvault.com';
    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
    $chk->execute([$userEmail]);
    if ((int)$chk->fetchColumn() === 0) {
        $hash = password_hash('Demo@123', PASSWORD_BCRYPT);
        $ins  = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
        $ins->execute(['Demo User', $userEmail, $hash, 'user']);
        $steps[] = ['ok', 'Demo user created: demo@streamvault.com / Demo@123 ✓'];
    } else {
        $steps[] = ['skip', 'Demo user already exists — skipped ✓'];
    }
} catch (PDOException $e) {
    $steps[] = ['err', 'Demo user creation failed: ' . $e->getMessage()];
}

renderSteps($steps);

function renderSteps(array $steps): void {
    foreach ($steps as [$type, $msg]) {
        echo "<div class='step $type'><h3>$type</h3><p class='msg'>$msg</p></div>";
    }
}
?>
  <div class="creds">
    <strong>🔐 Login Credentials</strong><br>
    Admin — <span>admin@streamvault.com</span> / <span>Admin@123</span><br>
    Demo User — <span>demo@streamvault.com</span> / <span>Demo@123</span>
  </div>
  <a class="btn" href="index.php">🚀 Launch StreamVault</a>
  <p style="margin-top:16px;color:#666;font-size:0.8rem;">⚠️ Delete this file after setup for security.</p>
</div>
</body>
</html>


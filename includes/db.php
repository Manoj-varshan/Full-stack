<?php
// =============================================================
// streamvault Configuration & DB Connection (PDO)
// =============================================================

define('DB_HOST',   'localhost');
define('DB_NAME',   'streamvault');
define('DB_USER',   'root');
define('DB_PASS',   'manoj9127');          // Change this if your MySQL has a password
define('DB_CHARSET','utf8mb4');

define('SITE_NAME', 'streamvault');
define('SITE_URL',  'http://localhost/streamvault');

// PDO Singleton
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

<?php
session_start();

// ── Database connection ───────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'safenest');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// ── Auto-install schema on first boot ────────────────────────
(function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            username      VARCHAR(100) UNIQUE NOT NULL,
            password      VARCHAR(255) NOT NULL,
            role          ENUM('parent','child') NOT NULL,
            parent_id     INT NULL DEFAULT NULL,
            monitor_token VARCHAR(64) NULL DEFAULT NULL,
            email         VARCHAR(255) NULL DEFAULT NULL,
            phone         VARCHAR(20) NULL DEFAULT NULL,
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS threat_events (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            child_id    INT NOT NULL,
            threat_type VARCHAR(100) NOT NULL,
            severity    ENUM('low','medium','high') NOT NULL,
            context     TEXT NOT NULL,
            status      ENUM('new','reviewed','resolved') DEFAULT 'new',
            timestamp   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sites (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            child_id    INT NOT NULL,
            url         VARCHAR(255) NOT NULL,
            category    VARCHAR(100) DEFAULT 'Divers',
            is_blocked  TINYINT(1) DEFAULT 0,
            timestamp   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("DROP TRIGGER IF EXISTS after_site_insert");

    // Upgrade existing installations silently
    foreach (['parent_id INT NULL DEFAULT NULL', 'monitor_token VARCHAR(64) NULL DEFAULT NULL', 'avatar VARCHAR(255) DEFAULT \'avatar1.png\'', 'email VARCHAR(255) NULL DEFAULT NULL', 'phone VARCHAR(20) NULL DEFAULT NULL'] as $col) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN $col"); } catch (PDOException) {}
    }
    
    // Switch to Alertes dedicated table
    try {
        $pdo->exec("ALTER TABLE threat_events DROP FOREIGN KEY fk_threat_site");
        $pdo->exec("ALTER TABLE threat_events DROP COLUMN site_id");
    } catch (PDOException $e) {}

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alertes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            child_id INT NOT NULL,
            site_id INT NOT NULL,
            status ENUM('new', 'reviewed', 'resolved') DEFAULT 'new',
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
})($pdo);

// ── Auth helpers ──────────────────────────────────────────────
function logged_in(): bool  { return isset($_SESSION['user_id']); }

function require_role(string $role): void {
    if (!logged_in() || $_SESSION['role'] !== $role) {
        header('Location: index.php'); exit;
    }
}

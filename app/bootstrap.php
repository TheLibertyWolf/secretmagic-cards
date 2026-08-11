<?php
declare(strict_types=1);

if (!defined('CARDS_CONFIG_PATH')) {
    define('CARDS_CONFIG_PATH', dirname(__DIR__) . '/cards.secretmagic.config.php');
}

function cards_config(): array
{
    static $config;
    if ($config === null) {
        $config = require CARDS_CONFIG_PATH;
    }
    return $config;
}

function cards_security_headers(bool $admin = false): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self'");
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if ($admin) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    } else {
        header('X-Robots-Tag: noindex, noarchive, nosnippet');
    }
}

function cards_db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = cards_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );
    $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);

    cards_migrate($pdo);
    return $pdo;
}

function cards_migrate(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS cards_admins (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        account_role VARCHAR(20) NOT NULL DEFAULT 'admin',
        must_change_password TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $roleColumn = $pdo->query("SHOW COLUMNS FROM cards_admins LIKE 'account_role'")->fetch();
    if (!$roleColumn) {
        $pdo->exec("ALTER TABLE cards_admins ADD COLUMN account_role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER password_hash");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS cards_short_links (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        suit VARCHAR(12) NOT NULL,
        rank_value VARCHAR(8) NOT NULL,
        visual_style VARCHAR(20) NOT NULL,
        max_visits INT UNSIGNED NULL,
        visit_count INT UNSIGNED NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_visited_at DATETIME NULL,
        INDEX idx_cards_links_created (created_at),
        INDEX idx_cards_links_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cards_nfc_links (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        suit VARCHAR(12) NOT NULL,
        rank_value VARCHAR(8) NOT NULL,
        visual_style VARCHAR(20) NOT NULL,
        visit_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        opened_at DATETIME NULL,
        INDEX idx_cards_nfc_created (created_at),
        INDEX idx_cards_nfc_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cards_nfc_sdm_profiles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        profile_token CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        nickname VARCHAR(80) NOT NULL DEFAULT 'Puce NFC',
        master_key_encrypted VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        suit VARCHAR(12) NOT NULL,
        rank_value VARCHAR(8) NOT NULL,
        visual_style VARCHAR(20) NOT NULL,
        tag_variant VARCHAR(24) NOT NULL DEFAULT 'ntag424dna',
        active TINYINT(1) NOT NULL DEFAULT 1,
        scan_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_scanned_at DATETIME NULL,
        INDEX idx_cards_sdm_created (created_at),
        INDEX idx_cards_sdm_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $nicknameColumn = $pdo->query("SHOW COLUMNS FROM cards_nfc_sdm_profiles LIKE 'nickname'")->fetch();
    if (!$nicknameColumn) {
        $pdo->exec("ALTER TABLE cards_nfc_sdm_profiles ADD COLUMN nickname VARCHAR(80) NOT NULL DEFAULT 'Puce NFC' AFTER profile_token");
    }

    $archivedColumn = $pdo->query("SHOW COLUMNS FROM cards_nfc_sdm_profiles LIKE 'archived_at'")->fetch();
    if (!$archivedColumn) {
        $pdo->exec("ALTER TABLE cards_nfc_sdm_profiles ADD COLUMN archived_at DATETIME NULL AFTER last_scanned_at, ADD INDEX idx_cards_sdm_archived (archived_at)");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS cards_custom_styles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        archived_at DATETIME NULL,
        source_title VARCHAR(160) NULL,
        source_url VARCHAR(500) NULL,
        source_author VARCHAR(160) NULL,
        source_license VARCHAR(80) NULL,
        INDEX idx_cards_custom_styles_active (active),
        CONSTRAINT fk_cards_custom_style_admin FOREIGN KEY (created_by) REFERENCES cards_admins(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cards_custom_cards (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        style_id BIGINT UNSIGNED NOT NULL,
        suit VARCHAR(12) NOT NULL,
        rank_value VARCHAR(8) NOT NULL,
        image_filename VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        image_width SMALLINT UNSIGNED NULL,
        image_height SMALLINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cards_custom_card (style_id, suit, rank_value),
        INDEX idx_cards_custom_card_style (style_id),
        CONSTRAINT fk_cards_custom_card_style FOREIGN KEY (style_id) REFERENCES cards_custom_styles(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach ([
        'source_title' => "VARCHAR(160) NULL AFTER archived_at",
        'source_url' => "VARCHAR(500) NULL AFTER source_title",
        'source_author' => "VARCHAR(160) NULL AFTER source_url",
        'source_license' => "VARCHAR(80) NULL AFTER source_author",
    ] as $column => $definition) {
        if (!$pdo->query("SHOW COLUMNS FROM cards_custom_styles LIKE " . $pdo->quote($column))->fetch()) {
            $pdo->exec("ALTER TABLE cards_custom_styles ADD COLUMN $column $definition");
        }
    }
    foreach ([
        'image_width' => "SMALLINT UNSIGNED NULL AFTER image_filename",
        'image_height' => "SMALLINT UNSIGNED NULL AFTER image_width",
    ] as $column => $definition) {
        if (!$pdo->query("SHOW COLUMNS FROM cards_custom_cards LIKE " . $pdo->quote($column))->fetch()) {
            $pdo->exec("ALTER TABLE cards_custom_cards ADD COLUMN $column $definition");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS cards_nfc_sdm_scans (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        profile_id BIGINT UNSIGNED NOT NULL,
        tag_uid CHAR(14) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        read_counter INT UNSIGNED NOT NULL,
        opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cards_sdm_scan (profile_id, tag_uid, read_counter),
        INDEX idx_cards_sdm_uid (tag_uid),
        CONSTRAINT fk_cards_sdm_profile FOREIGN KEY (profile_id) REFERENCES cards_nfc_sdm_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cards_login_attempts (
        ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        blocked_until DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $config = cards_config();
    if (!empty($config['initial_admin_user']) && !empty($config['initial_admin_hash'])) {
        $insert = $pdo->prepare('INSERT IGNORE INTO cards_admins (username, password_hash, account_role) VALUES (?, ?, ?)');
        $insert->execute([$config['initial_admin_user'], $config['initial_admin_hash'], 'admin']);
    }
    $done = true;
}

function cards_start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('CARDS_ADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function cards_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function cards_verify_csrf(): void
{
    $provided = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals(cards_csrf_token(), $provided)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page.');
    }
}

function cards_admin(): ?array
{
    if (empty($_SESSION['admin_id']) || !is_int($_SESSION['admin_id'])) {
        return null;
    }
    $query = cards_db()->prepare('SELECT id, username, account_role, must_change_password, last_login_at FROM cards_admins WHERE id = ? LIMIT 1');
    $query->execute([$_SESSION['admin_id']]);
    $admin = $query->fetch();
    return $admin ?: null;
}

function cards_client_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', (string) $ip);
}

function cards_login_wait_seconds(PDO $pdo): int
{
    $query = $pdo->prepare('SELECT blocked_until FROM cards_login_attempts WHERE ip_hash = ? AND blocked_until > NOW()');
    $query->execute([cards_client_ip_hash()]);
    $blockedUntil = $query->fetchColumn();
    if (!$blockedUntil) {
        return 0;
    }
    return max(1, strtotime((string) $blockedUntil) - time());
}

function cards_record_login_failure(PDO $pdo): void
{
    $hash = cards_client_ip_hash();
    $query = $pdo->prepare("INSERT INTO cards_login_attempts (ip_hash, attempt_count, last_attempt_at, blocked_until)
        VALUES (?, 1, NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            attempt_count = IF(last_attempt_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1, attempt_count + 1),
            blocked_until = IF(attempt_count >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), blocked_until),
            last_attempt_at = NOW()");
    $query->execute([$hash]);
}

function cards_clear_login_failures(PDO $pdo): void
{
    $query = $pdo->prepare('DELETE FROM cards_login_attempts WHERE ip_hash = ?');
    $query->execute([cards_client_ip_hash()]);
}

function cards_generate_code(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $length = strlen($alphabet);
    $check = $pdo->prepare('SELECT 1 FROM cards_short_links WHERE code = ? LIMIT 1');

    for ($attempt = 0; $attempt < 40; $attempt++) {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $alphabet[random_int(0, $length - 1)];
        }
        $check->execute([$code]);
        if (!$check->fetchColumn()) {
            return $code;
        }
    }
    throw new RuntimeException('Impossible de générer un code unique.');
}

function cards_generate_nfc_token(PDO $pdo): string
{
    $check = $pdo->prepare('SELECT 1 FROM cards_nfc_links WHERE token = ? LIMIT 1');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $token = bin2hex(random_bytes(16));
        $check->execute([$token]);
        if (!$check->fetchColumn()) {
            return $token;
        }
    }
    throw new RuntimeException('Impossible de générer une URL NFC unique.');
}

function cards_h(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

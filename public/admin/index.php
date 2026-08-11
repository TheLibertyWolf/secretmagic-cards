<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/cards_entry.php';
require_once CARDS_APP_PATH . '/admin_helpers.php';

cards_security_headers(true);
cards_start_admin_session();

try {
    $pdo = cards_db();
} catch (Throwable $exception) {
    error_log('Cards admin database error: ' . $exception->getMessage());
    http_response_code(503);
    exit('Le tableau de bord est momentanément indisponible.');
}

$admin = cards_admin();
$loginError = null;

if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    cards_verify_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    cards_admin_redirect('/admin/');
}

if (!$admin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    cards_verify_csrf();
    if (cards_login_wait_seconds($pdo) > 0) {
        $loginError = 'Trop de tentatives. Réessayez dans quelques minutes.';
    } else {
        $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $query = $pdo->prepare('SELECT id, password_hash FROM cards_admins WHERE username = ? LIMIT 1');
        $query->execute([$username]);
        $candidate = $query->fetch();
        if ($candidate && password_verify($password, $candidate['password_hash'])) {
            cards_clear_login_failures($pdo);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $candidate['id'];
            unset($_SESSION['csrf_token']);
            $pdo->prepare('UPDATE cards_admins SET last_login_at = NOW() WHERE id = ?')->execute([$candidate['id']]);
            cards_admin_redirect('/admin/');
        }
        cards_record_login_failure($pdo);
        usleep(random_int(250000, 550000));
        $loginError = 'Identifiant ou mot de passe incorrect.';
    }
}

if (!$admin):
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="theme-color" content="#0b0e14">
    <title>Connexion — Secret Magic Cards</title><link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="login-page"><main class="login-shell">
    <div class="brand-mark" aria-hidden="true">♠</div><p class="eyebrow">Secret Magic Cards</p><h1>Espace privé</h1>
    <p class="muted">Connectez-vous pour préparer et suivre vos révélations.</p>
    <?php if ($loginError): ?><div class="notice error" role="alert"><?= cards_h($loginError) ?></div><?php endif; ?>
    <form method="post" class="login-form" autocomplete="on">
        <input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>">
        <label>Identifiant<input name="username" autocomplete="username" required autofocus maxlength="80"></label>
        <label>Mot de passe<input type="password" name="password" autocomplete="current-password" required maxlength="200"></label>
        <button class="primary wide" type="submit">Entrer</button>
    </form>
</main></body></html>
<?php
exit;
endif;

$stats = $pdo->query("SELECT COUNT(*) AS total_links, COALESCE(SUM(visit_count),0) AS total_visits, COALESCE(SUM(active=1),0) AS active_links, COALESCE(SUM(max_visits IS NOT NULL AND visit_count>=max_visits),0) AS exhausted_links FROM cards_short_links")->fetch();
$recentLinks = $pdo->query('SELECT code, suit, rank_value, visit_count, max_visits, active FROM cards_short_links ORDER BY id DESC LIMIT 5')->fetchAll();

cards_admin_page_start('Vue d’ensemble', 'dashboard', $admin);
?>
<section class="welcome"><div><p class="eyebrow">Vue d’ensemble</p><h1>Bonjour, <?= cards_h($admin['username']) ?></h1><p class="page-intro">Tout ce qu’il faut pour préparer votre prochain effet.</p></div><span class="status-dot">Système actif</span></section>
<section class="stats-grid" aria-label="Statistiques">
    <article><span>Liens créés</span><strong><?= (int) $stats['total_links'] ?></strong></article>
    <article><span>Visites</span><strong><?= (int) $stats['total_visits'] ?></strong></article>
    <article><span>Liens actifs</span><strong><?= (int) $stats['active_links'] ?></strong></article>
    <article><span>Épuisés</span><strong><?= (int) $stats['exhausted_links'] ?></strong></article>
</section>
<section class="quick-grid">
    <a class="quick-card" href="/admin/generator.php"><span>✦</span><div><strong>Créer une carte</strong><small>Aperçu, lien et QR code</small></div><b>→</b></a>
    <a class="quick-card" href="/admin/nfc.php"><span>⌁</span><div><strong>Programmer une puce</strong><small>Préparer une NTAG 424</small></div><b>→</b></a>
    <a class="quick-card" href="/admin/links.php"><span>↗</span><div><strong>Gérer les liens</strong><small>Visites, limites et réarmement</small></div><b>→</b></a>
</section>
<section class="module"><div class="module-heading"><div><p class="eyebrow">Activité</p><h2>Derniers liens</h2></div><a class="text-link" href="/admin/links.php">Tout voir →</a></div>
    <?php if (!$recentLinks): ?><div class="empty-state"><span>✦</span><p>Votre premier lien apparaîtra ici.</p></div><?php else: ?>
    <div class="recent-list"><?php foreach ($recentLinks as $link): ?><div><span class="playing-suit"><?= cards_h(['coeur'=>'♥','carreau'=>'♦','trefle'=>'♣','pique'=>'♠'][$link['suit']] ?? '♠') ?></span><p><strong><?= cards_h(ucfirst($link['rank_value'])) ?> de <?= cards_h($link['suit']) ?></strong><small>?card=<?= cards_h($link['code']) ?></small></p><b><?= (int) $link['visit_count'] ?> visite<?= (int) $link['visit_count'] > 1 ? 's' : '' ?></b></div><?php endforeach; ?></div>
    <?php endif; ?>
</section>
<?php cards_admin_page_end(); ?>

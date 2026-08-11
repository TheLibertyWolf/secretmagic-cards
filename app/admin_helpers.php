<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function cards_admin_redirect(string $path = '/admin/'): never
{
    header('Location: ' . $path);
    exit;
}

function cards_admin_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function cards_admin_take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function cards_admin_require(): array
{
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
    if (!$admin) {
        cards_admin_redirect('/admin/');
    }
    return [$pdo, $admin];
}

function cards_card_options(?PDO $pdo = null): array
{
    $options = [
        'suits' => ['coeur' => '♥ Cœur', 'carreau' => '♦ Carreau', 'trefle' => '♣ Trèfle', 'pique' => '♠ Pique'],
        'ranks' => ['as' => 'As', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10', 'valet' => 'Valet', 'dame' => 'Dame', 'roi' => 'Roi'],
        'styles' => ['moderne' => 'Moderne', 'classique' => 'Classique', 'minimal' => 'Minimal', 'tetes' => 'Têtes traditionnelles', 'ancien' => 'Ancien Pallas'],
        'availability' => ['moderne' => '*', 'classique' => '*', 'minimal' => '*', 'tetes' => '*', 'ancien' => '*'],
    ];

    if ($pdo) {
        $query = $pdo->query("SELECT s.id, s.name, c.suit, c.rank_value
            FROM cards_custom_styles s
            JOIN cards_custom_cards c ON c.style_id = s.id
            WHERE s.active = 1 AND s.archived_at IS NULL
            ORDER BY s.name, c.id");
        foreach ($query->fetchAll() as $card) {
            $key = 'custom_' . (int) $card['id'];
            $options['styles'][$key] = (string) $card['name'];
            if (!isset($options['availability'][$key]) || $options['availability'][$key] === '*') {
                $options['availability'][$key] = [];
            }
            $options['availability'][$key][] = $card['suit'] . ':' . $card['rank_value'];
        }
    }

    return $options;
}

function cards_card_choice_exists(PDO $pdo, string $suit, string $rank, string $style): bool
{
    $base = cards_card_options();
    if (!isset($base['suits'][$suit], $base['ranks'][$rank])) {
        return false;
    }
    if (isset($base['styles'][$style])) {
        return true;
    }
    if (!preg_match('/^custom_([1-9][0-9]*)$/', $style, $match)) {
        return false;
    }
    $query = $pdo->prepare("SELECT 1 FROM cards_custom_cards c JOIN cards_custom_styles s ON s.id = c.style_id
        WHERE c.style_id = ? AND c.suit = ? AND c.rank_value = ? AND s.active = 1 AND s.archived_at IS NULL LIMIT 1");
    $query->execute([(int) $match[1], $suit, $rank]);
    return (bool) $query->fetchColumn();
}

function cards_style_label(PDO $pdo, string $style): string
{
    $base = cards_card_options();
    if (isset($base['styles'][$style])) {
        return $base['styles'][$style];
    }
    if (preg_match('/^custom_([1-9][0-9]*)$/', $style, $match)) {
        $query = $pdo->prepare('SELECT name FROM cards_custom_styles WHERE id = ? LIMIT 1');
        $query->execute([(int) $match[1]]);
        $name = $query->fetchColumn();
        if ($name !== false) {
            return (string) $name;
        }
    }
    return $style;
}

function cards_app_version(): string
{
    $versionFile = __DIR__ . '/VERSION';
    if (is_file($versionFile) && is_readable($versionFile)) {
        $version = trim((string) file_get_contents($versionFile));
        if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version)) {
            return $version;
        }
    }
    return '2.0.0';
}

function cards_admin_asset(string $path): string
{
    return $path . (str_contains($path, '?') ? '&' : '?') . 'v=' . rawurlencode(cards_app_version());
}

function cards_admin_page_start(string $title, string $active, array $admin): void
{
    $csrf = cards_csrf_token();
    $flash = cards_admin_take_flash();
    $nav = [
        'dashboard' => ['/admin/', 'Vue d’ensemble', '⌂'],
        'generator' => ['/admin/generator.php', 'Générateur', '✦'],
        'links' => ['/admin/links.php', 'Liens courts', '↗'],
        'nfc' => ['/admin/nfc.php', 'NFC 424', '⌁'],
        'styles' => ['/admin/styles.php', 'Styles de cartes', '▣'],
        'account' => ['/admin/account.php', 'Accès', '⚙'],
    ];
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0b0e14">
        <title><?= cards_h($title) ?> — Secret Magic Cards</title>
        <link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/admin.css')) ?>">
        <link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/admin-layout.css')) ?>">
        <link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/about.css')) ?>">
        <?php if ($active === 'nfc'): ?><link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/nfc.css')) ?>"><link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/nfc-modal.css')) ?>"><link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/nfc-archive.css')) ?>"><?php endif; ?>
        <?php if ($active === 'links'): ?><link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/links.css')) ?>"><?php endif; ?>
        <?php if ($active === 'account'): ?><link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/account.css')) ?>"><?php endif; ?>
        <?php if ($active === 'styles'): ?><link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/styles.css')) ?>"><link rel="stylesheet" href="<?= cards_h(cards_admin_asset('/admin/styles-source.css')) ?>"><?php endif; ?>
    </head>
    <body class="dashboard" data-base-url="<?= cards_h(rtrim((string) cards_config()['app_url'], '/')) ?>">
        <header class="topbar">
            <a class="brand" href="/admin/"><span>♠</span> Secret Magic</a>
            <div class="topbar-actions">
                <button class="ghost small about-trigger" type="button" data-open-about><span aria-hidden="true">ⓘ</span> À propos</button>
                <form method="post" action="/admin/">
                    <input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= cards_h($csrf) ?>">
                    <button class="ghost small" type="submit">Déconnexion</button>
                </form>
            </div>
        </header>
        <div class="admin-layout">
            <aside class="sidebar-nav" aria-label="Navigation administration">
                <nav>
                    <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
                        <a href="<?= cards_h($href) ?>"<?= $key === $active ? ' class="current" aria-current="page"' : '' ?>><span aria-hidden="true"><?= $icon ?></span><?= cards_h($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <main class="dashboard-shell page-shell">
                <?php if ($flash): ?><div class="notice <?= cards_h($flash['type']) ?>" role="status"><?= cards_h($flash['message']) ?></div><?php endif; ?>
                <?php if ((int) $admin['must_change_password'] === 1): ?>
                    <div class="notice warning">Votre mot de passe initial est très faible. <a href="/admin/account.php">Modifiez-le maintenant.</a></div>
                <?php endif; ?>
    <?php
}

function cards_admin_page_end(array $scripts = []): void
{
    ?>
            </main>
        </div>
        <?php foreach ($scripts as $script): ?><script src="<?= cards_h(cards_admin_asset($script)) ?>"></script><?php endforeach; ?>
        <dialog class="about-dialog" id="about-dialog" aria-labelledby="about-title">
            <article>
                <button type="button" class="about-close" data-close-about aria-label="Fermer la fenêtre À propos">×</button>
                <div class="about-hero">
                    <div class="about-mark" aria-hidden="true"><i></i><i></i><b>♠</b></div>
                    <div><p class="eyebrow">À propos du logiciel</p><h2 id="about-title">Secret Magic Cards</h2><span class="about-version">Version <?= cards_h(cards_app_version()) ?></span></div>
                </div>
                <p class="about-lead">Une expérience pensée pour les illusionnistes : révéler une carte avec élégance, préparer des liens éphémères et prolonger la magie grâce aux puces NFC sécurisées.</p>
                <div class="about-capabilities"><span>52 cartes</span><span>Révélations privées</span><span>NTAG 424 DNA</span><span>Mobile first</span></div>
                <blockquote>« La meilleure technologie est celle qui disparaît pour laisser toute la place à la magie. »</blockquote>
                <section class="author-card" aria-label="Auteur du logiciel">
                    <div class="author-avatar" aria-hidden="true">TLW</div>
                    <div><small>Créé et maintenu par</small><h3>TheLibertyWolf</h3><p>Conception, développement et expérience magique.</p></div>
                    <a href="https://github.com/TheLibertyWolf/secretmagic-cards" target="_blank" rel="noopener noreferrer" aria-label="Voir Secret Magic Cards sur GitHub">GitHub <span aria-hidden="true">↗</span></a>
                </section>
                <footer><span>© 2026 TheLibertyWolf · Licence propriétaire</span><button class="primary" type="button" data-close-about>Refermer</button></footer>
            </article>
        </dialog>
        <script src="<?= cards_h(cards_admin_asset('/admin/about.js')) ?>"></script>
    </body>
    </html>
    <?php
}

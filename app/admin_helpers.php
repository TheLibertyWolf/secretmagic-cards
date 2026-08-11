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

function cards_card_options(): array
{
    return [
        'suits' => ['coeur' => '♥ Cœur', 'carreau' => '♦ Carreau', 'trefle' => '♣ Trèfle', 'pique' => '♠ Pique'],
        'ranks' => ['as' => 'As', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10', 'valet' => 'Valet', 'dame' => 'Dame', 'roi' => 'Roi'],
        'styles' => ['moderne' => 'Moderne', 'classique' => 'Classique', 'minimal' => 'Minimal', 'tetes' => 'Têtes traditionnelles', 'ancien' => 'Ancien Pallas'],
    ];
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
        <link rel="stylesheet" href="/admin/admin.css">
        <link rel="stylesheet" href="/admin/admin-layout.css">
        <?php if ($active === 'nfc'): ?><link rel="stylesheet" href="/admin/nfc.css"><link rel="stylesheet" href="/admin/nfc-modal.css"><?php endif; ?>
        <?php if ($active === 'links'): ?><link rel="stylesheet" href="/admin/links.css"><?php endif; ?>
        <?php if ($active === 'account'): ?><link rel="stylesheet" href="/admin/account.css"><?php endif; ?>
    </head>
    <body class="dashboard" data-base-url="<?= cards_h(rtrim((string) cards_config()['app_url'], '/')) ?>">
        <header class="topbar">
            <a class="brand" href="/admin/"><span>♠</span> Secret Magic</a>
            <form method="post" action="/admin/">
                <input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= cards_h($csrf) ?>">
                <button class="ghost small" type="submit">Déconnexion</button>
            </form>
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
        <?php foreach ($scripts as $script): ?><script src="<?= cards_h($script) ?>"></script><?php endforeach; ?>
    </body>
    </html>
    <?php
}

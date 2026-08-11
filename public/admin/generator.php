<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/cards_entry.php';
require_once CARDS_APP_PATH . '/admin_helpers.php';
[$pdo, $admin] = cards_admin_require();
$options = cards_card_options($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cards_verify_csrf();
    $suit = isset($_POST['suit']) && is_string($_POST['suit']) ? $_POST['suit'] : '';
    $rank = isset($_POST['rank']) && is_string($_POST['rank']) ? $_POST['rank'] : '';
    $style = isset($_POST['visual_style']) && is_string($_POST['visual_style']) ? $_POST['visual_style'] : '';
    $limitMode = isset($_POST['limit_mode']) && is_string($_POST['limit_mode']) ? $_POST['limit_mode'] : 'unlimited';

    if (!cards_card_choice_exists($pdo, $suit, $rank, $style)) {
        cards_admin_flash('error', 'Les paramètres de la carte sont invalides.');
        cards_admin_redirect('/admin/generator.php');
    }
    $maxVisits = null;
    if ($limitMode === 'unique') {
        $maxVisits = 1;
    } elseif ($limitMode === 'custom') {
        $rawLimit = filter_input(INPUT_POST, 'custom_limit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($rawLimit === false || $rawLimit === null) {
            cards_admin_flash('error', 'La limite doit être comprise entre 1 et 1 000 000.');
            cards_admin_redirect('/admin/generator.php');
        }
        $maxVisits = (int) $rawLimit;
    }

    try {
        $code = cards_generate_code($pdo);
        $pdo->prepare('INSERT INTO cards_short_links (code, suit, rank_value, visual_style, max_visits) VALUES (?, ?, ?, ?, ?)')->execute([$code, $suit, $rank, $style, $maxVisits]);
        cards_admin_flash('success', 'Lien créé : ' . cards_config()['app_url'] . '/?card=' . $code);
    } catch (Throwable $exception) {
        error_log('Cards link creation error: ' . $exception->getMessage());
        cards_admin_flash('error', 'Le lien n’a pas pu être créé. Réessayez.');
    }
    cards_admin_redirect('/admin/generator.php');
}

cards_admin_page_start('Générateur', 'generator', $admin);
?>
<section class="welcome compact"><div><p class="eyebrow">Générateur</p><h1>Préparer une carte</h1><p class="page-intro">Le visuel, le lien et le QR code réagissent à vos choix.</p></div><span class="live-pill">Aperçu en direct</span></section>
<section class="module generator-module solo-module"><div class="generator-grid">
    <form method="post" class="generator-controls" id="card-form">
        <input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>">
        <div class="three-fields">
            <label>Enseigne<select name="suit" id="suit"><?php foreach ($options['suits'] as $value => $label): ?><option value="<?= cards_h($value) ?>"><?= cards_h($label) ?></option><?php endforeach; ?></select></label>
            <label>Valeur<select name="rank" id="rank"><?php foreach ($options['ranks'] as $value => $label): ?><option value="<?= cards_h($value) ?>"><?= cards_h($label) ?></option><?php endforeach; ?></select></label>
            <label>Style<select name="visual_style" id="visual-style"><?php foreach ($options['styles'] as $value => $label): $available = $options['availability'][$value] ?? []; ?><option value="<?= cards_h($value) ?>" data-cards="<?= cards_h($available === '*' ? '*' : implode(',', $available)) ?>"><?= cards_h($label) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="link-output"><span>Lien direct</span><div><input id="direct-link" readonly aria-label="Lien direct"><button type="button" class="copy-button" data-copy-target="direct-link">Copier</button></div></div>
        <div class="qr-row"><div id="qrcode" aria-label="QR code du lien direct"></div><p>Ce QR code correspond au lien direct, sans limite de visites.</p></div>
        <fieldset class="limits"><legend>Créer un lien court</legend>
            <label><input type="radio" name="limit_mode" value="unlimited" checked> Illimité</label>
            <label><input type="radio" name="limit_mode" value="unique"> Visite unique</label>
            <label><input type="radio" name="limit_mode" value="custom"> Limite personnalisée</label>
            <input type="number" name="custom_limit" id="custom-limit" min="1" max="1000000" value="3" aria-label="Nombre maximal de visites" disabled>
        </fieldset>
        <button class="primary" type="submit">Créer le lien court</button>
    </form>
    <div class="preview-wrap"><div class="phone"><iframe id="card-preview" title="Aperçu de la carte"></iframe></div></div>
</div></section>
<?php cards_admin_page_end(['/admin/vendor/qrcode.min.js', '/admin/admin.js']); ?>

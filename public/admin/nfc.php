<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/cards_entry.php';
require_once CARDS_APP_PATH . '/admin_helpers.php';
require_once CARDS_APP_PATH . '/nfc_sdm.php';
[$pdo, $admin] = cards_admin_require();
$options = cards_card_options($pdo);
$baseUrl = rtrim((string) cards_config()['app_url'], '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cards_verify_csrf();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create_sdm') {
        $nickname = isset($_POST['nickname']) && is_string($_POST['nickname']) ? trim($_POST['nickname']) : '';
        $suit = isset($_POST['suit']) && is_string($_POST['suit']) ? $_POST['suit'] : '';
        $rank = isset($_POST['rank']) && is_string($_POST['rank']) ? $_POST['rank'] : '';
        $style = isset($_POST['visual_style']) && is_string($_POST['visual_style']) ? $_POST['visual_style'] : '';
        $variant = isset($_POST['tag_variant']) && is_string($_POST['tag_variant']) ? $_POST['tag_variant'] : '';
        $confirmed = isset($_POST['factory_confirmed']) && $_POST['factory_confirmed'] === '1';

        if ($nickname === '' || mb_strlen($nickname, 'UTF-8') > 80) {
            cards_admin_flash('error', 'Donnez un surnom à la puce (80 caractères maximum).');
        } elseif (!cards_card_choice_exists($pdo, $suit, $rank, $style) || !in_array($variant, ['ntag424dna', 'ntag424tt'], true)) {
            cards_admin_flash('error', 'Les informations de la puce ou de la carte sont invalides.');
        } elseif (!$confirmed) {
            cards_admin_flash('error', 'Confirmez que la puce est neuve ou que vous possédez sa clé actuelle.');
        } else {
            try {
                $profileToken = bin2hex(random_bytes(12));
                $masterKey = random_bytes(16);
                $insert = $pdo->prepare('INSERT INTO cards_nfc_sdm_profiles (profile_token, nickname, master_key_encrypted, suit, rank_value, visual_style, tag_variant) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([$profileToken, $nickname, cards_sdm_encrypt_master_key($masterKey), $suit, $rank, $style, $variant]);
                cards_admin_flash('success', 'La puce « ' . $nickname . ' » est prête à être programmée.');
                cards_admin_redirect('/admin/nfc.php?setup=' . $profileToken);
            } catch (Throwable $exception) {
                error_log('Cards SDM profile creation error: ' . $exception->getMessage());
                cards_admin_flash('error', 'La configuration NFC n’a pas pu être créée.');
            }
        }
        cards_admin_redirect('/admin/nfc.php');
    }

    $id = filter_input(INPUT_POST, 'profile_id', FILTER_VALIDATE_INT);
    if ($id && $action === 'toggle_sdm') {
        $pdo->prepare('UPDATE cards_nfc_sdm_profiles SET active = IF(active=1,0,1) WHERE id = ? AND archived_at IS NULL')->execute([$id]);
        cards_admin_flash('success', 'État de la puce mis à jour.');
    } elseif ($id && $action === 'archive_sdm') {
        $lookup = $pdo->prepare('SELECT nickname FROM cards_nfc_sdm_profiles WHERE id = ? LIMIT 1');
        $lookup->execute([$id]);
        $nickname = $lookup->fetchColumn();
        if ($nickname !== false) {
            $pdo->prepare('UPDATE cards_nfc_sdm_profiles SET archived_at = NOW(), active = 0 WHERE id = ? AND archived_at IS NULL')->execute([$id]);
            cards_admin_flash('success', 'La puce « ' . (string) $nickname . ' » a été archivée. Sa clé maître et son historique sont conservés.');
        }
    } elseif ($id && $action === 'restore_sdm') {
        $pdo->prepare('UPDATE cards_nfc_sdm_profiles SET archived_at = NULL, active = 1 WHERE id = ? AND archived_at IS NOT NULL')->execute([$id]);
        cards_admin_flash('success', 'La puce a été restaurée et réactivée.');
    }
    cards_admin_redirect('/admin/nfc.php');
}

$allProfiles = $pdo->query('SELECT * FROM cards_nfc_sdm_profiles ORDER BY id DESC')->fetchAll();
foreach ($allProfiles as &$profile) {
    try {
        $profile['master_key'] = strtoupper(bin2hex(cards_sdm_decrypt_master_key($profile['master_key_encrypted'])));
    } catch (Throwable $exception) {
        $profile['master_key'] = '';
    }
    $profile['program_url'] = $baseUrl . '/nfc/' . $profile['profile_token'];
}
unset($profile);
$profiles = array_values(array_filter($allProfiles, fn(array $profile): bool => $profile['archived_at'] === null));
$archivedProfiles = array_values(array_filter($allProfiles, fn(array $profile): bool => $profile['archived_at'] !== null));

$setupToken = isset($_GET['setup']) && is_string($_GET['setup']) && preg_match('/^[a-f0-9]{24}$/', $_GET['setup']) ? $_GET['setup'] : '';
$setupProfile = null;
foreach ($allProfiles as $profile) {
    if (hash_equals($profile['profile_token'], $setupToken)) {
        $setupProfile = $profile;
        break;
    }
}
$symbols = ['coeur' => '♥', 'carreau' => '♦', 'trefle' => '♣', 'pique' => '♠'];

cards_admin_page_start('Mes puces NFC 424', 'nfc', $admin);
?>
<section class="welcome compact"><div><p class="eyebrow">NTAG 424 DNA · SDM</p><h1>Mes puces</h1><p class="page-intro">Une puce physique peut être scannée autant de fois que nécessaire. Chaque adresse produite ne révèle la carte qu’une fois.</p></div><span class="nfc-chip"><?= count($profiles) ?> puce<?= count($profiles) > 1 ? 's' : '' ?></span></section>

<section class="tag-grid" aria-label="Puces NFC enregistrées">
    <?php foreach ($profiles as $profile): $red = in_array($profile['suit'], ['coeur', 'carreau'], true); ?>
    <article class="tag-card<?= (int) $profile['active'] === 0 ? ' is-disabled' : '' ?>">
        <div class="tag-card-top"><span class="tag-icon">⌁</span><span class="badge <?= (int) $profile['active'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $profile['active'] === 1 ? 'Active' : 'Inactive' ?></span></div>
        <div><p class="tag-type"><?= $profile['tag_variant'] === 'ntag424tt' ? 'NTAG 424 DNA TagTamper' : 'NTAG 424 DNA' ?></p><h2><?= cards_h($profile['nickname']) ?></h2></div>
        <div class="tag-playing-card<?= $red ? ' red' : '' ?>"><span><?= cards_h($options['ranks'][$profile['rank_value']] ?? ucfirst($profile['rank_value'])) ?></span><b><?= cards_h($symbols[$profile['suit']] ?? '♠') ?></b><small><?= cards_h(cards_style_label($pdo, $profile['visual_style'])) ?></small></div>
        <div class="tag-stats"><div><strong><?= (int) $profile['scan_count'] ?></strong><span>scan<?= (int) $profile['scan_count'] > 1 ? 's' : '' ?> valide<?= (int) $profile['scan_count'] > 1 ? 's' : '' ?></span></div><div><strong><?= $profile['last_scanned_at'] ? cards_h(date('d/m H:i', strtotime($profile['last_scanned_at']))) : '—' ?></strong><span>dernier scan</span></div></div>
        <div class="tag-actions">
            <button type="button" class="primary program-profile" data-id="<?= (int) $profile['id'] ?>" data-nickname="<?= cards_h($profile['nickname']) ?>" data-url="<?= cards_h($profile['program_url']) ?>" data-key="<?= cards_h($profile['master_key']) ?>">Programmer</button>
            <button type="button" class="ghost tag-more" aria-expanded="false" aria-controls="actions-<?= (int) $profile['id'] ?>">•••</button>
            <div class="tag-action-menu" id="actions-<?= (int) $profile['id'] ?>" hidden>
                <form method="post"><input type="hidden" name="action" value="toggle_sdm"><input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><button type="submit"><?= (int) $profile['active'] === 1 ? 'Désactiver' : 'Réactiver' ?></button></form>
                <button type="button" class="archive-profile" data-id="<?= (int) $profile['id'] ?>" data-nickname="<?= cards_h($profile['nickname']) ?>">Archiver…</button>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
    <button type="button" class="tag-card add-tag" id="add-tag"><span>+</span><strong>Ajouter une puce</strong><small>Lancer l’assistant</small></button>
</section>

<?php if ($archivedProfiles): ?>
<section class="archived-tags" aria-labelledby="archived-tags-title">
    <div class="archive-heading"><div><p class="eyebrow">Conservation</p><h2 id="archived-tags-title">Puces archivées</h2></div><span><?= count($archivedProfiles) ?></span></div>
    <p class="archive-intro">Les clés maîtres et l’historique restent disponibles pour reprogrammer une puce. Une puce archivée ne révèle plus de carte tant qu’elle n’est pas restaurée.</p>
    <div class="archived-tag-list">
    <?php foreach ($archivedProfiles as $profile): ?>
        <article><div><strong><?= cards_h($profile['nickname']) ?></strong><small><?= cards_h(cards_style_label($pdo, $profile['visual_style'])) ?> · archivée le <?= cards_h(date('d/m/Y', strtotime($profile['archived_at']))) ?></small></div><div><button type="button" class="ghost small program-profile" data-id="<?= (int) $profile['id'] ?>" data-nickname="<?= cards_h($profile['nickname']) ?>" data-url="<?= cards_h($profile['program_url']) ?>" data-key="<?= cards_h($profile['master_key']) ?>">Voir la clé / programmer</button><form method="post"><input type="hidden" name="action" value="restore_sdm"><input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><button class="primary" type="submit">Restaurer</button></form></div></article>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<dialog class="nfc-dialog" id="nfc-wizard"<?= $setupProfile ? ' data-auto-open="program"' : '' ?>>
    <div class="dialog-bar"><div><p class="eyebrow">Assistant NFC 424</p><h2 id="wizard-title">Ajouter une puce</h2></div><button type="button" class="dialog-close" data-close-dialog aria-label="Fermer">×</button></div>
    <div class="dialog-body">
    <form method="post" id="create-tag-form" class="wizard-form">
        <input type="hidden" name="action" value="create_sdm"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>">
        <div class="wizard-progress" aria-label="Progression"><span class="current">1</span><i></i><span>2</span><i></i><span>3</span></div>

        <section class="wizard-panel current" data-step="1">
            <div class="wizard-copy"><p class="eyebrow">Étape 1 sur 3</p><h3>Quelle est cette puce ?</h3><p>Le surnom sert uniquement à la retrouver facilement dans votre collection.</p></div>
            <label>Surnom ou numéro inscrit<input name="nickname" maxlength="80" required placeholder="Ex. Puce n°12" autocomplete="off"></label>
            <label>Modèle de la puce<select name="tag_variant"><option value="ntag424dna">NXP NTAG 424 DNA</option><option value="ntag424tt">NXP NTAG 424 DNA TagTamper</option></select></label>
            <label class="confirm-line"><input type="checkbox" name="factory_confirmed" value="1" required><span>La puce est neuve, ou je possède sa clé maître actuelle.</span></label>
        </section>

        <section class="wizard-panel" data-step="2" hidden>
            <div class="wizard-copy"><p class="eyebrow">Étape 2 sur 3</p><h3>Quelle carte doit-elle révéler ?</h3><p>Ces réglages resteront associés à cette puce.</p></div>
            <div class="wizard-fields">
                <label>Enseigne<select name="suit" id="nfc-suit"><?php foreach ($options['suits'] as $value => $label): ?><option value="<?= cards_h($value) ?>"><?= cards_h($label) ?></option><?php endforeach; ?></select></label>
                <label>Valeur<select name="rank" id="nfc-rank"><?php foreach ($options['ranks'] as $value => $label): ?><option value="<?= cards_h($value) ?>"><?= cards_h($label) ?></option><?php endforeach; ?></select></label>
                <label>Style<select name="visual_style" id="nfc-style"><?php foreach ($options['styles'] as $value => $label): $available = $options['availability'][$value] ?? []; ?><option value="<?= cards_h($value) ?>" data-cards="<?= cards_h($available === '*' ? '*' : implode(',', $available)) ?>"><?= cards_h($label) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="notice warning">Ne verrouillez pas définitivement la puce pendant les essais. Sa clé de programmation sera créée à l’étape suivante.</div>
        </section>
    </form>

    <section class="wizard-panel program-panel" id="program-panel" hidden>
        <div class="wizard-progress complete" aria-label="Progression"><span>1</span><i></i><span>2</span><i></i><span class="current">3</span></div>
        <div class="wizard-copy"><p class="eyebrow">Étape 3 sur 3</p><h3>Programmer « <span id="program-nickname"><?= $setupProfile ? cards_h($setupProfile['nickname']) : '' ?></span> »</h3><p>Utilisez NFC Developer App sur Android. La version d’essai gratuite demande simplement un CAPTCHA.</p></div>
        <a class="app-install" href="https://play.google.com/store/apps/details?id=com.nfcdeveloperapp" target="_blank" rel="noopener noreferrer"><span class="app-mark">N</span><div><strong>Installer NFC Developer App</strong><small>Application dédiée aux NTAG 424 DNA</small></div><b>↗</b></a>
        <ol class="program-list">
            <li><div><b>URL to be programmed on the tag</b><span>Collez cette adresse :</span></div><div class="nfc-url"><input id="sdm-url" value="<?= $setupProfile ? cards_h($setupProfile['program_url']) : '' ?>" readonly><button type="button" class="copy-button" data-copy-target="sdm-url">Copier</button></div></li>
            <li><div><b>Authentication master key</b><span>Collez la clé secrète :</span></div><div class="nfc-url"><input id="sdm-key" value="<?= $setupProfile ? cards_h($setupProfile['master_key']) : '' ?>" readonly autocomplete="off"><button type="button" class="copy-button" data-copy-target="sdm-key">Copier</button></div></li>
            <li><div><b>Derniers réglages</b><span>Mode AES, LRP désactivé et « Custom data » vide. Entrez le CAPTCHA, appuyez sur « Write NFC tag », puis approchez la puce.</span></div></li>
        </ol>
        <div class="program-test"><strong>Test final</strong><span>Scan → carte. Actualisation → disparition. Nouveau scan → nouvelle révélation.</span></div>
    </section>
    </div>
    <footer class="dialog-footer">
        <button type="button" class="ghost" id="wizard-back" hidden>Précédent</button>
        <span class="dialog-footer-spacer"></span>
        <button type="button" class="primary" id="wizard-next">Suivant</button>
        <button type="submit" class="primary" id="wizard-submit" form="create-tag-form" hidden>Créer et continuer</button>
        <button type="button" class="primary" id="wizard-done" data-close-dialog hidden>Terminé</button>
    </footer>
</dialog>

<dialog class="confirm-dialog" id="archive-dialog">
    <form method="post">
        <input type="hidden" name="action" value="archive_sdm"><input type="hidden" name="profile_id" id="archive-profile-id"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>">
        <span class="danger-icon">↧</span><h2>Archiver cette puce ?</h2><p>« <strong id="archive-profile-name"></strong> » sera rangée à part et désactivée. Sa clé maître et tout son historique resteront conservés et accessibles.</p>
        <div class="confirm-actions"><button type="button" class="ghost" data-close-archive>Annuler</button><button type="submit" class="danger-button">Archiver la puce</button></div>
    </form>
</dialog>
<?php cards_admin_page_end(['/admin/admin.js', '/admin/nfc.js']); ?>

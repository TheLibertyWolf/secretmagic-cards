<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/cards_entry.php';
require_once CARDS_APP_PATH . '/admin_helpers.php';
[$pdo, $admin] = cards_admin_require();
$options = cards_card_options();

function cards_uploaded_image(array $file): GdImage
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name'], $file['size'])) {
        throw new RuntimeException('Sélectionnez une image valide.');
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 8 * 1024 * 1024 || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('La photo doit peser moins de 8 Mo.');
    }
    $info = getimagesize((string) $file['tmp_name']);
    if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Formats acceptés : JPG, PNG et WebP.');
    }
    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000 || $width * $height > 40000000) {
        throw new RuntimeException('Les dimensions de cette photo sont trop importantes.');
    }
    $source = imagecreatefromstring((string) file_get_contents((string) $file['tmp_name']));
    if (!$source) {
        throw new RuntimeException('La photo ne peut pas être lue.');
    }
    if (($info['mime'] ?? '') === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data((string) $file['tmp_name']);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rotated = imagerotate($source, $angle, 0);
            if ($rotated instanceof GdImage) {
                imagedestroy($source);
                $source = $rotated;
            }
        }
    }
    return $source;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cards_verify_csrf();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    try {
        if ($action === 'create_style') {
            $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
            if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 80) {
                throw new RuntimeException('Le nom doit contenir entre 2 et 80 caractères.');
            }
            $insert = $pdo->prepare('INSERT INTO cards_custom_styles (name, created_by) VALUES (?, ?)');
            $insert->execute([$name, $admin['id']]);
            cards_admin_flash('success', 'Le style « ' . $name . ' » a été créé. Ajoutez maintenant ses cartes.');
        } elseif ($action === 'upload_card') {
            $styleId = filter_input(INPUT_POST, 'style_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $suit = isset($_POST['suit']) && is_string($_POST['suit']) ? $_POST['suit'] : '';
            $rank = isset($_POST['rank']) && is_string($_POST['rank']) ? $_POST['rank'] : '';
            if (!$styleId || !isset($options['suits'][$suit], $options['ranks'][$rank])) {
                throw new RuntimeException('La carte choisie est invalide.');
            }
            $styleQuery = $pdo->prepare('SELECT name FROM cards_custom_styles WHERE id = ? AND active = 1 AND archived_at IS NULL LIMIT 1');
            $styleQuery->execute([$styleId]);
            if ($styleQuery->fetchColumn() === false) {
                throw new RuntimeException('Ce style est archivé ou introuvable.');
            }
            $source = cards_uploaded_image($_FILES['card_image'] ?? []);
            $width = imagesx($source);
            $height = imagesy($source);
            $ratio = min(1, 1600 / max(1, $width), 2240 / max(1, $height));
            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($source);

            $uploadRoot = rtrim((string) (cards_config()['custom_cards_path'] ?? ''), DIRECTORY_SEPARATOR);
            if ($uploadRoot === '' || (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0700, true)) || !is_writable($uploadRoot)) {
                imagedestroy($target);
                throw new RuntimeException('Le dossier privé des cartes n’est pas accessible en écriture.');
            }
            $filename = bin2hex(random_bytes(20)) . '.webp';
            $path = $uploadRoot . DIRECTORY_SEPARATOR . $filename;
            if (!imagewebp($target, $path, 90)) {
                imagedestroy($target);
                throw new RuntimeException('La photo n’a pas pu être enregistrée.');
            }
            imagedestroy($target);
            chmod($path, 0600);
            $save = $pdo->prepare("INSERT INTO cards_custom_cards (style_id, suit, rank_value, image_filename)
                VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE image_filename = VALUES(image_filename), updated_at = NOW()");
            $save->execute([$styleId, $suit, $rank, $filename]);
            cards_admin_flash('success', ($options['ranks'][$rank] ?? $rank) . ' de ' . ($options['suits'][$suit] ?? $suit) . ' ajouté au style.');
        } elseif (in_array($action, ['archive_style', 'restore_style'], true)) {
            $styleId = filter_input(INPUT_POST, 'style_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$styleId) {
                throw new RuntimeException('Style invalide.');
            }
            if ($action === 'archive_style') {
                $pdo->prepare('UPDATE cards_custom_styles SET active = 0, archived_at = NOW() WHERE id = ? AND archived_at IS NULL')->execute([$styleId]);
                cards_admin_flash('success', 'Style archivé. Les liens existants continuent de fonctionner.');
            } else {
                $pdo->prepare('UPDATE cards_custom_styles SET active = 1, archived_at = NULL WHERE id = ? AND archived_at IS NOT NULL')->execute([$styleId]);
                cards_admin_flash('success', 'Style restauré.');
            }
        }
    } catch (Throwable $exception) {
        error_log('Cards custom style error: ' . $exception->getMessage());
        cards_admin_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'L’opération a échoué.');
    }
    cards_admin_redirect('/admin/styles.php');
}

$styles = $pdo->query("SELECT s.*, COUNT(c.id) AS card_count FROM cards_custom_styles s LEFT JOIN cards_custom_cards c ON c.style_id = s.id GROUP BY s.id ORDER BY s.archived_at IS NOT NULL, s.id DESC")->fetchAll();
$cardQuery = $pdo->prepare('SELECT * FROM cards_custom_cards WHERE style_id = ? ORDER BY FIELD(rank_value,\'as\',\'2\',\'3\',\'4\',\'5\',\'6\',\'7\',\'8\',\'9\',\'10\',\'valet\',\'dame\',\'roi\'), FIELD(suit,\'coeur\',\'carreau\',\'trefle\',\'pique\')');
$activeStyles = array_values(array_filter($styles, fn(array $style): bool => $style['archived_at'] === null));
$archivedStyles = array_values(array_filter($styles, fn(array $style): bool => $style['archived_at'] !== null));

cards_admin_page_start('Styles de cartes', 'styles', $admin);
?>
<section class="welcome compact"><div><p class="eyebrow">Bibliothèque</p><h1>Styles de cartes</h1><p class="page-intro">Créez un jeu visuel, puis importez uniquement les cartes dont vous disposez. Elles seules seront proposées dans les générateurs.</p></div></section>
<section class="module style-create"><div><h2>Nouveau style</h2><p>Ex. Bicycle bleu, Tarot ancien, Jeu personnel…</p></div><form method="post"><input type="hidden" name="action" value="create_style"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><label>Nom du style<input name="name" minlength="2" maxlength="80" required placeholder="Mon style"></label><button class="primary" type="submit">Créer le style</button></form></section>

<section class="custom-style-list">
<?php if (!$activeStyles): ?><div class="module empty-state"><span>▣</span><p>Aucun style personnalisé pour le moment.</p></div><?php endif; ?>
<?php foreach ($activeStyles as $style): $cardQuery->execute([$style['id']]); $cards = $cardQuery->fetchAll(); ?>
<article class="module custom-style-card">
    <header><div><p class="eyebrow">Style personnalisé</p><h2><?= cards_h($style['name']) ?></h2><span><?= (int) $style['card_count'] ?> carte<?= (int) $style['card_count'] > 1 ? 's' : '' ?> sur 52</span></div><form method="post" class="archive-style-form"><input type="hidden" name="action" value="archive_style"><input type="hidden" name="style_id" value="<?= (int) $style['id'] ?>"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><button class="ghost small" type="submit">Archiver</button></form></header>
    <form method="post" enctype="multipart/form-data" class="card-upload-form"><input type="hidden" name="action" value="upload_card"><input type="hidden" name="style_id" value="<?= (int) $style['id'] ?>"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>">
        <label>Enseigne<select name="suit"><?php foreach ($options['suits'] as $value => $label): ?><option value="<?= cards_h($value) ?>"><?= cards_h($label) ?></option><?php endforeach; ?></select></label>
        <label>Valeur<select name="rank"><?php foreach ($options['ranks'] as $value => $label): ?><option value="<?= cards_h($value) ?>"><?= cards_h($label) ?></option><?php endforeach; ?></select></label>
        <label>Photo de la carte<input type="file" name="card_image" accept="image/jpeg,image/png,image/webp" required></label><button class="primary" type="submit">Ajouter / remplacer</button>
    </form>
    <?php if ($cards): ?><div class="uploaded-cards"><?php foreach ($cards as $card): ?><figure><img src="/card-image.php?id=<?= (int) $card['id'] ?>&v=<?= cards_h(strtotime($card['updated_at'])) ?>" alt="<?= cards_h(($options['ranks'][$card['rank_value']] ?? $card['rank_value']) . ' ' . ($options['suits'][$card['suit']] ?? $card['suit'])) ?>" loading="lazy"><figcaption><?= cards_h($options['ranks'][$card['rank_value']] ?? $card['rank_value']) ?> <?= cards_h($options['suits'][$card['suit']] ?? $card['suit']) ?></figcaption></figure><?php endforeach; ?></div><?php else: ?><p class="style-empty">Ajoutez la première photo : ce style apparaîtra ensuite dans le générateur et le wizard NFC.</p><?php endif; ?>
</article>
<?php endforeach; ?>
</section>

<?php if ($archivedStyles): ?><section class="archived-styles"><div class="archive-heading"><h2>Styles archivés</h2><span><?= count($archivedStyles) ?></span></div><?php foreach ($archivedStyles as $style): ?><article><div><strong><?= cards_h($style['name']) ?></strong><small><?= (int) $style['card_count'] ?> carte<?= (int) $style['card_count'] > 1 ? 's' : '' ?> conservée<?= (int) $style['card_count'] > 1 ? 's' : '' ?></small></div><form method="post"><input type="hidden" name="action" value="restore_style"><input type="hidden" name="style_id" value="<?= (int) $style['id'] ?>"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><button class="ghost small" type="submit">Restaurer</button></form></article><?php endforeach; ?></section><?php endif; ?>
<?php cards_admin_page_end(); ?>

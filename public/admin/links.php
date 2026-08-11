<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/cards_entry.php';
require_once CARDS_APP_PATH . '/admin_helpers.php';
[$pdo, $admin] = cards_admin_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cards_verify_csrf();
    $id = filter_input(INPUT_POST, 'link_id', FILTER_VALIDATE_INT);
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if ($id && $action === 'toggle_link') {
        $pdo->prepare('UPDATE cards_short_links SET active = IF(active=1,0,1) WHERE id = ?')->execute([$id]);
        cards_admin_flash('success', 'État du lien mis à jour.');
    } elseif ($id && $action === 'reset_link') {
        $pdo->prepare('UPDATE cards_short_links SET visit_count=0, active=1, last_visited_at=NULL WHERE id = ?')->execute([$id]);
        cards_admin_flash('success', 'Le lien est réarmé et son compteur remis à zéro.');
    } elseif ($id && $action === 'delete_link') {
        $lookup = $pdo->prepare('SELECT code FROM cards_short_links WHERE id = ? LIMIT 1');
        $lookup->execute([$id]);
        $code = $lookup->fetchColumn();
        if ($code !== false) {
            $pdo->prepare('DELETE FROM cards_short_links WHERE id = ?')->execute([$id]);
            cards_admin_flash('success', 'Le lien ?card=' . (string) $code . ' a été supprimé.');
        }
    }
    cards_admin_redirect('/admin/links.php');
}

$links = $pdo->query('SELECT * FROM cards_short_links ORDER BY id DESC LIMIT 250')->fetchAll();
$baseUrl = rtrim((string) cards_config()['app_url'], '/');
cards_admin_page_start('Liens courts', 'links', $admin);
?>
<section class="welcome compact"><div><p class="eyebrow">Liens courts</p><h1>Gérer les révélations</h1><p class="page-intro">Compteurs, limites et réarmement de vos liens.</p></div><a class="primary-link" href="/admin/generator.php">+ Nouveau lien</a></section>
<section class="module solo-module"><div class="module-heading"><div><h2><?= count($links) ?> lien<?= count($links) > 1 ? 's' : '' ?></h2></div><span>250 derniers maximum</span></div>
<?php if (!$links): ?><div class="empty-state"><span>✦</span><p>Aucun lien pour le moment.</p></div><?php else: ?><div class="table-wrap"><table>
<thead><tr><th>Lien</th><th>Carte</th><th>Visites</th><th>Limite</th><th>État</th><th></th></tr></thead><tbody>
<?php foreach ($links as $link): $url=$baseUrl.'/?card='.$link['code']; $exhausted=$link['max_visits']!==null&&(int)$link['visit_count']>=(int)$link['max_visits']; ?>
<tr><td><div class="short-link"><a href="<?= cards_h($url) ?>" target="_blank" rel="noopener">?card=<?= cards_h($link['code']) ?></a><button type="button" class="icon-copy" data-copy-value="<?= cards_h($url) ?>" aria-label="Copier">⧉</button></div><small><?= cards_h(date('d/m/Y H:i',strtotime($link['created_at']))) ?></small></td>
<td><?= cards_h(ucfirst($link['rank_value'])) ?> · <?= cards_h($link['suit']) ?><small><?= cards_h($link['visual_style']) ?></small></td><td><strong><?= (int)$link['visit_count'] ?></strong></td><td><?= $link['max_visits']===null?'∞':(int)$link['max_visits'] ?></td>
<td><span class="badge <?= ((int)$link['active']===1&&!$exhausted)?'active':'inactive' ?>"><?= $exhausted?'Épuisé':((int)$link['active']===1?'Actif':'Inactif') ?></span></td>
<td><div class="link-actions"><form method="post"><input type="hidden" name="action" value="<?= $exhausted?'reset_link':'toggle_link' ?>"><input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><button class="ghost small" type="submit"><?= $exhausted?'Réarmer':((int)$link['active']===1?'Désactiver':'Réactiver') ?></button></form><button type="button" class="link-delete delete-short-link" data-id="<?= (int) $link['id'] ?>" data-code="<?= cards_h($link['code']) ?>" aria-label="Supprimer le lien ?card=<?= cards_h($link['code']) ?>">Supprimer</button></div></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<dialog class="link-delete-dialog" id="link-delete-dialog"><form method="post"><input type="hidden" name="action" value="delete_link"><input type="hidden" name="link_id" id="delete-link-id"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><span>×</span><h2>Supprimer ce lien ?</h2><p><code>?card=<strong id="delete-link-code"></strong></code> n’existera plus et son compteur de visites sera perdu.</p><div><button type="button" class="ghost" data-cancel-delete>Annuler</button><button type="submit" class="confirm-link-delete">Supprimer définitivement</button></div></form></dialog>
<?php cards_admin_page_end(['/admin/admin.js', '/admin/links.js']); ?>

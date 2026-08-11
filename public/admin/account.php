<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/cards_entry.php';
require_once CARDS_APP_PATH . '/admin_helpers.php';
[$pdo, $admin] = cards_admin_require();
$isAdministrator = ($admin['account_role'] ?? 'admin') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cards_verify_csrf();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'update_self';

    if ($action === 'update_self') {
        $currentPassword = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
        $newUsername = isset($_POST['new_username']) && is_string($_POST['new_username']) ? trim($_POST['new_username']) : '';
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $query = $pdo->prepare('SELECT password_hash FROM cards_admins WHERE id = ? LIMIT 1');
        $query->execute([$admin['id']]);
        $currentHash = $query->fetchColumn();

        if (!$currentHash || !password_verify($currentPassword, (string) $currentHash)) {
            cards_admin_flash('error', 'Le mot de passe actuel est incorrect.');
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $newUsername)) {
            cards_admin_flash('error', 'L’identifiant doit contenir 3 à 50 caractères simples.');
        } elseif ($newPassword !== '' && strlen($newPassword) < 12) {
            cards_admin_flash('error', 'Le nouveau mot de passe doit contenir au moins 12 caractères.');
        } else {
            try {
                if ($newPassword !== '') {
                    $pdo->prepare('UPDATE cards_admins SET username=?, password_hash=?, must_change_password=0 WHERE id=?')->execute([$newUsername, password_hash($newPassword, PASSWORD_DEFAULT), $admin['id']]);
                } else {
                    $pdo->prepare('UPDATE cards_admins SET username=? WHERE id=?')->execute([$newUsername, $admin['id']]);
                }
                cards_admin_flash('success', 'Votre accès a été mis à jour.');
            } catch (PDOException $exception) {
                cards_admin_flash('error', 'Cet identifiant est déjà utilisé.');
            }
        }
        cards_admin_redirect('/admin/account.php');
    }

    if (!$isAdministrator) {
        http_response_code(403);
        exit('Action réservée aux administrateurs.');
    }

    if ($action === 'create_account') {
        $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $role = isset($_POST['account_role']) && is_string($_POST['account_role']) ? $_POST['account_role'] : '';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            cards_admin_flash('error', 'L’identifiant doit contenir 3 à 50 caractères simples.');
        } elseif (strlen($password) < 12) {
            cards_admin_flash('error', 'Le mot de passe doit contenir au moins 12 caractères.');
        } elseif (!in_array($role, ['admin', 'user'], true)) {
            cards_admin_flash('error', 'Le type de compte est invalide.');
        } else {
            try {
                $pdo->prepare('INSERT INTO cards_admins (username, password_hash, account_role, must_change_password) VALUES (?, ?, ?, 0)')->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
                cards_admin_flash('success', 'Le compte « ' . $username . ' » a été créé.');
            } catch (PDOException $exception) {
                cards_admin_flash('error', 'Cet identifiant est déjà utilisé.');
            }
        }
    } else {
        $accountId = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT);
        if (!$accountId || $accountId === (int) $admin['id']) {
            cards_admin_flash('error', 'Vous ne pouvez pas modifier votre propre rôle ou supprimer votre compte ici.');
        } elseif ($action === 'change_role') {
            $role = isset($_POST['account_role']) && is_string($_POST['account_role']) ? $_POST['account_role'] : '';
            if (in_array($role, ['admin', 'user'], true)) {
                $pdo->prepare('UPDATE cards_admins SET account_role = ? WHERE id = ?')->execute([$role, $accountId]);
                cards_admin_flash('success', 'Le type de compte a été mis à jour.');
            }
        } elseif ($action === 'delete_account') {
            $target = $pdo->prepare('SELECT username, account_role FROM cards_admins WHERE id = ? LIMIT 1');
            $target->execute([$accountId]);
            $account = $target->fetch();
            if ($account) {
                if ($account['account_role'] === 'admin' && (int) $pdo->query("SELECT COUNT(*) FROM cards_admins WHERE account_role='admin'")->fetchColumn() <= 1) {
                    cards_admin_flash('error', 'Le dernier administrateur ne peut pas être supprimé.');
                } else {
                    $pdo->prepare('DELETE FROM cards_admins WHERE id = ?')->execute([$accountId]);
                    cards_admin_flash('success', 'Le compte « ' . $account['username'] . ' » a été supprimé.');
                }
            }
        }
    }
    cards_admin_redirect('/admin/account.php');
}

$accounts = $isAdministrator ? $pdo->query('SELECT id, username, account_role, created_at, last_login_at FROM cards_admins ORDER BY id')->fetchAll() : [];
cards_admin_page_start('Accès et comptes', 'account', $admin);
?>
<section class="welcome compact"><div><p class="eyebrow">Sécurité</p><h1>Accès et comptes</h1><p class="page-intro">Gérez votre connexion<?= $isAdministrator ? ' et les personnes autorisées à utiliser l’application' : '' ?>.</p></div><span class="role-pill"><?= $isAdministrator ? 'Administrateur' : 'Utilisateur' ?></span></section>

<section class="module account-module solo-module"><div class="module-heading"><div><h2>Mes identifiants</h2></div><span>Mot de passe haché</span></div>
<form method="post" class="account-form"><input type="hidden" name="action" value="update_self"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>">
<label>Identifiant<input name="new_username" value="<?= cards_h($admin['username']) ?>" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+"></label>
<label>Mot de passe actuel<input type="password" name="current_password" required autocomplete="current-password"></label>
<label>Nouveau mot de passe<input type="password" name="new_password" minlength="12" autocomplete="new-password" placeholder="Laisser vide pour conserver"></label>
<button class="primary" type="submit">Mettre à jour</button></form></section>

<?php if ($isAdministrator): ?>
<section class="module"><div class="module-heading"><div><p class="eyebrow">Équipe</p><h2>Comptes autorisés</h2></div><span><?= count($accounts) ?> compte<?= count($accounts) > 1 ? 's' : '' ?></span></div>
<div class="accounts-list">
<?php foreach ($accounts as $account): ?><article><div class="account-avatar"><?= cards_h(strtoupper(mb_substr($account['username'], 0, 1, 'UTF-8'))) ?></div><div class="account-identity"><strong><?= cards_h($account['username']) ?><?= (int) $account['id'] === (int) $admin['id'] ? ' (vous)' : '' ?></strong><small>Dernière connexion : <?= $account['last_login_at'] ? cards_h(date('d/m/Y H:i', strtotime($account['last_login_at']))) : 'jamais' ?></small></div><span class="badge <?= $account['account_role'] === 'admin' ? 'active' : 'inactive' ?>"><?= $account['account_role'] === 'admin' ? 'Admin' : 'Utilisateur' ?></span>
<?php if ((int) $account['id'] !== (int) $admin['id']): ?><div class="account-actions"><form method="post"><input type="hidden" name="action" value="change_role"><input type="hidden" name="account_id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><select name="account_role" aria-label="Type du compte <?= cards_h($account['username']) ?>"><option value="user"<?= $account['account_role'] === 'user' ? ' selected' : '' ?>>Utilisateur</option><option value="admin"<?= $account['account_role'] === 'admin' ? ' selected' : '' ?>>Administrateur</option></select><button class="ghost small" type="submit">Enregistrer</button></form><button type="button" class="account-delete" data-id="<?= (int) $account['id'] ?>" data-name="<?= cards_h($account['username']) ?>">Supprimer…</button></div><?php endif; ?></article><?php endforeach; ?>
</div></section>

<section class="module"><div class="module-heading"><div><p class="eyebrow">Nouveau</p><h2>Créer un compte</h2></div><span>Accès individuel</span></div>
<form method="post" class="new-account-form"><input type="hidden" name="action" value="create_account"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><label>Identifiant<input name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="off"></label><label>Mot de passe initial<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Type de compte<select name="account_role"><option value="user">Utilisateur</option><option value="admin">Administrateur</option></select></label><button class="primary" type="submit">Créer le compte</button></form>
<div class="role-explain"><div><strong>Administrateur</strong><span>Accès complet, y compris la gestion des comptes.</span></div><div><strong>Utilisateur</strong><span>Cartes, liens et NFC ; uniquement son propre mot de passe.</span></div></div></section>

<dialog class="account-delete-dialog" id="account-delete-dialog"><form method="post"><input type="hidden" name="action" value="delete_account"><input type="hidden" name="account_id" id="delete-account-id"><input type="hidden" name="csrf_token" value="<?= cards_h(cards_csrf_token()) ?>"><span>×</span><h2>Supprimer ce compte ?</h2><p>« <strong id="delete-account-name"></strong> » ne pourra plus se connecter.</p><div><button type="button" class="ghost" data-cancel-account-delete>Annuler</button><button type="submit" class="danger-button">Supprimer définitivement</button></div></form></dialog>
<?php endif; ?>
<?php cards_admin_page_end($isAdministrator ? ['/admin/account.js'] : []); ?>

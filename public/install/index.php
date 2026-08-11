<?php
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store, max-age=0');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('CARDS_INSTALL');
session_set_cookie_params(['lifetime' => 0, 'path' => '/install', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
session_start();

$publicRoot = dirname(__DIR__);
$loaderPath = $publicRoot . '/cards_loader.php';
$defaultPrivateRoot = dirname($publicRoot, 2);
$detectedAppPath = $defaultPrivateRoot . '/cards_app';
$installed = is_file($loaderPath) && is_file($detectedAppPath . '/install.lock');

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
function install_h(string|int $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function install_csrf(): void
{
    $provided = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!hash_equals((string) $_SESSION['install_csrf'], $provided)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page.');
    }
}
function install_inside(string $path, string $root): bool
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $root = rtrim(str_replace('\\', '/', $root), '/');
    return $path === $root || str_starts_with($path . '/', $root . '/');
}

$requirements = [
    ['PHP 8.1 ou supérieur', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
    ['Extension PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'Disponible' : 'Manquante'],
    ['Extension OpenSSL', extension_loaded('openssl'), extension_loaded('openssl') ? 'Disponible' : 'Manquante'],
    ['Extension mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'Disponible' : 'Manquante'],
    ['Extension JSON', extension_loaded('json'), extension_loaded('json') ? 'Disponible' : 'Manquante'],
    ['Dossier public accessible en écriture', is_writable($publicRoot), is_writable($publicRoot) ? 'Oui' : 'Non'],
    ['Dossier privé accessible en écriture', is_writable($defaultPrivateRoot), is_writable($defaultPrivateRoot) ? 'Oui' : 'Non'],
];
$requirementsOk = !in_array(false, array_column($requirements, 1), true);
$step = isset($_GET['step']) ? max(1, min(4, (int) $_GET['step'])) : 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    install_csrf();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'database') {
        $db = [
            'host' => trim((string) ($_POST['db_host'] ?? 'localhost')),
            'port' => (int) ($_POST['db_port'] ?? 3306),
            'name' => trim((string) ($_POST['db_name'] ?? '')),
            'user' => trim((string) ($_POST['db_user'] ?? '')),
            'password' => (string) ($_POST['db_password'] ?? ''),
        ];
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $db['name']) || $db['user'] === '' || $db['host'] === '' || $db['port'] < 1 || $db['port'] > 65535) {
            $error = 'Les paramètres SQL sont invalides.';
            $step = 2;
        } else {
            try {
                new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $_SESSION['install_db'] = $db;
                header('Location: /install/?step=3');
                exit;
            } catch (PDOException $exception) {
                $error = 'Connexion impossible. Vérifiez le serveur, la base, l’utilisateur et le mot de passe.';
                $step = 2;
            }
        }
    } elseif ($action === 'install') {
        $db = $_SESSION['install_db'] ?? null;
        $appPath = rtrim(trim((string) ($_POST['app_path'] ?? '')), '/');
        $configPath = trim((string) ($_POST['config_path'] ?? ''));
        $appUrl = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
        $username = trim((string) ($_POST['admin_user'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');
        $confirmation = (string) ($_POST['admin_password_confirmation'] ?? '');
        if (!is_array($db)) {
            $error = 'Testez d’abord la connexion SQL.';
            $step = 2;
        } elseif (!is_dir($appPath) || !is_file($appPath . '/bootstrap.php')) {
            $error = 'Le dossier privé de l’application est introuvable.';
            $step = 3;
        } elseif ($configPath === '' || install_inside($configPath, $publicRoot)) {
            $error = 'Le fichier de configuration doit être placé hors du dossier public.';
            $step = 3;
        } elseif (!filter_var($appUrl, FILTER_VALIDATE_URL) || !str_starts_with($appUrl, 'https://')) {
            $error = 'L’adresse publique doit être une URL HTTPS valide.';
            $step = 3;
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $error = 'L’identifiant administrateur est invalide.';
            $step = 3;
        } elseif (strlen($password) < 12 || $password !== $confirmation) {
            $error = 'Les mots de passe doivent être identiques et comporter au moins 12 caractères.';
            $step = 3;
        } else {
            try {
                $config = [
                    'db_host' => $db['host'], 'db_port' => $db['port'], 'db_name' => $db['name'],
                    'db_user' => $db['user'], 'db_password' => $db['password'], 'app_url' => $appUrl,
                    'nfc_key_encryption' => bin2hex(random_bytes(32)),
                    'initial_admin_user' => '', 'initial_admin_hash' => '',
                ];
                $configSource = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
                $configDir = dirname($configPath);
                if (!is_dir($configDir) || !is_writable($configDir)) {
                    throw new RuntimeException('Le dossier du fichier de configuration n’est pas accessible en écriture.');
                }
                $temporaryConfig = $configPath . '.tmp-' . bin2hex(random_bytes(4));
                if (file_put_contents($temporaryConfig, $configSource, LOCK_EX) === false || !rename($temporaryConfig, $configPath)) {
                    throw new RuntimeException('Impossible d’écrire le fichier de configuration.');
                }
                chmod($configPath, 0600);
                $loaderSource = "<?php\ndeclare(strict_types=1);\n\ndefine('CARDS_APP_PATH', " . var_export($appPath, true) . ");\ndefine('CARDS_CONFIG_PATH', " . var_export($configPath, true) . ");\n";
                if (file_put_contents($loaderPath, $loaderSource, LOCK_EX) === false) {
                    throw new RuntimeException('Impossible d’écrire le chargeur public.');
                }
                chmod($loaderPath, 0600);
                define('CARDS_APP_PATH', $appPath);
                define('CARDS_CONFIG_PATH', $configPath);
                require_once $appPath . '/bootstrap.php';
                $pdo = cards_db();
                $pdo->prepare('INSERT INTO cards_admins (username, password_hash, account_role, must_change_password) VALUES (?, ?, ?, 0)')->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin']);
                file_put_contents($appPath . '/install.lock', 'installed=' . gmdate('c') . "\n", LOCK_EX);
                unset($_SESSION['install_db']);
                session_regenerate_id(true);
                header('Location: /install/?step=4');
                exit;
            } catch (Throwable $exception) {
                error_log('Cards installation error: ' . $exception->getMessage());
                $error = 'Installation interrompue : ' . $exception->getMessage();
                $step = 3;
            }
        }
    }
}

if ($installed) { $step = 4; }
$dbValues = $_SESSION['install_db'] ?? ['host' => 'localhost', 'port' => 3306, 'name' => '', 'user' => '', 'password' => ''];
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0b0e14"><title>Installation — Secret Magic Cards</title><link rel="stylesheet" href="/install/install.css"></head><body><main class="installer"><header><span>♠</span><div><p>Secret Magic Cards</p><h1>Installation</h1></div></header>
<nav aria-label="Progression"><?php foreach ([1=>'Serveur',2=>'Base SQL',3=>'Compte',4=>'Terminé'] as $number=>$label): ?><div class="<?= $number === $step ? 'current' : ($number < $step ? 'done' : '') ?>"><b><?= $number < $step ? '✓' : $number ?></b><span><?= install_h($label) ?></span></div><?php endforeach; ?></nav>
<?php if ($error): ?><div class="install-error" role="alert"><?= install_h($error) ?></div><?php endif; ?>
<?php if ($step === 1): ?><section><p class="eyebrow">Étape 1 sur 4</p><h2>Vérification du serveur</h2><p class="intro">Tous les éléments requis doivent être disponibles avant de poursuivre.</p><div class="checks"><?php foreach ($requirements as [$label,$ok,$value]): ?><div><b class="<?= $ok?'ok':'bad' ?>"><?= $ok?'✓':'×' ?></b><span><?= install_h($label) ?></span><small><?= install_h($value) ?></small></div><?php endforeach; ?></div><footer><span></span><?php if ($requirementsOk): ?><a class="primary" href="/install/?step=2">Configurer la base SQL</a><?php else: ?><button disabled>Corriger les prérequis</button><?php endif; ?></footer></section>
<?php elseif ($step === 2): ?><form method="post"><input type="hidden" name="csrf" value="<?= install_h($_SESSION['install_csrf']) ?>"><input type="hidden" name="action" value="database"><p class="eyebrow">Étape 2 sur 4</p><h2>Connexion à MySQL</h2><p class="intro">Le test ne modifie encore aucune donnée.</p><div class="fields two"><label>Serveur<input name="db_host" value="<?= install_h($dbValues['host']) ?>" required></label><label>Port<input type="number" name="db_port" value="<?= (int)$dbValues['port'] ?>" min="1" max="65535" required></label><label>Nom de la base<input name="db_name" value="<?= install_h($dbValues['name']) ?>" required></label><label>Utilisateur SQL<input name="db_user" value="<?= install_h($dbValues['user']) ?>" required></label><label class="wide">Mot de passe SQL<input type="password" name="db_password" value="<?= install_h($dbValues['password']) ?>" required autocomplete="new-password"></label></div><footer><a href="/install/?step=1">Précédent</a><button class="primary" type="submit">Tester la connexion</button></footer></form>
<?php elseif ($step === 3): ?><form method="post"><input type="hidden" name="csrf" value="<?= install_h($_SESSION['install_csrf']) ?>"><input type="hidden" name="action" value="install"><p class="eyebrow">Étape 3 sur 4</p><h2>Application et premier compte</h2><p class="intro">Le premier compte possède obligatoirement le rôle administrateur.</p><div class="fields"><label>Adresse publique HTTPS<input type="url" name="app_url" value="<?= install_h((!empty($_SERVER['HTTP_HOST'])?'https://'.$_SERVER['HTTP_HOST']:'')) ?>" required></label><label>Dossier privé de l’application<input name="app_path" value="<?= install_h($detectedAppPath) ?>" required></label><label>Fichier de configuration privé<input name="config_path" value="<?= install_h($defaultPrivateRoot.'/cards.secretmagic.config.php') ?>" required></label><hr><label>Identifiant administrateur<input name="admin_user" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="username"></label><label>Mot de passe administrateur<input type="password" name="admin_password" required minlength="12" autocomplete="new-password"></label><label>Confirmer le mot de passe<input type="password" name="admin_password_confirmation" required minlength="12" autocomplete="new-password"></label></div><footer><a href="/install/?step=2">Précédent</a><button class="primary" type="submit">Installer l’application</button></footer></form>
<?php else: ?><section class="complete"><span>✓</span><p class="eyebrow">Installation terminée</p><h2>La magie peut commencer</h2><p>Le fichier de verrouillage interdit toute nouvelle installation. Connectez-vous avec le compte administrateur créé.</p><footer><span></span><a class="primary" href="/admin/">Ouvrir l’administration</a></footer></section><?php endif; ?>
</main></body></html>

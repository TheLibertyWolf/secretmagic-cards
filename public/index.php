<?php
declare(strict_types=1);

require_once __DIR__ . '/cards_entry.php';
require_once CARDS_APP_PATH . '/bootstrap.php';
require_once CARDS_APP_PATH . '/disappeared.php';
require_once CARDS_APP_PATH . '/nfc_sdm.php';

cards_security_headers();

$shortCode = isset($_GET['card']) && is_string($_GET['card']) ? trim($_GET['card']) : null;
$nfcToken = isset($_GET['nfc']) && is_string($_GET['nfc']) ? trim($_GET['nfc']) : null;
$sdmProfileToken = isset($_GET['sdm_profile']) && is_string($_GET['sdm_profile']) ? strtolower(trim($_GET['sdm_profile'])) : null;
$hasDirectCard = isset($_GET['c']) || isset($_GET['v']) || isset($_GET['s']);

if ($sdmProfileToken !== null) {
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($requestMethod === 'HEAD') {
        http_response_code(204);
        exit;
    }
    if ($requestMethod !== 'GET') {
        header('Allow: GET, HEAD');
        http_response_code(405);
        exit;
    }
    $piccData = isset($_GET['picc_data']) && is_string($_GET['picc_data']) ? $_GET['picc_data'] : '';
    $cmac = isset($_GET['cmac']) && is_string($_GET['cmac']) ? $_GET['cmac'] : '';
    $enc = isset($_GET['enc']) && is_string($_GET['enc']) ? $_GET['enc'] : null;
    if (!preg_match('/^[a-f0-9]{24}$/', $sdmProfileToken) || $piccData === '' || $cmac === '') {
        cards_render_disappeared('missing');
    }

    try {
        $pdo = cards_db();
        $query = $pdo->prepare('SELECT id, master_key_encrypted, suit, rank_value, visual_style FROM cards_nfc_sdm_profiles WHERE profile_token = ? AND active = 1 LIMIT 1');
        $query->execute([$sdmProfileToken]);
        $profile = $query->fetch();
        if (!$profile) {
            cards_render_disappeared('missing');
        }
        $scan = cards_sdm_validate(cards_sdm_decrypt_master_key($profile['master_key_encrypted']), $piccData, $cmac, $enc);

        $pdo->beginTransaction();
        try {
            $consume = $pdo->prepare('INSERT INTO cards_nfc_sdm_scans (profile_id, tag_uid, read_counter) VALUES (?, ?, ?)');
            $consume->execute([$profile['id'], $scan['uid'], $scan['counter']]);
            $pdo->prepare('UPDATE cards_nfc_sdm_profiles SET scan_count = scan_count + 1, last_scanned_at = NOW() WHERE id = ?')->execute([$profile['id']]);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((string) $exception->getCode() === '23000') {
                cards_render_disappeared('expired');
            }
            throw $exception;
        }

        $_GET['c'] = $profile['suit'];
        $_GET['v'] = $profile['rank_value'];
        $_GET['s'] = $profile['visual_style'];
    } catch (InvalidArgumentException $exception) {
        cards_render_disappeared('missing');
    } catch (Throwable $exception) {
        error_log('Cards SDM error: ' . $exception->getMessage());
        cards_render_disappeared('missing');
    }
} elseif ($nfcToken !== null) {
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($requestMethod === 'HEAD') {
        http_response_code(204);
        exit;
    }
    if ($requestMethod !== 'GET') {
        header('Allow: GET, HEAD');
        http_response_code(405);
        exit;
    }
    if (!preg_match('/^[a-f0-9]{32}$/', $nfcToken)) {
        cards_render_disappeared('missing');
    }

    try {
        $pdo = cards_db();
        $query = $pdo->prepare('SELECT id, suit, rank_value, visual_style FROM cards_nfc_links WHERE token = ? LIMIT 1');
        $query->execute([$nfcToken]);
        $nfcLink = $query->fetch();
        if (!$nfcLink) {
            cards_render_disappeared('missing');
        }

        $consume = $pdo->prepare('UPDATE cards_nfc_links SET visit_count = 1, active = 0, opened_at = NOW() WHERE id = ? AND active = 1 AND visit_count = 0');
        $consume->execute([$nfcLink['id']]);
        if ($consume->rowCount() !== 1) {
            cards_render_disappeared('expired');
        }

        $_GET['c'] = $nfcLink['suit'];
        $_GET['v'] = $nfcLink['rank_value'];
        $_GET['s'] = $nfcLink['visual_style'];
    } catch (PDOException $exception) {
        error_log('Cards NFC link error: ' . $exception->getMessage());
        cards_render_disappeared('missing');
    }
} elseif ($shortCode !== null) {
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($requestMethod === 'HEAD') {
        http_response_code(204);
        exit;
    }
    if ($requestMethod !== 'GET') {
        header('Allow: GET, HEAD');
        http_response_code(405);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9]{4}$/', $shortCode)) {
        cards_render_disappeared('missing');
    }

    try {
        $pdo = cards_db();
        $query = $pdo->prepare('SELECT id, suit, rank_value, visual_style FROM cards_short_links WHERE code = ? LIMIT 1');
        $query->execute([$shortCode]);
        $shortLink = $query->fetch();
        if (!$shortLink) {
            cards_render_disappeared('missing');
        }

        $visit = $pdo->prepare("UPDATE cards_short_links
            SET visit_count = visit_count + 1,
                last_visited_at = NOW(),
                active = CASE WHEN max_visits IS NOT NULL AND visit_count + 1 >= max_visits THEN 0 ELSE active END
            WHERE id = ? AND active = 1 AND (max_visits IS NULL OR visit_count < max_visits)");
        $visit->execute([$shortLink['id']]);
        if ($visit->rowCount() !== 1) {
            cards_render_disappeared('expired');
        }

        $_GET['c'] = $shortLink['suit'];
        $_GET['v'] = $shortLink['rank_value'];
        $_GET['s'] = $shortLink['visual_style'];
    } catch (PDOException $exception) {
        error_log('Cards short-link error: ' . $exception->getMessage());
        cards_render_disappeared('missing');
    }
} elseif (!$hasDirectCard) {
    cards_render_disappeared('missing');
}

function normaliser(string $texte): string
{
    $texte = mb_strtolower(trim($texte), 'UTF-8');
    return strtr($texte, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
    ]);
}

function parametre(array $noms, string $defaut): string
{
    foreach ($noms as $nom) {
        if (isset($_GET[$nom]) && is_string($_GET[$nom])) {
            return normaliser($_GET[$nom]);
        }
    }
    return $defaut;
}

$enseignes = [
    'coeur' => ['symbole' => '♥', 'nom' => 'cœur', 'couleur' => 'rouge'],
    'carreau' => ['symbole' => '♦', 'nom' => 'carreau', 'couleur' => 'rouge'],
    'trefle' => ['symbole' => '♣', 'nom' => 'trèfle', 'couleur' => 'noir'],
    'pique' => ['symbole' => '♠', 'nom' => 'pique', 'couleur' => 'noir'],
];

$aliasEnseignes = [
    'coeur' => 'coeur', 'coeurs' => 'coeur', 'heart' => 'coeur', 'hearts' => 'coeur', 'h' => 'coeur', '♥' => 'coeur',
    'carreau' => 'carreau', 'carreaux' => 'carreau', 'diamond' => 'carreau', 'diamonds' => 'carreau', 'd' => 'carreau', '♦' => 'carreau',
    'trefle' => 'trefle', 'trefles' => 'trefle', 'club' => 'trefle', 'clubs' => 'trefle', 'c' => 'trefle', '♣' => 'trefle',
    'pique' => 'pique', 'piques' => 'pique', 'spade' => 'pique', 'spades' => 'pique', 's' => 'pique', '♠' => 'pique',
];

$valeurs = [
    '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10',
    'valet' => 'V', 'dame' => 'D', 'roi' => 'R', 'as' => 'A',
];

$aliasValeurs = [
    'j' => 'valet', 'v' => 'valet', 'jack' => 'valet', 'valet' => 'valet',
    'q' => 'dame', 'queen' => 'dame', 'dame' => 'dame',
    'k' => 'roi', 'king' => 'roi', 'roi' => 'roi',
    'a' => 'as', 'ace' => 'as', 'as' => 'as',
];
foreach (range(2, 10) as $nombre) {
    $aliasValeurs[(string) $nombre] = (string) $nombre;
}

$aliasStyles = [
    'moderne' => 'moderne', 'modern' => 'moderne',
    'classique' => 'classique', 'classic' => 'classique',
    'minimal' => 'minimal', 'minimaliste' => 'minimal', 'simple' => 'minimal',
    'tetes' => 'tetes', 'tete' => 'tetes', 'figures' => 'tetes', 'traditionnel' => 'tetes',
    'ancien' => 'ancien', 'ancienne' => 'ancien', 'pallas' => 'ancien', 'vintage' => 'ancien',
];

$enseigneDemandee = parametre(['c'], 'coeur');
$valeurDemandee = parametre(['v'], 'as');
$styleDemande = parametre(['s'], 'moderne');

$enseigneCle = $aliasEnseignes[$enseigneDemandee] ?? 'coeur';
$valeurCle = $aliasValeurs[$valeurDemandee] ?? 'as';
$style = $aliasStyles[$styleDemande] ?? (preg_match('/^custom_[1-9][0-9]*$/', $styleDemande) ? $styleDemande : 'moderne');
$enseigne = $enseignes[$enseigneCle];
$rang = $valeurs[$valeurCle];
$customCardId = null;
$customStyleName = null;
if (preg_match('/^custom_([1-9][0-9]*)$/', $style, $customStyleMatch)) {
    try {
        $customQuery = cards_db()->prepare('SELECT c.id, s.name FROM cards_custom_cards c JOIN cards_custom_styles s ON s.id = c.style_id WHERE c.style_id = ? AND c.suit = ? AND c.rank_value = ? LIMIT 1');
        $customQuery->execute([(int) $customStyleMatch[1], $enseigneCle, $valeurCle]);
        $customCard = $customQuery->fetch();
        if (!$customCard) {
            cards_render_disappeared('missing');
        }
        $customCardId = (int) $customCard['id'];
        $customStyleName = (string) $customCard['name'];
    } catch (Throwable $exception) {
        error_log('Cards custom card error: ' . $exception->getMessage());
        cards_render_disappeared('missing');
    }
}

$nomsValeurs = [
    '2' => 'deux', '3' => 'trois', '4' => 'quatre', '5' => 'cinq', '6' => 'six',
    '7' => 'sept', '8' => 'huit', '9' => 'neuf', '10' => 'dix',
    'valet' => 'valet', 'dame' => 'dame', 'roi' => 'roi', 'as' => 'as',
];
$nomCarte = $nomsValeurs[$valeurCle] . ' de ' . $enseigne['nom'];

$positions = [
    2 => [2, 14],
    3 => [2, 8, 14],
    4 => [1, 3, 13, 15],
    5 => [1, 3, 8, 13, 15],
    6 => [1, 3, 7, 9, 13, 15],
    7 => [1, 3, 5, 7, 9, 13, 15],
    8 => [1, 3, 5, 7, 9, 11, 13, 15],
    9 => [1, 2, 3, 7, 8, 9, 13, 14, 15],
    10 => [1, 3, 5, 6, 7, 9, 10, 11, 13, 15],
];

$estFigure = in_array($valeurCle, ['valet', 'dame', 'roi'], true);
$estNombre = ctype_digit($valeurCle);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= in_array($style, ['classique', 'ancien'], true) ? '#123d2c' : ($style === 'minimal' ? '#eeeae3' : '#10131a') ?>">
    <title><?= htmlspecialchars(ucfirst($nomCarte), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { color-scheme: dark; --red: #d82732; --black: #15171c; }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; margin: 0; }
        body {
            min-height: 100svh;
            display: grid;
            place-items: center;
            overflow: hidden;
            padding: max(24px, env(safe-area-inset-top)) max(18px, env(safe-area-inset-right)) max(24px, env(safe-area-inset-bottom)) max(18px, env(safe-area-inset-left));
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #10131a;
        }
        body.theme-moderne {
            background:
                radial-gradient(circle at 18% 15%, rgba(109, 94, 252, .28), transparent 34%),
                radial-gradient(circle at 82% 85%, rgba(0, 211, 173, .16), transparent 35%),
                #10131a;
        }
        body.theme-classique {
            background-color: #123d2c;
            background-image: radial-gradient(rgba(255,255,255,.045) 1px, transparent 1px);
            background-size: 5px 5px;
        }
        body.theme-tetes { background: radial-gradient(circle at center, #34415d, #111722 72%); }
        body.theme-ancien {
            background:
                radial-gradient(ellipse at center, rgba(255,255,255,.07), transparent 58%),
                #403725;
        }
        body.theme-minimal { color-scheme: light; background: #eeeae3; }
        body.theme-custom { background: radial-gradient(circle at center, #2f3440, #0d1016 72%); }

        .scene {
            width: min(76vw, 350px, calc((100svh - 48px) * .714));
            aspect-ratio: 5 / 7;
            perspective: 1200px;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .card {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            cursor: pointer;
            transform-origin: 50% 82%;
            animation: reveal 1650ms linear 100ms both;
        }
        .card.replay { animation: none; }
        .face {
            position: absolute;
            inset: 0;
            overflow: hidden;
            border-radius: clamp(16px, 5vw, 25px);
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }
        .front {
            color: var(--black);
            background: #fbfaf5;
            box-shadow: 0 28px 65px rgba(0, 0, 0, .34), 0 4px 12px rgba(0, 0, 0, .18);
        }
        .card.red .front { color: var(--red); }
        .back {
            transform: rotateY(180deg);
            border: 8px solid #f5f0e6;
            background:
                linear-gradient(45deg, rgba(255,255,255,.13) 25%, transparent 25% 75%, rgba(255,255,255,.13) 75%),
                linear-gradient(-45deg, rgba(255,255,255,.13) 25%, transparent 25% 75%, rgba(255,255,255,.13) 75%),
                #3947a7;
            background-size: 22px 22px;
            box-shadow: 0 28px 65px rgba(0, 0, 0, .34);
        }
        .back::after {
            content: "";
            position: absolute;
            inset: 11px;
            border: 2px solid rgba(255,255,255,.7);
            border-radius: 12px;
        }
        .corner {
            position: absolute;
            z-index: 3;
            top: 5.5%;
            left: 6%;
            width: 18%;
            display: grid;
            justify-items: center;
            font-weight: 800;
            font-size: clamp(28px, 11vw, 49px);
            line-height: .82;
            letter-spacing: -.06em;
        }
        .corner .suit { margin-top: .22em; font-size: .68em; line-height: 1; }
        .corner.bottom { inset: auto 6% 5.5% auto; transform: rotate(180deg); }
        .pips {
            position: absolute;
            inset: 16% 16%;
            display: grid;
            grid-template: repeat(5, 1fr) / repeat(3, 1fr);
            place-items: center;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(36px, 11vw, 55px);
            line-height: 1;
        }
        .pip { grid-area: var(--row) / var(--col); }
        .pip.flip { transform: rotate(180deg); }
        .ace {
            position: absolute;
            inset: 19%;
            display: grid;
            place-items: center;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(130px, 45vw, 205px);
            line-height: 1;
        }
        .royal {
            position: absolute;
            inset: 14.5% 13%;
            overflow: hidden;
            border: 2px solid currentColor;
            background: #fffdf5;
        }
        .court-art {
            position: absolute;
            inset: 0;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            background-color: #fff;
        }
        .court-valet { background-image: url("assets/tetes-valet.webp"); }
        .court-dame { background-image: url("assets/tetes-dame.webp"); }
        .court-roi { background-image: url("assets/tetes-roi.webp"); }
        .theme-ancien .court-art { background-color: #eee1bf; }
        .theme-ancien .court-valet { background-image: url("assets/ancien-valet.webp"); }
        .theme-ancien .court-dame { background-image: url("assets/ancien-dame.webp"); }
        .theme-ancien .court-roi { background-image: url("assets/ancien-roi.webp"); }
        .royal-suit {
            position: absolute;
            z-index: 5;
            inset: 50% auto auto 50%;
            display: grid;
            place-items: center;
            width: 23%;
            aspect-ratio: 1;
            transform: translate(-50%, -50%);
            border: 2px solid currentColor;
            border-radius: 50%;
            background: rgba(255,253,244,.94);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(22px, 7vw, 34px);
            line-height: 1;
        }

        .theme-moderne .front::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 7px;
            background: currentColor;
        }
        .theme-moderne .front { background: linear-gradient(145deg, #fff 0%, #f4f2eb 100%); }
        .theme-moderne .corner { font-family: inherit; }

        .theme-classique .front {
            border: 1px solid #d7d0bf;
            border-radius: 17px;
            background: #fffdf4;
            box-shadow: 0 25px 45px rgba(0, 0, 0, .42), inset 0 0 24px rgba(111, 84, 40, .06);
        }
        .theme-classique .face { border-radius: 17px; }
        .theme-classique .corner { font-family: Georgia, "Times New Roman", serif; font-weight: 700; }
        .theme-classique .royal { outline: 5px double currentColor; outline-offset: -10px; }
        .theme-classique .back { background-color: #812d32; }

        .theme-tetes .front {
            border: 1px solid #d4d7dc;
            background: #fff;
            box-shadow: 0 26px 58px rgba(0, 0, 0, .45);
        }
        .theme-tetes .corner { font-family: Georgia, "Times New Roman", serif; }
        .theme-tetes .royal { inset: 13.5% 11.5%; border-color: #1a3f84; }
        .theme-tetes .back { background-color: #173b79; }

        .theme-ancien .front {
            border: 1px solid #b7a77c;
            border-radius: 14px;
            background: #f2ead3;
            box-shadow: 0 26px 58px rgba(0, 0, 0, .5), inset 0 0 34px rgba(90,63,21,.12);
        }
        .theme-ancien .face { border-radius: 14px; }
        .theme-ancien .corner { font-family: Georgia, "Times New Roman", serif; font-weight: 700; }
        .theme-ancien .royal { inset: 13% 11%; border: 3px double #2b2921; background: #e9dfc1; }
        .theme-ancien .back { background-color: #695127; }

        .theme-minimal .front {
            border: 1px solid rgba(21, 23, 28, .13);
            background: #fff;
            box-shadow: 0 18px 50px rgba(44, 37, 27, .14);
        }
        .theme-minimal .back { background-color: #202124; box-shadow: 0 18px 50px rgba(44, 37, 27, .14); }
        .theme-minimal .corner { font-weight: 650; }
        .theme-minimal .royal { border-width: 1px; }
        .theme-custom .front { display: grid; place-items: center; background: #0a0c10; }
        .custom-card-image { display: block; width: 100%; height: 100%; object-fit: contain; }

        /* Reserve the corner-index area: the suit must never cross the court frame. */
        .front .royal {
            top: 22%;
            bottom: 22%;
        }

        @keyframes reveal {
            0% { transform: translateY(105svh) rotateY(180deg) rotateZ(-7deg); }
            8% { transform: translateY(72svh) rotateY(180deg) rotateZ(-6deg); }
            42% { transform: translateY(0) rotateY(180deg) rotateZ(-2deg); }
            55% { transform: translateY(-2px) rotateY(180deg) rotateZ(-1deg); }
            68% { transform: translateY(-8px) rotateY(112deg) rotateZ(2.5deg) scale(1.02); }
            86% { transform: translateY(-3px) rotateY(-9deg) rotateZ(-1deg) scale(1.01); }
            94% { transform: translateY(1px) rotateY(3deg) rotateZ(.35deg); }
            100% { transform: none; }
        }
        @media (orientation: landscape) and (max-height: 600px) {
            .scene { width: min(44vw, 300px, calc((100svh - 28px) * .714)); }
            body { padding-block: 14px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .card { animation: none; }
        }
    </style>
</head>
<body class="theme-<?= $customCardId ? 'custom' : htmlspecialchars($style, ENT_QUOTES, 'UTF-8') ?>">
    <main class="scene" aria-label="<?= htmlspecialchars(ucfirst($nomCarte), ENT_QUOTES, 'UTF-8') ?>">
        <div class="card <?= $enseigne['couleur'] === 'rouge' ? 'red' : 'black' ?>" id="card" role="button" tabindex="0" aria-label="<?= htmlspecialchars(ucfirst($nomCarte), ENT_QUOTES, 'UTF-8') ?> — appuyer pour rejouer l'animation">
            <div class="face back" aria-hidden="true"></div>
            <div class="face front">
                <?php if ($customCardId): ?>
                <img class="custom-card-image" src="/card-image.php?id=<?= $customCardId ?>" alt="<?= htmlspecialchars(ucfirst($nomCarte) . ' — ' . $customStyleName, ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                <div class="corner" aria-hidden="true"><span><?= $rang ?></span><span class="suit"><?= $enseigne['symbole'] ?></span></div>
                <div class="corner bottom" aria-hidden="true"><span><?= $rang ?></span><span class="suit"><?= $enseigne['symbole'] ?></span></div>

                <?php if ($valeurCle === 'as'): ?>
                    <div class="ace" aria-hidden="true"><?= $enseigne['symbole'] ?></div>
                <?php elseif ($estFigure): ?>
                    <div class="royal" aria-hidden="true">
                        <span class="court-art court-<?= $valeurCle ?>"></span>
                        <span class="royal-suit"><?= $enseigne['symbole'] ?></span>
                    </div>
                <?php elseif ($estNombre): ?>
                    <div class="pips" aria-hidden="true">
                        <?php foreach ($positions[(int) $valeurCle] as $position):
                            $ligne = intdiv($position - 1, 3) + 1;
                            $colonne = (($position - 1) % 3) + 1;
                            $retourne = $ligne >= 4;
                        ?>
                            <span class="pip<?= $retourne ? ' flip' : '' ?>" style="--row:<?= $ligne ?>;--col:<?= $colonne ?>"><?= $enseigne['symbole'] ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script>
        const card = document.getElementById('card');
        const replay = () => {
            card.classList.add('replay');
            void card.offsetWidth;
            card.classList.remove('replay');
        };
        card.addEventListener('click', replay);
        card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                replay();
            }
        });
    </script>
</body>
</html>

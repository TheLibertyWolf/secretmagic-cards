<?php
declare(strict_types=1);

$cardsLoader = __DIR__ . '/cards_loader.php';
if (!is_file($cardsLoader)) {
    if (!headers_sent()) {
        header('Location: /install/');
    }
    exit;
}
require_once $cardsLoader;

if (!defined('CARDS_APP_PATH') || !is_dir(CARDS_APP_PATH)) {
    http_response_code(503);
    exit('Configuration de l’application invalide.');
}

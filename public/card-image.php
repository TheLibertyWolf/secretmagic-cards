<?php
declare(strict_types=1);

require_once __DIR__ . '/cards_entry.php';
require_once CARDS_APP_PATH . '/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(404);
    exit;
}

try {
    $query = cards_db()->prepare('SELECT image_filename FROM cards_custom_cards WHERE id = ? LIMIT 1');
    $query->execute([$id]);
    $filename = $query->fetchColumn();
    $uploadRoot = rtrim((string) (cards_config()['custom_cards_path'] ?? ''), DIRECTORY_SEPARATOR);
    if (!is_string($filename) || $filename === '' || basename($filename) !== $filename || $uploadRoot === '') {
        throw new RuntimeException('Image inconnue.');
    }
    $path = $uploadRoot . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Image introuvable.');
    }
    header('Content-Type: image/webp');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    readfile($path);
} catch (Throwable $exception) {
    http_response_code(404);
}

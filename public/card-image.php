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
    $query = cards_db()->prepare('SELECT c.image_filename, s.source_url, s.source_license FROM cards_custom_cards c JOIN cards_custom_styles s ON s.id = c.style_id WHERE c.id = ? LIMIT 1');
    $query->execute([$id]);
    $card = $query->fetch();
    $filename = is_array($card) ? $card['image_filename'] : null;
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
    $sourceUrl = is_array($card) ? (string) ($card['source_url'] ?? '') : '';
    if (filter_var($sourceUrl, FILTER_VALIDATE_URL) && str_starts_with($sourceUrl, 'https://')) {
        header('Link: <' . str_replace(['\r', '\n'], '', $sourceUrl) . '>; rel="describedby"');
    }
    $sourceLicense = is_array($card) ? preg_replace('/[^A-Za-z0-9.+-]/', '', (string) ($card['source_license'] ?? '')) : '';
    if ($sourceLicense !== '') {
        header('X-Card-License: ' . $sourceLicense);
    }
    readfile($path);
} catch (Throwable $exception) {
    http_response_code(404);
}

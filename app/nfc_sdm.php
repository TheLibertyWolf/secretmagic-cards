<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function cards_sdm_aes_block(string $block, string $key): string
{
    $encrypted = openssl_encrypt($block, 'aes-128-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
    if (!is_string($encrypted) || strlen($encrypted) !== 16) {
        throw new RuntimeException('AES indisponible.');
    }
    return $encrypted;
}

function cards_sdm_xor(string $left, string $right): string
{
    return $left ^ $right;
}

function cards_sdm_shift(string $block): string
{
    $carry = 0;
    $out = '';
    for ($i = 15; $i >= 0; $i--) {
        $byte = ord($block[$i]);
        $out = chr((($byte << 1) & 0xff) | $carry) . $out;
        $carry = ($byte & 0x80) ? 1 : 0;
    }
    return $out;
}

function cards_sdm_cmac(string $key, string $message): string
{
    $zero = str_repeat("\0", 16);
    $l = cards_sdm_aes_block($zero, $key);
    $k1 = cards_sdm_shift($l);
    if ((ord($l[0]) & 0x80) !== 0) {
        $k1[15] = chr(ord($k1[15]) ^ 0x87);
    }
    $k2 = cards_sdm_shift($k1);
    if ((ord($k1[0]) & 0x80) !== 0) {
        $k2[15] = chr(ord($k2[15]) ^ 0x87);
    }

    $length = strlen($message);
    $blocks = max(1, (int) ceil($length / 16));
    $complete = $length > 0 && $length % 16 === 0;
    if ($complete) {
        $last = cards_sdm_xor(substr($message, ($blocks - 1) * 16, 16), $k1);
    } else {
        $tail = substr($message, ($blocks - 1) * 16);
        $padded = $tail . "\x80" . str_repeat("\0", 15 - strlen($tail));
        $last = cards_sdm_xor($padded, $k2);
    }

    $state = $zero;
    for ($i = 0; $i < $blocks - 1; $i++) {
        $state = cards_sdm_aes_block(cards_sdm_xor($state, substr($message, $i * 16, 16)), $key);
    }
    return cards_sdm_aes_block(cards_sdm_xor($state, $last), $key);
}

function cards_sdm_derive_tag_key(string $masterKey, string $uid, int $keyNumber): string
{
    if ($masterKey === str_repeat("\0", 16)) {
        return $masterKey;
    }
    return hash_pbkdf2('sha512', $masterKey, 'key' . $uid . chr($keyNumber), 5000, 16, true);
}

function cards_sdm_derive_meta_key(string $masterKey): string
{
    if ($masterKey === str_repeat("\0", 16)) {
        return $masterKey;
    }
    return hash_pbkdf2('sha512', $masterKey, 'key_no_uid' . chr(1), 5000, 16, true);
}

function cards_sdm_encrypt_master_key(string $masterKey): string
{
    $storageKey = hex2bin((string) cards_config()['nfc_key_encryption']);
    if (!is_string($storageKey) || strlen($storageKey) !== 32) {
        throw new RuntimeException('Clé de stockage NFC invalide.');
    }
    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($masterKey, 'aes-256-gcm', $storageKey, OPENSSL_RAW_DATA, $nonce, $tag, 'cards-nfc-sdm-v1');
    if (!is_string($cipher) || strlen($tag) !== 16) {
        throw new RuntimeException('Chiffrement NFC indisponible.');
    }
    return base64_encode($nonce . $tag . $cipher);
}

function cards_sdm_decrypt_master_key(string $stored): string
{
    $storageKey = hex2bin((string) cards_config()['nfc_key_encryption']);
    $packed = base64_decode($stored, true);
    if (!is_string($storageKey) || strlen($storageKey) !== 32 || !is_string($packed) || strlen($packed) < 29) {
        throw new RuntimeException('Secret NFC illisible.');
    }
    $plain = openssl_decrypt(substr($packed, 28), 'aes-256-gcm', $storageKey, OPENSSL_RAW_DATA, substr($packed, 0, 12), substr($packed, 12, 16), 'cards-nfc-sdm-v1');
    if (!is_string($plain) || strlen($plain) !== 16) {
        throw new RuntimeException('Secret NFC invalide.');
    }
    return $plain;
}

function cards_sdm_validate(string $masterKey, string $piccHex, string $cmacHex, ?string $encHex = null): array
{
    if (!preg_match('/^[A-Fa-f0-9]{32}$/', $piccHex) || !preg_match('/^[A-Fa-f0-9]{16}$/', $cmacHex)) {
        throw new InvalidArgumentException('Paramètres SDM invalides.');
    }
    if ($encHex !== null && $encHex !== '' && (!preg_match('/^[A-Fa-f0-9]+$/', $encHex) || strlen($encHex) % 32 !== 0)) {
        throw new InvalidArgumentException('Données SDM invalides.');
    }
    $ciphertext = hex2bin($piccHex);
    $metaKey = cards_sdm_derive_meta_key($masterKey);
    $plain = openssl_decrypt($ciphertext, 'aes-128-cbc', $metaKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
    if (!is_string($plain) || strlen($plain) !== 16) {
        throw new InvalidArgumentException('Message SDM illisible.');
    }
    $tag = ord($plain[0]);
    if (($tag & 0x80) === 0 || ($tag & 0x40) === 0 || ($tag & 0x0f) !== 7) {
        throw new InvalidArgumentException('Format SDM non reconnu.');
    }
    $uid = substr($plain, 1, 7);
    $counterBytes = substr($plain, 8, 3);
    $fileKey = cards_sdm_derive_tag_key($masterKey, $uid, 2);
    $sv = "\x3c\xc3\x00\x01\x00\x80" . $uid . $counterBytes;
    $sessionMacKey = cards_sdm_cmac($fileKey, $sv);
    $macInput = ($encHex !== null && $encHex !== '') ? strtoupper($encHex) . '&cmac=' : '';
    $fullMac = cards_sdm_cmac($sessionMacKey, $macInput);
    $expected = '';
    for ($i = 1; $i < 16; $i += 2) {
        $expected .= $fullMac[$i];
    }
    $received = hex2bin($cmacHex);
    if (!is_string($received) || !hash_equals($expected, $received)) {
        throw new InvalidArgumentException('Signature SDM incorrecte.');
    }
    $counter = ord($counterBytes[0]) | (ord($counterBytes[1]) << 8) | (ord($counterBytes[2]) << 16);
    return ['uid' => strtoupper(bin2hex($uid)), 'counter' => $counter];
}

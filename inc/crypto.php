<?php
require_once __DIR__ . '/config.php';

function encrypt_str(string $plain): string {
    $key = hash('sha256', APP_SECRET_KEY, true); // 32 bytes
    $iv  = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) throw new RuntimeException('encrypt failed');
    return base64_encode($iv . $cipher);
}

function decrypt_str(string $enc): string {
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 17) throw new RuntimeException('decrypt failed');
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $key = hash('sha256', APP_SECRET_KEY, true);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) throw new RuntimeException('decrypt failed');
    return $plain;
}

function key_hint(string $key): string {
    $t = trim($key);
    $last4 = mb_substr($t, -4);
    return '****' . $last4;
}

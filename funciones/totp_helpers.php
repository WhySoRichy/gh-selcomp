<?php
/**
 * Funciones auxiliares para TOTP (Time-Based One-Time Password)
 * Cifrado/descifrado de secretos TOTP con AES-256-CBC
 * Generación de QR y verificación de códigos
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Cifra un secreto TOTP usando AES-256-CBC
 * @param string $secret El secreto TOTP en texto plano (Base32)
 * @return string El secreto cifrado (base64: iv + ciphertext)
 * @throws Exception Si APP_KEY no está configurada
 */
function encrypt_2fa_secret(string $secret): string
{
    $key = APP_KEY;
    if (empty($key) || strlen($key) < 64) {
        throw new Exception('APP_KEY no configurada o inválida. Debe ser de 64 caracteres hex (32 bytes).');
    }

    $key_bin = hex2bin($key);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($secret, 'aes-256-cbc', $key_bin, OPENSSL_RAW_DATA, $iv);

    if ($encrypted === false) {
        throw new Exception('Error al cifrar el secreto TOTP.');
    }

    // Formato: base64(iv + ciphertext)
    return base64_encode($iv . $encrypted);
}

/**
 * Descifra un secreto TOTP cifrado con AES-256-CBC
 * @param string $encrypted_secret El secreto cifrado (base64)
 * @return string El secreto TOTP en texto plano (Base32)
 * @throws Exception Si no se puede descifrar
 */
function decrypt_2fa_secret(string $encrypted_secret): string
{
    $key = APP_KEY;
    if (empty($key) || strlen($key) < 64) {
        throw new Exception('APP_KEY no configurada o inválida.');
    }

    $key_bin = hex2bin($key);
    $data = base64_decode($encrypted_secret);

    if ($data === false || strlen($data) < 17) {
        throw new Exception('Datos cifrados inválidos.');
    }

    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key_bin, OPENSSL_RAW_DATA, $iv);

    if ($decrypted === false) {
        throw new Exception('Error al descifrar el secreto TOTP.');
    }

    return $decrypted;
}

/**
 * Genera un nuevo secreto TOTP
 * @return string Secreto en Base32 (16 caracteres)
 */
function generate_2fa_secret(): string
{
    $google2fa = new Google2FA();
    return $google2fa->generateSecretKey(16); // 16 caracteres Base32
}

/**
 * Genera el SVG del código QR para escanear con la app de autenticación
 * @param string $secret Secreto TOTP en Base32
 * @param string $email Email del usuario (para identificación)
 * @param string $issuer Nombre de la aplicación
 * @return string SVG del código QR
 */
function generate_2fa_qr_svg(string $secret, string $email, string $issuer = 'Selcomp Portal GH'): string
{
    $google2fa = new Google2FA();
    $otpauth_url = $google2fa->getQRCodeUrl($issuer, $email, $secret);

    $renderer = new ImageRenderer(
        new RendererStyle(250),
        new SvgImageBackEnd()
    );
    $writer = new Writer($renderer);

    return $writer->writeString($otpauth_url);
}

/**
 * Verifica un código TOTP ingresado por el usuario
 * @param string $secret Secreto TOTP en Base32 (descifrado)
 * @param string $code Código de 6 dígitos ingresado
 * @param int $window Ventana de tolerancia (períodos de 30s antes/después)
 * @return bool true si el código es válido
 */
function verify_2fa_code(string $secret, string $code, int $window = 2): bool
{
    $google2fa = new Google2FA();
    $google2fa->setWindow($window);
    return $google2fa->verifyKey($secret, $code);
}

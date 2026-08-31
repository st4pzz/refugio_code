<?php
declare(strict_types=1);

namespace Refugio\Services;

use Refugio\Support\Env;
use RuntimeException;

final class EncryptionService
{
    private string $key;

    public function __construct(?string $encodedKey = null, string $keyName = 'MARKETING_ENCRYPTION_KEY')
    {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('A extensao OpenSSL do PHP e obrigatoria para proteger credenciais.');
        }
        $encodedKey ??= Env::get('MARKETING_ENCRYPTION_KEY');
        $key = base64_decode($encodedKey, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException($keyName . ' deve conter 32 bytes em Base64.');
        }
        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException('Nao foi possivel proteger a credencial.');
        }
        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(?string $encrypted): string
    {
        if ($encrypted === null || $encrypted === '') {
            return '';
        }
        if (!str_starts_with($encrypted, 'v1:')) {
            throw new RuntimeException('Formato de credencial criptografada invalido.');
        }
        $payload = base64_decode(substr($encrypted, 3), true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Credencial criptografada corrompida.');
        }
        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $plaintext = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException('Nao foi possivel descriptografar a credencial.');
        }
        return $plaintext;
    }
}

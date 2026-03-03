<?php

namespace App\Services;

use RuntimeException;

class EncryptionService
{
    private const CIPHER     = 'aes-256-gcm';
    private const IV_LENGTH  = 12; // Recommended IV length for GCM
    private const TAG_LENGTH = 16; // 128-bit authentication tag

    /**
     * Encrypt a plaintext for a specific wizard context.
     *
     * $wizardSalt can be wizard ID, codice_univoco or another per-wizard unique value.
     */
    public function encryptForWizard(string $plaintext, string $wizardSalt): string
    {
        $key = $this->deriveKey($wizardSalt);

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',                 // no additional authenticated data
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        // Pack iv | tag | ciphertext and base64 encode
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a previously encrypted payload for a given wizard context.
     */
    public function decryptForWizard(string $encrypted, string $wizardSalt): string
    {
        $key = $this->deriveKey($wizardSalt);

        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 payload.');
        }

        if (strlen($decoded) <= (self::IV_LENGTH + self::TAG_LENGTH)) {
            throw new RuntimeException('Invalid encrypted payload length.');
        }

        $iv         = substr($decoded, 0, self::IV_LENGTH);
        $tag        = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed.');
        }

        return $plaintext;
    }

    /**
     * Derive a 256-bit key from APP_KEY and per-wizard salt.
     */
    private function deriveKey(string $wizardSalt): string
    {
        $appKey = config('app.key');

        if (! $appKey) {
            throw new RuntimeException('Application key is not configured.');
        }

        // Laravel APP_KEY is usually base64:...; decode if needed.[file:5]
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid base64 APP_KEY.');
            }
            $appKey = $decoded;
        }

        // Derive 32-byte key with SHA-256
        return hash('sha256', $appKey . '|' . $wizardSalt, true);
    }
}

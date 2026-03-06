<?php

namespace App\Services;

use RuntimeException;
/**
 * Servizio di cifratura AES-256-GCM utilizzando APP_KEY come chiave.
 * La chiave derivata è unica per installazione, la sicurezza risiede
 * nella protezione dell'APP_KEY stessa.
 */
class EncryptionService
{
    private string $key;

    public function __construct()
    {
        // Recupera e prepara la chiave da config('app.key')
        $this->key = $this->prepareKey(config('app.key'));
    }

    /**
     * Cifra un testo in chiaro usando AES-256-GCM.
     *
     * Output: base64_encode(IV (12 byte) + tag (16 byte) + ciphertext)
     *
     * @param string $plaintext
     * @return string
     * @throws RuntimeException
     */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12); // 12 byte per GCM
        $tag = ''; // verrà riempito da openssl_encrypt

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',   // additional authenticated data (vuoto)
            16    // tag length
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        // Concatena IV + tag + ciphertext e codifica in base64
        $payload = $iv . $tag . $ciphertext;
        return base64_encode($payload);
    }

    /**
     * Decifra un payload prodotto da encrypt().
     *
     * @param string $payload base64 di iv+tag+ciphertext
     * @return string
     * @throws RuntimeException
     */
    public function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 payload.');
        }

        // Estrai IV (primi 12 byte), tag (successivi 16 byte) e ciphertext (resto)
        $ivLength = 12;
        $tagLength = 16;

        if (strlen($decoded) < $ivLength + $tagLength) {
            throw new RuntimeException('Payload too short.');
        }

        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, $tagLength);
        $ciphertext = substr($decoded, $ivLength + $tagLength);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
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
     * Prepara la chiave partendo dal valore di config('app.key').
     * La chiave può essere in formato "base64:..." oppure una stringa già binaria.
     *
     * @param string $appKey
     * @return string (binary key)
     * @throws RuntimeException
     */
    private function prepareKey(string $appKey): string
    {
        // Se la chiave è prefissata con "base64:", la decodifichiamo
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid base64 APP_KEY.');
            }
            return $decoded;
        }

        // Altrimenti assumiamo sia già una chiave binaria a 32 byte?
        // In realtà Laravel di solito usa prefisso, ma gestiamo comunque.
        // Se è una stringa esadecimale, la convertiamo.
        if (strlen($appKey) === 64 && ctype_xdigit($appKey)) {
            return hex2bin($appKey);
        }

        // Fallback: usa direttamente la stringa (dovrebbe già essere binaria)
        return $appKey;
    }
}

<?php

/**
 * AES-256-CBC encryption + XOR key-splitting.
 *
 * Flow:
 *  1. generateKey()              → 32-byte random AES key
 *  2. splitKey($key, $n)         → n XOR parts (P1…Pn) where XOR of all = key
 *  3. encryptFile($path, $key)   → encrypts file in-place, returns IV (hex)
 *  4. encryptPart($part, $pass)  → encrypts one key part with holder's password
 *
 * To decrypt:
 *  1. decryptPart($enc, $pass)   → recover plain part
 *  2. joinParts($parts)          → XOR all parts → original key
 *  3. decryptFile($path, $key)   → decrypt and return file contents as string
 */
class CryptoHelper {
    private const CIPHER = 'AES-256-CBC';
    private const IV_LEN = 16;

    // ── Key generation ────────────────────────────────────────────────────────

    public static function generateKey(): string {
        return random_bytes(32);
    }

    /**
     * XOR split: generate n-1 random parts, last part = XOR of all previous XOR key.
     * @return string[]  array of n hex-encoded parts
     */
    public static function splitKey(string $key, int $n): array {
        if ($n < 2) throw new InvalidArgumentException('Need at least 2 parts.');
        $parts = [];
        $xor   = $key;
        for ($i = 0; $i < $n - 1; $i++) {
            $p       = random_bytes(32);
            $parts[] = bin2hex($p);
            $xor     = $xor ^ $p;
        }
        $parts[] = bin2hex($xor);  // last part reconstructs the key
        return $parts;
    }

    /**
     * XOR all parts together to recover the original key.
     * @param string[] $hexParts  hex-encoded parts
     */
    public static function joinParts(array $hexParts): string {
        $result = hex2bin($hexParts[0]);
        for ($i = 1; $i < count($hexParts); $i++) {
            $result ^= hex2bin($hexParts[$i]);
        }
        return $result;
    }

    // ── Part encryption (protect each part with holder's password) ────────────

    public static function encryptPart(string $hexPart, string $password): string {
        $partKey = hash('sha256', $password, true);
        $iv      = random_bytes(self::IV_LEN);
        $enc     = openssl_encrypt(
            hex2bin($hexPart), self::CIPHER, $partKey, OPENSSL_RAW_DATA, $iv
        );
        return base64_encode($iv . $enc);
    }

    public static function decryptPart(string $encPart, string $password): ?string {
        $raw  = base64_decode($encPart);
        $iv   = substr($raw, 0, self::IV_LEN);
        $data = substr($raw, self::IV_LEN);
        $partKey = hash('sha256', $password, true);
        $plain = openssl_decrypt($data, self::CIPHER, $partKey, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) return null;
        return bin2hex($plain);
    }

    // ── File encryption ───────────────────────────────────────────────────────

    /**
     * Encrypt file contents. Returns hex IV (prepended to encrypted file).
     * The encrypted file format: [16 bytes IV][encrypted data]
     */
    public static function encryptFile(string $filePath, string $key): void {
        $plain = file_get_contents($filePath);
        $iv    = random_bytes(self::IV_LEN);
        $enc   = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        file_put_contents($filePath, $iv . $enc);
    }

    /**
     * Decrypt file to a temp path and return that path.
     * Caller is responsible for deleting the temp file.
     */
    public static function decryptFileToTemp(string $encPath, string $key): string {
        $raw  = file_get_contents($encPath);
        $iv   = substr($raw, 0, self::IV_LEN);
        $data = substr($raw, self::IV_LEN);
        $plain = openssl_decrypt($data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('Декриптирането е неуспешно. Проверете ключовете.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'docreg_');
        file_put_contents($tmp, $plain);
        return $tmp;
    }

    // ── Verify password produces a working part ───────────────────────────────

    public static function verifyPartPassword(string $encPart, string $password): bool {
        return self::decryptPart($encPart, $password) !== null;
    }
}

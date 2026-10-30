<?php
namespace App\Services;

class GatewaySecret
{
    private function key(): string
    {
        $encoded = getenv('XSENDER_ENCRYPTION_KEY');
        if (APP_ENV === 'production' && !$encoded) throw new \RuntimeException('XSENDER_ENCRYPTION_KEY wajib diisi.');
        $path = CONFIG_PATH . '/gateway_key.php';
        // Each installation gets its own key on first use; it is never shipped with the source.
        if (!$encoded && !is_file($path)) {
            // Mode "x" fails if another request created the file first, so a key is never overwritten.
            $handle = @fopen($path, 'x');
            if ($handle) {
                fwrite($handle, "<?php\nreturn '" . base64_encode(random_bytes(32)) . "';\n");
                fclose($handle);
                @chmod($path, 0600);
            } elseif (!is_file($path)) {
                throw new \RuntimeException('Kunci enkripsi gateway gagal dibuat. Pastikan folder config dapat ditulis.');
            }
        }
        if (!$encoded && is_file($path)) $encoded = require $path;
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($key === false || strlen($key) !== 32) throw new \RuntimeException('Kunci enkripsi gateway belum tersedia. Jalankan migrasi gateway.');
        return $key;
    }

    public function encrypt(string $value): string
    {
        $iv = random_bytes(12);
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($encrypted === false) throw new \RuntimeException('API key belum berhasil dienkripsi.');
        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $value): string
    {
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 29) throw new \RuntimeException('Konfigurasi gateway tidak dapat dibaca.');
        $decrypted = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($decrypted === false) throw new \RuntimeException('Konfigurasi gateway tidak dapat dibaca.');
        return $decrypted;
    }
}

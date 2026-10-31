<?php
/**
 * User Model (Admin RBAC & Credentials)
 * Gmap Prospek
 */

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();

    }

    public function profile(int $id): ?array
    {
        $q = $this->db->prepare('SELECT id,username,email,name,role,created_at FROM users WHERE id=? AND deleted_at IS NULL');
        $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    public function updateProfile(int $id, string $name, string $email, string $currentPassword): void
    {
        $name = trim($name);
        $email = trim($email);
        if(\App\Services\PlatformContext::enabled()){
            $profile=$this->profile($id);
            if(!$profile || mb_strtolower($email)!==mb_strtolower($profile['email']))throw new \InvalidArgumentException('Email akun terverifikasi tidak dapat diganti dari profil.');
        }
        if (!preg_match('/^.{1,100}$/us', $name)) throw new \InvalidArgumentException('Nama wajib diisi, maksimal 100 karakter.');
        if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Masukkan alamat email yang valid, maksimal 100 karakter.');
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT email,password FROM users WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $q->execute([$id]);
            $user = $q->fetch();
            if (!$user) throw new \InvalidArgumentException('Akun tidak ditemukan.');
            if ($email !== $user['email'] && !password_verify($currentPassword, $user['password'])) throw new \InvalidArgumentException('Password saat ini diperlukan untuk mengganti email.');
            $q = $this->db->prepare('SELECT id FROM users WHERE (email=? OR username=?) AND id<>? LIMIT 1');
            $q->execute([$email, $email, $id]);
            if ($q->fetch()) throw new \InvalidArgumentException('Email tersebut sudah digunakan akun lain.');
            $this->db->prepare('UPDATE users SET name=?,email=? WHERE id=?')->execute([$name, $email, $id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($e instanceof \PDOException && $e->getCode() === '23000') throw new \InvalidArgumentException('Email tersebut sudah digunakan akun lain.');
            throw $e;
        }
    }

    /** Returns the new session version: every other signed-in session of this account is logged out. */
    public function changePassword(int $id, string $current, string $password, string $confirmation): int
    {
        if (!preg_match('/^.{8,}$/us', $password) || strlen($password) > 72 || str_contains($password, "\0")) throw new \InvalidArgumentException('Password baru minimal 8 karakter dan tidak boleh terlalu panjang.');
        if ($password !== $confirmation) throw new \InvalidArgumentException('Konfirmasi password baru tidak cocok.');
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT password FROM users WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $q->execute([$id]);
            $hash = $q->fetchColumn();
            if (!$hash || !password_verify($current, $hash)) throw new \InvalidArgumentException('Password saat ini salah.');
            if (password_verify($password, $hash)) throw new \InvalidArgumentException('Gunakan password baru yang berbeda dari password saat ini.');
            $this->db->prepare('UPDATE users SET password=?,session_version=session_version+1 WHERE id=?')->execute([password_hash_secure($password), $id]);
            $q = $this->db->prepare('SELECT session_version FROM users WHERE id=?');
            $q->execute([$id]);
            $version = (int)$q->fetchColumn();
            $this->db->commit();
            return $version;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Find user by username or email
     *
     * @param string $identifier
     * @return array|null
     */
    public function findByIdentifier(string $identifier): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, username, email, password, name, role,workspace_id, last_login,session_version
            FROM users
            WHERE (username = :u OR email = :e) AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bindValue(':u', $identifier, PDO::PARAM_STR);
        $stmt->bindValue(':e', $identifier, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Authenticate admin credentials
     *
     * @param string $identifier
     * @param string $password
     * @return array|false Returns user array if authenticated, false otherwise
     */
    public function authenticate(string $identifier, string $password)
    {
        $user = $this->findByIdentifier($identifier);
        // Unknown accounts still pay for a hash check so response time does not reveal which emails exist.
        $hash = $user['password'] ?? null;
        if (!is_string($hash) || $hash === '') $hash = self::dummyHash();
        $valid = password_verify($password, $hash);

        if (!$user || !$valid || (int)$user['workspace_id'] !== 1 || !in_array($user['role'], ['admin', 'user'], true)) {
            return false;
        }

        if (password_needs_upgrade($user['password'])) {
            $this->db->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash_secure($password), (int)$user['id']]);
        }
        $this->updateLastLogin((int)$user['id']);
        return $user;
    }

    private static function dummyHash(): string
    {
        static $hash;
        return $hash ??= password_hash_secure(bin2hex(random_bytes(16)));
    }

    /**
     * Update last login datetime
     *
     * @param int $userId
     */
    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET last_login = NOW()
            WHERE id = :id
        ");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Create a new account (CLI use: scripts/create_user.php)
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password, name, role, created_at)
            VALUES (:username, :email, :password, :name, :role, NOW())
        ");
        $stmt->bindValue(':username', $data['username'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':password', password_hash_secure($data['password']), PDO::PARAM_STR);
        $stmt->bindValue(':name', $data['name'] ?? 'Admin', PDO::PARAM_STR);
        $stmt->bindValue(':role', 'user', PDO::PARAM_STR);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }
}

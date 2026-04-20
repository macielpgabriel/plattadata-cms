<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function paginate(int $limit = 25): array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, email, role, is_active, two_factor_enabled, failed_login_attempts, locked_until, created_at FROM users ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active, two_factor_enabled) VALUES (:name, :email, :password_hash, :role, :is_active, :two_factor_enabled)'
        );

        $stmt->execute([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'is_active' => (int) ($data['is_active'] ?? 1),
            'two_factor_enabled' => (int) ($data['two_factor_enabled'] ?? 0),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $params = [
            'id' => $id,
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'role' => $data['role'],
            'is_active' => (int) ($data['is_active'] ?? 1),
            'two_factor_enabled' => (int) ($data['two_factor_enabled'] ?? 0),
        ];

        $sql = 'UPDATE users SET name = :name, email = :email, role = :role, is_active = :is_active, two_factor_enabled = :two_factor_enabled';

        if (!empty($data['password_hash'])) {
            $sql .= ', password_hash = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function registerFailedLogin(int $userId): void
    {
        $maxAttempts = max(3, (int) config('app.security.login_max_attempts', 5));
        $lockMinutes = max(1, (int) config('app.security.login_lockout_minutes', 15));

        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET failed_login_attempts = failed_login_attempts + 1,
                 locked_until = CASE
                     WHEN failed_login_attempts + 1 >= :max_attempts THEN DATE_ADD(NOW(), INTERVAL :lock_minutes MINUTE)
                     ELSE locked_until
                 END,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'max_attempts' => $maxAttempts,
            'lock_minutes' => $lockMinutes,
            'id' => $userId,
        ]);
    }

    public function resetLoginFailures(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET failed_login_attempts = 0,
                 locked_until = NULL,
                 last_login_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    public function isLocked(array $user): bool
    {
        $lockedUntil = $user['locked_until'] ?? null;
        if (!is_string($lockedUntil) || trim($lockedUntil) === '') {
            return false;
        }

        return strtotime($lockedUntil) > time();
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countAll(): int
    {
        $stmt = Database::connection()->query('SELECT COUNT(*) AS total FROM users');
        $row = $stmt->fetch();

        return (int) ($row['total'] ?? 0);
    }

    public function createPasswordResetToken(string $email): ?string
    {
        $user = $this->findByEmail($email);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);

        $stmt = Database::connection()->prepare(
            'INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (:email, :token, :expires_at)'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'token' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function verifyPasswordResetToken(string $token): ?array
    {
        $hashedToken = hash('sha256', $token);

        $stmt = Database::connection()->prepare(
            'SELECT prt.*, u.id as user_id, u.email, u.name 
             FROM password_reset_tokens prt
             JOIN users u ON u.email = prt.email
              WHERE prt.token = :token 
                AND prt.expires_at > UTC_TIMESTAMP() 
                AND prt.used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['token' => $hashedToken]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function markResetTokenAsUsed(string $token): void
    {
        $hashedToken = hash('sha256', $token);

        $stmt = Database::connection()->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE token = :token'
        );
        $stmt->execute(['token' => $hashedToken]);
    }

    public function updatePassword(int $userId, string $newPasswordHash): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'password_hash' => $newPasswordHash,
            'id' => $userId,
        ]);
    }

    public function createEmailVerificationToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);

        $stmt = Database::connection()->prepare(
            'INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function verifyEmailToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT evt.*, u.id as user_id, u.email, u.name, u.email_verified_at
             FROM email_verification_tokens evt
             JOIN users u ON u.id = evt.user_id
             WHERE evt.token = :token 
               AND evt.expires_at > NOW() 
               AND evt.verified_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['token' => hash('sha256', $token)]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function markEmailAsVerified(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET email_verified_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);

        $stmt = Database::connection()->prepare(
            'UPDATE email_verification_tokens SET verified_at = NOW() WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function isEmailVerified(array $user): bool
    {
        $verifiedAt = $user['email_verified_at'] ?? null;
        if ($verifiedAt === null || trim((string) $verifiedAt) === '') {
            return false;
        }
        return strtotime((string) $verifiedAt) > 0;
    }
}

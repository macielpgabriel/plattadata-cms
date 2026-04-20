<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $result = self::attemptWithResult($email, $password);
        return (bool) ($result['ok'] ?? false);
    }

    public static function attemptWithResult(string $email, string $password): array
    {
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);

        if (!$user || !(int) $user['is_active']) {
            return [
                'ok' => false,
                'reason' => 'invalid_or_inactive',
            ];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $repo->registerFailedLogin((int) $user['id']);

            return [
                'ok' => false,
                'reason' => 'invalid_credentials',
                'user' => $user,
            ];
        }

        $repo->resetLoginFailures((int) $user['id']);

        return [
            'ok' => true,
            'reason' => 'ok',
            'user' => $user,
        ];
    }

    public static function loginByUserId(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }

        return (new UserRepository())->findById((int) $id);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function can(array $roles): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        return in_array($user['role'], $roles, true);
    }
}

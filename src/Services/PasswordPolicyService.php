<?php

declare(strict_types=1);

namespace App\Services;

final class PasswordPolicyService
{
    public function validate(string $password): ?string
    {
        $minLength = max(8, (int) config('app.security.password_min_length', 12));

        if (strlen($password) < $minLength) {
            return 'A senha deve ter pelo menos ' . $minLength . ' caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'A senha deve ter ao menos 1 letra maiuscula.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'A senha deve ter ao menos 1 letra minuscula.';
        }

        if (!preg_match('/\d/', $password)) {
            return 'A senha deve ter ao menos 1 numero.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'A senha deve ter ao menos 1 caractere especial.';
        }

        $hibp = new HIBPPasswordService();
        if ($hibp->isPwned($password)) {
            return 'Esta senha ja foi exposta em vazamentos de dados. Escolha outra.';
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\MailService;
use App\Services\PasswordPolicyService;
use App\Services\ValidationService;

final class UserController
{
    private ValidationService $validator;
    private const PASSWORD_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';

    public function __construct()
    {
        $this->validator = new ValidationService();
    }

    public function index(): void
    {
        View::render('users/index', [
            'title' => 'Usuarios',
            'users' => (new UserRepository())->paginate(),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
            'roles' => config('roles'),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function store(): void
    {
        $data = [
            'name' => $this->validator->sanitizeName((string) ($_POST['name'] ?? '')),
            'email' => $this->validator->sanitizeEmail((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'role' => (string) ($_POST['role'] ?? 'viewer'),
            'is_active' => isset($_POST['is_active']) && $_POST['is_active'] !== '0' ? 1 : 0,
        ];

        if (!$this->validator->name($data['name'])) {
            Session::flash('error', 'Nome invalido. Use apenas letras e espacos (minimo 2 caracteres).');
            redirect('/usuarios');
        }

        if (!$this->validator->email($data['email'])) {
            Session::flash('error', 'E-mail invalido.');
            redirect('/usuarios');
        }

        if ($data['password'] === '') {
            $data['password'] = self::generateSecurePassword();
        }

        if (!array_key_exists($data['role'], config('roles'))) {
            Session::flash('error', 'Perfil de usuario invalido.');
            redirect('/usuarios');
        }

        $passwordError = (new PasswordPolicyService())->validate($data['password']);
        if ($passwordError !== null) {
            Session::flash('error', $passwordError);
            redirect('/usuarios');
        }

        $data['two_factor_enabled'] = $data['role'] === 'admin' ? 1 : 0;
        
        $plainPassword = $data['password'];
        $data['password_hash'] = password_hash($plainPassword, PASSWORD_ARGON2ID);

        $userId = (new UserRepository())->create($data);
        $adminEmail = (string) config('mail.admin_email', '');
        if ($adminEmail !== '') {
            (new MailService())->send(
                $adminEmail,
                'Novo usuário criado no CMS',
                str_replace(['ID', 'NOME', 'EMAIL', 'PERFIL', 'SENHA'], [
                    e((string) $userId),
                    e($data['name']),
                    e($data['email']),
                    e($data['role']),
                    e($plainPassword)
                ], <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #d1fae5; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Novo Usuário Criado</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Um novo usuário foi criado no CMS.</p>
</div>
<div style="background: #f9fafb; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left;">
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">ID:</strong> ID</p>
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">Nome:</strong> NOME</p>
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">E-mail:</strong> EMAIL</p>
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">Perfil:</strong> PERFIL</p>
    <p style="margin: 0;"><strong style="color: #374151;">Senha Temporária:</strong> SENHA</p>
</div>
HTML
                )
            );
        }
        Session::flash('success', 'Usuario criado com sucesso.');
        redirect('/usuarios');
    }

    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Usuario invalido.');
            redirect('/usuarios');
        }

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'role' => (string) ($_POST['role'] ?? 'viewer'),
            'is_active' => isset($_POST['is_active']) && $_POST['is_active'] !== '0' ? 1 : 0,
        ];

        if ($data['name'] === '' || $data['email'] === '') {
            Session::flash('error', 'Nome e e-mail sao obrigatorios.');
            redirect('/usuarios');
        }

        if (!array_key_exists($data['role'], config('roles'))) {
            Session::flash('error', 'Perfil de usuario invalido.');
            redirect('/usuarios');
        }

        $existingUser = (new UserRepository())->findById($id);
        if ($existingUser && $existingUser['role'] !== $data['role'] && !(new UserRepository())->isEmailVerified($existingUser)) {
            Session::flash('error', 'Nao e possivel alterar o perfil de um usuario com e-mail nao confirmado.');
            redirect('/usuarios');
        }

        if (!empty($data['password'])) {
            $passwordError = (new PasswordPolicyService())->validate($data['password']);
            if ($passwordError !== null) {
                Session::flash('error', $passwordError);
                redirect('/usuarios');
            }
            $data['password_hash'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        }

        $data['two_factor_enabled'] = $data['role'] === 'admin' ? 1 : 0;

        (new UserRepository())->update($id, $data);
        Session::flash('success', 'Usuario atualizado com sucesso.');
        redirect('/usuarios');
    }

    public function delete(array $params): void
    {
        if (!Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Seu perfil nao possui permissao para excluir usuarios.';
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Usuario invalido.');
            redirect('/usuarios');
        }

        $currentUser = Auth::user();
        if ((int) ($currentUser['id'] ?? 0) === $id) {
            Session::flash('error', 'Voce nao pode excluir seu proprio usuario.');
            redirect('/usuarios');
        }

        if (!Csrf::validate($_POST['_token'] ?? null)) {
            Session::flash('error', 'Token CSRF invalido.');
            redirect('/usuarios');
        }

        (new UserRepository())->delete($id);
        Session::flash('success', 'Usuario excluido com sucesso.');
        redirect('/usuarios');
    }

    private static function generateSecurePassword(int $length = 12): string
    {
        $chars = self::PASSWORD_CHARS;
        $charsLength = strlen($chars);
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
        
        return $password;
    }
}

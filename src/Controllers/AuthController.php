<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\IpBlocklistService;
use App\Services\MailService;
use App\Services\SetupService;
use App\Services\ValidationService;

final class AuthController
{
    private UserRepository $users;
    private ValidationService $validator;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->validator = new ValidationService();
    }

    public function showLogin(): void
    {
        $setup = new SetupService();
        if (!$setup->isDatabaseReady()) {
            redirect('/install');
        }

        $setup->ensureInitialAdmin();

        if (Auth::check()) {
            redirect('/dashboard');
        }

        unset($_SESSION['pending_auth']);

        View::render('auth/login', [
            'title' => 'Entrar',
            'flash' => Session::flash('error'),
            'success' => Session::flash('success'),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function login(): void
    {

        $email = $this->validator->sanitizeEmail((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            Session::flash('error', 'Preencha e-mail e senha.');
            redirect('/login');
        }

        if (!$this->validator->email($email)) {
            Session::flash('error', 'E-mail invalido.');
            redirect('/login');
        }

        $result = Auth::attemptWithResult($email, $password);
        if (!(bool) ($result['ok'] ?? false)) {
            $reason = (string) ($result['reason'] ?? 'invalid');
            
            // Registrar tentativa falha e possivelmente bloquear IP
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $attempts = IpBlocklistService::recordFailedAttempt($clientIp);
            
            if ($reason === 'locked') {
                $lockedUntil = (string) ($result['locked_until'] ?? '');
                Session::flash('error', 'Conta bloqueada temporariamente. Tente apos ' . ($lockedUntil !== '' ? $lockedUntil : 'alguns minutos') . '.');
                redirect('/login');
            }

            // Mensagem generica para evitar enumeracao de usuarios
            Session::flash('error', 'E-mail ou senha incorretos.');
            
            // Log de tentativa falha
            Logger::warning('Tentativa de login falhou', [
                'email' => $email,
                'ip' => $clientIp,
                'reason' => $reason,
                'attempt_number' => $attempts
            ]);
            
            redirect('/login');
        }
        
        // Log de login bem-sucedido
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        IpBlocklistService::clearFailedAttempts($clientIp);
        Logger::info('Login realizado', [
            'email' => $email,
            'ip' => $clientIp
        ]);

        $user = is_array($result['user'] ?? null) ? $result['user'] : null;
        if (!$user) {
            Session::flash('error', 'Falha ao identificar usuario autenticado.');
            redirect('/login');
        }

        if ($this->requiresTwoFactor($user)) {
            if (!$this->startTwoFactorFlow($user)) {
                Session::flash('error', 'Nao foi possivel enviar o codigo de verificacao. Verifique o e-mail configurado.');
                redirect('/login');
            }
            redirect('/login/2fa');
        }

        Auth::loginByUserId((int) $user['id']);
        unset($_SESSION['pending_auth']);
        Session::flash('success', 'Bem-vindo de volta!');
        redirect('/dashboard');
    }

    public function showForgotForm(): void
    {
        View::render('auth/forgot_password', [
            'title' => 'Recuperar Acesso',
            'metaRobots' => 'noindex,nofollow',
            'flash' => Session::flash('error'),
            'success' => Session::flash('success'),
        ]);
    }

    public function sendResetLink(): void
    {

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        
        if ($email !== '') {
            $token = $this->users->createPasswordResetToken($email);

            if ($token) {
                $baseUrl = rtrim((string) config('app.url', 'https://plattadata.com'), '/');
                $link = $baseUrl . '/redefinir-senha/' . $token;

                (new MailService())->send(
                    $email,
                    'Recuperação de Senha - Plattadata',
                    str_replace('LINK', $link, <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #fef3c7; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Recuperação de Senha</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Você solicitou a recuperação de senha da sua conta.</p>
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="LINK" style="display: inline-block; background: #0f766e; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 16px;">Criar Nova Senha</a>
</div>
<p style="color: #6b7280; text-align: center; font-size: 14px;">Este link expira em <strong>1 hora</strong>.</p>
<div style="background: #fef2f2; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center; border-left: 4px solid #f59e0b;">
    <p style="margin: 0; color: #92400e; font-size: 14px;">Se você não solicitou esta recuperação, pode ignorar este e-mail com segurança.</p>
</div>
HTML
                    )
                );
            }
        }

        Session::flash('success', 'Se o e-mail estiver cadastrado, voce recebera um link de recuperacao em instantes.');
        redirect('/recuperar-senha');
    }

    public function showResetForm(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        View::render('auth/reset_password', [
            'title' => 'Redefinir Senha',
            'token' => $token,
            'metaRobots' => 'noindex,nofollow',
            'flash' => Session::flash('error'),
        ]);
    }

    public function resetPassword(): void
    {

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');

        if ($token === '' || $password === '' || $passwordConfirm === '') {
            Session::flash('error', 'Preencha todos os campos.');
            redirect('/redefinir-senha/' . $token);
        }

        if ($password !== $passwordConfirm) {
            Session::flash('error', 'As senhas nao coincidem.');
            redirect('/redefinir-senha/' . $token);
        }

        $minLength = (int) config('app.security.password_min_length', 12);
        if (strlen($password) < $minLength) {
            Session::flash('error', "A senha deve ter pelo menos $minLength caracteres.");
            redirect('/redefinir-senha/' . $token);
        }

        $passwordError = (new \App\Services\PasswordPolicyService())->validate($password);
        if ($passwordError !== null) {
            Session::flash('error', $passwordError);
            redirect('/redefinir-senha/' . $token);
        }

        $resetData = $this->users->verifyPasswordResetToken($token);
        if (!$resetData) {
            Session::flash('error', 'Link invalido ou expirado. Solicite uma nova recuperacao.');
            redirect('/recuperar-senha');
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $this->users->updatePassword((int) $resetData['user_id'], $passwordHash);
        $this->users->markResetTokenAsUsed($token);

        Session::flash('success', 'Senha redefinida com sucesso. Faca login com sua nova senha.');
        redirect('/login');
    }

    public function showTwoFactor(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        if (!$this->hasPendingTwoFactor()) {
            Session::flash('error', 'Sessao expirada.');
            redirect('/login');
        }

        View::render('auth/two_factor', [
            'title' => 'Verificacao',
            'flash' => Session::flash('error'),
            'success' => Session::flash('success'),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function verifyTwoFactor(): void
    {

        if (!$this->hasPendingTwoFactor()) {
            redirect('/login');
        }

        $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? '')) ?? '';
        $pending = $_SESSION['pending_auth'];

        if ($code === '') {
            Session::flash('error', 'Informe o codigo.');
            redirect('/login/2fa');
        }

        $hash = (string) ($pending['otp_hash'] ?? '');
        $expiresAt = (int) ($pending['otp_expires_at'] ?? 0);
        if ($hash === '' || time() > $expiresAt || !password_verify($code, $hash)) {
            if (time() > $expiresAt) {
                unset($_SESSION['pending_auth']);
                Session::flash('error', 'Codigo expirado. Solicite um novo.');
            } else {
                Session::flash('error', 'Codigo invalido.');
            }
            redirect('/login/2fa');
        }

        Auth::loginByUserId((int) $pending['user_id']);
        unset($_SESSION['pending_auth']);
        redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    private function requiresTwoFactor(array $user): bool
    {
        return (int) ($user['two_factor_enabled'] ?? 0) === 1;
    }

    private function startTwoFactorFlow(array $user): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['pending_auth'] = [
            'user_id' => (int) $user['id'],
            'otp_hash' => password_hash($code, PASSWORD_DEFAULT),
            'otp_expires_at' => time() + 600,
        ];

        return (new MailService())->send(
            (string) $user['email'],
            'Código de Verificação - Plattadata',
            str_replace('CODIGO', $code, <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #dbeafe; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Código de Verificação</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Use o código abaixo para autenticar-se:</p>
</div>
<div style="background: #f3f4f6; border-radius: 8px; padding: 25px; margin: 25px 0; text-align: center;">
    <p style="margin: 0 0 10px; color: #6b7280; font-size: 14px;">Seu código de verificação:</p>
    <p style="margin: 0; font-size: 32px; font-weight: bold; color: #111827; letter-spacing: 8px;">CODIGO</p>
</div>
<p style="color: #6b7280; text-align: center; font-size: 14px;">Este código expira em <strong>10 minutos</strong>.</p>
<div style="background: #fef2f2; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center; border-left: 4px solid #dc2626;">
    <p style="margin: 0; color: #92400e; font-size: 14px;">Se não foi você, ignore este e-mail.</p>
</div>
HTML
            )
        );
    }

    private function hasPendingTwoFactor(): bool
    {
        return isset($_SESSION['pending_auth']);
    }

    public function unsubscribe(): void
    {
        $email = $_GET['email'] ?? '';
        
        View::render('auth/unsubscribe', [
            'title' => 'Cancelar Inscrição',
            'metaRobots' => 'noindex,nofollow',
            'email' => $email,
        ]);
    }

    public function processUnsubscribe(): void
    {

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        
        if ($email === '') {
            Session::flash('error', 'Informe seu e-mail.');
            redirect('/unsubscribe');
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findByEmail($email);
        
        if ($user) {
            $userRepo->update((int) $user['id'], ['notifications_enabled' => 0]);
            Session::flash('success', 'Inscrição cancelada com sucesso. Você não receberá mais e-mails.');
        } else {
            Session::flash('success', 'Se este e-mail estiver cadastrado, a inscrição foi cancelada.');
        }

        redirect('/unsubscribe');
    }
}
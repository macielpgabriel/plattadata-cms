<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Services\DisposableEmailService;
use App\Services\MailService;
use App\Services\PasswordPolicyService;

final class RegisterController
{
    private UserRepository $users;
    private PasswordPolicyService $passwordPolicy;
    private MailService $mail;
    private DisposableEmailService $disposableEmail;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->passwordPolicy = new PasswordPolicyService();
        $this->mail = new MailService();
        $this->disposableEmail = new DisposableEmailService();
    }

    public function show(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        View::render('auth/register', [
            'title' => 'Criar Conta',
            'metaRobots' => 'noindex,nofollow',
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function store(): void
    {

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            Session::flash('error', 'Todos os campos sao obrigatorios.');
            redirect('/cadastro');
        }

        if ($password !== $confirm) {
            Session::flash('error', 'As senhas nao conferem.');
            redirect('/cadastro');
        }

        if ($this->users->findByEmail($email)) {
            Session::flash('error', 'Este e-mail ja esta em uso.');
            redirect('/cadastro');
        }

        if ($this->disposableEmail->isDisposable($email)) {
            Session::flash('error', 'E-mails temporarios/disposables nao sao permitidos. Use um e-mail real.');
            redirect('/cadastro');
        }

        $passwordError = $this->passwordPolicy->validate($password);
        if ($passwordError) {
            Session::flash('error', $passwordError);
            redirect('/cadastro');
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);
        
        $userId = $this->users->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => $hash,
            'role' => 'viewer',
            'is_active' => 1
        ]);

        if ($userId) {
            $token = $this->users->createEmailVerificationToken($userId);
            $baseUrl = rtrim((string) config('app.url', 'https://plattadata.com'), '/');
            $verifyLink = $baseUrl . '/verificar-email/' . $token;

            $this->mail->send(
                $email,
                'Confirme seu e-mail - Plattadata',
                str_replace(['NOME', 'LINK'], [$name, $verifyLink], <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #d1fae5; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Bem-vindo ao Plattadata!</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Olá, NOME! Obrigado por criar sua conta.</p>
</div>
<p style="color: #6b7280; text-align: center; margin: 20px 0;">Para confirmar seu e-mail, clique no botão abaixo:</p>
<div style="text-align: center; margin: 30px 0;">
    <a href="LINK" style="display: inline-block; background: #0f766e; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 16px;">Confirmar E-mail</a>
</div>
<p style="color: #6b7280; text-align: center; font-size: 14px;">Este link expira em <strong>24 horas</strong>.</p>
<div style="background: #f3f4f6; border-radius: 8px; padding: 15px; margin: 20px 0;">
    <p style="margin: 0 0 10px; color: #374151; font-size: 14px;"><strong>Ou copie este link:</strong></p>
    <p style="margin: 0; color: #6b7280; font-size: 12px; word-break: break-all;">LINK</p>
</div>
<div style="background: #fef2f2; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center; border-left: 4px solid #dc2626;">
    <p style="margin: 0; color: #92400e; font-size: 14px;">Se você não criou esta conta, ignore este e-mail.</p>
</div>
HTML
                )
            );

            Session::flash('success', 'Conta criada! Enviamos um e-mail de confirmacao para ' . e($email) . '. Verifique sua caixa de entrada ou spam.');
            redirect('/login');
        }

        Session::flash('error', 'Erro ao criar conta. Tente novamente.');
        redirect('/cadastro');
    }

    public function verify(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        
        if ($token === '') {
            $data = [
                'title' => 'E-mail Verificado',
                'success' => false,
                'message' => 'Token nao fornecido.',
            ];
        } else {
            $result = $this->users->verifyEmailToken($token);

            if ($result) {
                $this->users->markEmailAsVerified((int) $result['user_id']);
                $data = [
                    'title' => 'E-mail Verificado',
                    'success' => true,
                    'message' => 'Seu e-mail foi confirmado com sucesso! Agora voce pode fazer login.',
                    'userName' => $result['name'] ?? '',
                ];
            } else {
                $data = [
                    'title' => 'Link Invalido',
                    'success' => false,
                    'message' => 'Este link expirou ou ja foi utilizado. Solicite um novo registro.',
                ];
            }
        }

        View::render('auth/email_verified', $data + ['metaRobots' => 'noindex,nofollow']);
    }

    public function resendVerification(): void
    {

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'E-mail invalido'], 400);
        }

        if ($this->disposableEmail->isDisposable($email)) {
            Response::json(['error' => 'E-mail temporario nao permitido.'], 400);
        }

        $user = $this->users->findByEmail($email);

        if ($user && !$this->users->isEmailVerified($user)) {
            $token = $this->users->createEmailVerificationToken((int) $user['id']);
            $baseUrl = rtrim((string) config('app.url', 'https://plattadata.com'), '/');
            $verifyLink = $baseUrl . '/verificar-email/' . $token;

            $this->mail->send(
                $email,
                'Confirme seu e-mail - Plattadata',
                str_replace(['NOME', 'LINK'], [$user['name'], $verifyLink], <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #d1fae5; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Confirme seu E-mail</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Olá, NOME! Você solicitou o reenvio da confirmação de e-mail.</p>
</div>
<p style="color: #6b7280; text-align: center; margin: 20px 0;">Clique no botão abaixo para confirmar:</p>
<div style="text-align: center; margin: 30px 0;">
    <a href="LINK" style="display: inline-block; background: #0f766e; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 16px;">Confirmar E-mail</a>
</div>
<p style="color: #6b7280; text-align: center; font-size: 14px;">Este link expira em <strong>24 horas</strong>.</p>
HTML
                )
            );
        }

        Response::json(['success' => true, 'message' => 'Se o e-mail estiver cadastrado e nao verificado, enviamos um novo link.']);
    }
}
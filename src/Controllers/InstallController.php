<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Services\PasswordPolicyService;
use App\Services\SetupService;

final class InstallController
{
    public function show(): void
    {
        $setup = new SetupService();

        if ($setup->isDatabaseReady()) {
            redirect('/empresas');
        }

        $error = Session::flash('error');
        $success = Session::flash('success');

        $defaults = [
            'app_url' => (string) env('APP_URL', 'https://plattadata.com'),
            'db_host' => (string) env('DB_HOST', 'localhost'),
            'db_port' => (string) env('DB_PORT', '3306'),
            'db_name' => (string) env('DB_NAME', ''),
            'db_user' => (string) env('DB_USER', ''),
            'admin_name' => (string) env('CMS_ADMIN_NAME', 'Administrador'),
            'admin_email' => (string) env('CMS_ADMIN_EMAIL', 'admin@plattadata.com'),
        ];

        $this->renderForm($defaults, $error, $success);
    }

    public function save(): void
    {
        $data = [
            'app_url' => trim((string) ($_POST['app_url'] ?? '')),
            'db_host' => trim((string) ($_POST['db_host'] ?? '')),
            'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'db_name' => trim((string) ($_POST['db_name'] ?? '')),
            'db_user' => trim((string) ($_POST['db_user'] ?? '')),
            'db_pass' => (string) ($_POST['db_pass'] ?? ''),
            'admin_name' => trim((string) ($_POST['admin_name'] ?? 'Administrador')),
            'admin_email' => trim((string) ($_POST['admin_email'] ?? 'admin@plattadata.com')),
            'admin_password' => (string) ($_POST['admin_password'] ?? ''),
        ];

        if ($data['app_url'] === '' || $data['db_host'] === '' || $data['db_name'] === '' || $data['db_user'] === '' || $data['admin_email'] === '' || $data['admin_password'] === '') {
            Session::flash('error', 'Preencha todos os campos obrigatorios.');
            redirect('/install');
        }

        $passwordError = (new PasswordPolicyService())->validate($data['admin_password']);
        if ($passwordError !== null) {
            Session::flash('error', $passwordError);
            redirect('/install');
        }

        $setup = new SetupService();
        if (!$setup->saveInstallerConfig($data)) {
            Session::flash('error', 'Nao foi possivel salvar o .env. Verifique permissao de escrita no arquivo.');
            redirect('/install');
        }

        $setup->runInitialSetup();

        if (!$setup->isDatabaseReady()) {
            $technical = $setup->getLastConnectionError();
            $message = 'Configuracao salva, mas a conexao com banco falhou. Verifique host, usuario, senha e permissoes do banco no painel Hostinger.';
            if (!empty($technical)) {
                $message .= ' Erro tecnico: ' . $technical;
            }
            Session::flash('error', $message);
            redirect('/install');
        }

        Session::flash('success', 'Instalacao concluida com sucesso.');
        redirect('/login');
    }

    private function renderForm(array $defaults, ?string $error, ?string $success): void
    {
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="pt-BR">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Instalacao do CMS</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; }
                .wrap { max-width: 760px; margin: 40px auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
                h1 { margin-top: 0; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
                .full { grid-column: 1 / -1; }
                label { font-size: 13px; color: #334; display: block; margin-bottom: 6px; }
                input { width: 100%; padding: 10px; border: 1px solid #ccd; border-radius: 8px; box-sizing: border-box; }
                button { padding: 12px 18px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; cursor: pointer; }
                .alert { padding: 10px; border-radius: 8px; margin-bottom: 14px; }
                .err { background: #ffe9e9; color: #8a2121; }
                .ok { background: #e9ffef; color: #1a6930; }
                @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
            </style>
        </head>
        <body>
        <div class="wrap">
            <h1>Instalacao do CMS (Hostinger)</h1>
            <p>Informe os dados do banco criado no painel da hospedagem. O CMS criara as tabelas e o admin no primeiro acesso.</p>

            <?php if (!empty($error)): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
            <?php if (!empty($success)): ?><div class="alert ok"><?= e($success) ?></div><?php endif; ?>

            <form method="post" action="/install">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <div class="grid">
                    <div class="full">
                        <label>URL do site</label>
                        <input type="text" name="app_url" value="<?= e($defaults['app_url']) ?>" required>
                    </div>
                    <div>
                        <label>DB Host</label>
                        <input type="text" name="db_host" value="<?= e($defaults['db_host']) ?>" required>
                    </div>
                    <div>
                        <label>DB Porta</label>
                        <input type="text" name="db_port" value="<?= e($defaults['db_port']) ?>" required>
                    </div>
                    <div>
                        <label>DB Nome</label>
                        <input type="text" name="db_name" value="<?= e($defaults['db_name']) ?>" required>
                    </div>
                    <div>
                        <label>DB Usuario</label>
                        <input type="text" name="db_user" value="<?= e($defaults['db_user']) ?>" required>
                    </div>
                    <div class="full">
                        <label>DB Senha</label>
                        <input type="password" name="db_pass" required>
                    </div>
                    <div>
                        <label>Admin Nome</label>
                        <input type="text" name="admin_name" value="<?= e($defaults['admin_name']) ?>" required>
                    </div>
                    <div>
                        <label>Admin E-mail</label>
                        <input type="email" name="admin_email" value="<?= e($defaults['admin_email']) ?>" required>
                    </div>
                    <div class="full">
                        <label>Admin Senha</label>
                        <input type="password" name="admin_password" required>
                        <small>Minimo 12 caracteres com maiuscula, minuscula, numero e simbolo.</small>
                    </div>
                </div>
                <p style="margin-top:16px"><button type="submit">Salvar e instalar</button></p>
            </form>
        </div>
        </body>
        </html>
        <?php
    }
}

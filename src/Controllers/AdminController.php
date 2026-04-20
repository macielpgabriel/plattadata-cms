<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Database;
use App\Services\SetupService;
use App\Services\ObservabilityService;
use App\Services\GoogleDriveService;
use App\Services\GoogleDriveServiceOAuth;
use App\Services\IpBlocklistService;
use App\Repositories\SiteSettingRepository;
use App\Core\Cache;

final class AdminController
{
    public function index(): void
    {
        if (!(new SetupService())->isDatabaseReady()) {
            redirect('/install');
        }

        $service = new ObservabilityService();
        $health = $service->healthSnapshot();
        $metrics = $service->adminMetrics();
        $settings = (new SiteSettingRepository())->allAssoc();
        $db = Database::connection();

        View::render('admin/dashboard', [
            'title' => 'Admin',
            'health' => $health,
            'metrics' => $metrics,
            'settings' => $settings,
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function clearCache(): void
    {
        try {
            $cleared = Cache::gc();
            Session::flash('success', "Cache limpo! ($cleared arquivos removidos)");
        } catch (\Throwable $e) {
            Session::flash('error', 'Erro: ' . $e->getMessage());
        }

        redirect('/admin');
    }

    public function showDriveUpload(): void
    {
        $drive = new GoogleDriveService();
        $driveOAuth = new GoogleDriveServiceOAuth();
        $isEnabled = $drive->isEnabled();

        $credentialsPath = base_path('storage/credentials/google-drive.json');
        $hasCredentialsFile = is_file($credentialsPath);

        View::render('admin/drive-upload', [
            'title' => 'Configurar Google Drive',
            'isEnabled' => $isEnabled,
            'hasCredentialsFile' => $hasCredentialsFile,
            'oauthEnabled' => $driveOAuth->isEnabled(),
            'oauthAuthenticated' => $driveOAuth->isAuthenticated(),
            'oauthUser' => $driveOAuth->getUser(),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function uploadDriveCredentials(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            Session::flash('error', 'Sessão expirada.');
            redirect('/admin/configuracoes');
        }

        $file = $_FILES['credentials'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Selecione um arquivo JSON válido.');
            redirect('/admin/configuracoes');
        }

        $content = file_get_contents($file['tmp_name']);
        $json = json_decode($content, true);

        if (!$json || !isset($json['client_email']) || !isset($json['private_key'])) {
            Session::flash('error', 'Arquivo JSON inválido. Use o arquivo de credenciais da conta de serviço do Google.');
            redirect('/admin/configuracoes');
        }

        $dir = base_path('storage/credentials');

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0750, true)) {
                Session::flash('error', 'Erro ao criar diretório. Verifique permissões da pasta storage/.');
                redirect('/admin/configuracoes');
            }
        }

        $targetPath = $dir . '/google-drive.json';

        if (file_put_contents($targetPath, $content) === false) {
            Session::flash('error', 'Erro ao salvar credenciais. Verifique permissões da pasta storage/credentials/.');
            redirect('/admin/configuracoes');
        }

        redirect('/admin/drive-upload');
    }

    public function listBlockedIPs(): void
    {
        $db = Database::connection();
        $stmt = $db->query("SELECT ip, reason, created_at, expires_at FROM blocked_ips WHERE expires_at IS NULL OR expires_at > NOW() ORDER BY created_at DESC");
        $blockedIPs = $stmt->fetchAll() ?: [];

        $stmt = $db->query("SELECT ip, attempts, last_attempt FROM ip_failed_attempts ORDER BY last_attempt DESC LIMIT 50");
        $failedAttempts = $stmt->fetchAll() ?: [];

        View::render('admin/blocked-ips', [
            'title' => 'IPs Bloqueados',
            'blockedIPs' => $blockedIPs,
            'failedAttempts' => $failedAttempts,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function unblockIP(): void
    {
        $ip = trim($_POST['ip'] ?? '');

        if ($ip === '') {
            Session::flash('error', 'IP inválido.');
            redirect('/admin/bloqueados');
        }

        IpBlocklistService::unblock($ip);

        $db = Database::connection();
        $stmt = $db->prepare("DELETE FROM ip_failed_attempts WHERE ip = ?");
        $stmt->execute([$ip]);

        Session::flash('success', "IP {$ip} desbloqueado.");
        redirect('/admin/bloqueados');
    }

    public function unblockAllIPs(): void
    {
        $db = Database::connection();
        $db->exec("DELETE FROM blocked_ips");
        $db->exec("DELETE FROM ip_failed_attempts");

        Session::flash('success', 'Todos os IPs desbloqueados.');
        redirect('/admin/bloqueados');
    }
}
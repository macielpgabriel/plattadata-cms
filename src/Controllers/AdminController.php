<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Database;
use App\Services\SetupService;
use App\Services\ObservabilityService;
use App\Services\LocalStorageService;
use App\Services\IpBlocklistService;
use App\Repositories\SiteSettingRepository;
use App\Core\Cache;

final class AdminController
{
    private function setNoCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public function index(): void
    {
        $this->setNoCacheHeaders();

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

    public function testStorageConnection(): void
    {
        $storage = new LocalStorageService();
        $result = $storage->testConnection();

        if ($result['status'] === 'connected') {
            Session::flash('success', 'Armazenamento local funcionando!');
        } else {
            Session::flash('error', 'Erro: ' . ($result['message'] ?? 'Erro desconhecido'));
        }

        redirect('/admin');
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
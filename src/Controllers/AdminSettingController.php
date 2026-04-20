<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\SiteSettingRepository;
use App\Support\SiteSettings;

final class AdminSettingController
{
    public function edit(): void
    {
        redirect('/admin#identidade');
    }

    public function autosave(): void
    {
        header('Content-Type: application/json');
        
        if (!Csrf::validate($_POST['_token'] ?? '')) {
            echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
            return;
        }

        $payload = [
            'site_name' => $this->cleanText($_POST['site_name'] ?? '', 120),
            'site_description' => $this->cleanText($_POST['site_description'] ?? '', 255),
            'homepage_title' => $this->cleanText($_POST['homepage_title'] ?? '', 120),
            'homepage_subtitle' => $this->cleanText($_POST['homepage_subtitle'] ?? '', 255),
            'homepage_search_placeholder' => $this->cleanText($_POST['homepage_search_placeholder'] ?? '', 120),
            'homepage_public_notice' => $this->cleanText($_POST['homepage_public_notice'] ?? '', 255),
            'contact_email' => $this->cleanEmail($_POST['contact_email'] ?? ''),
            'contact_phone' => $this->cleanText($_POST['contact_phone'] ?? '', 40),
            'contact_whatsapp' => $this->cleanText($_POST['contact_whatsapp'] ?? '', 40),
            'footer_text' => $this->cleanText($_POST['footer_text'] ?? '', 180),
            'seo_default_robots' => $this->cleanRobots($_POST['seo_default_robots'] ?? ''),
            'companies_per_page' => (string) $this->toInt($_POST['companies_per_page'] ?? '15', 5, 100, 15),
            'public_search_rate_limit_per_minute' => (string) $this->toInt($_POST['public_search_rate_limit_per_minute'] ?? '20', 1, 300, 20),
            'sitemap_company_limit' => (string) $this->toInt($_POST['sitemap_company_limit'] ?? '10000', 100, 50000, 10000),
            'public_search_enabled' => isset($_POST['public_search_enabled']) ? '1' : '0',
        ];

        if ($payload['site_name'] === '') {
            echo json_encode(['ok' => false, 'error' => 'Nome do site é obrigatório']);
            return;
        }

        $repo = new SiteSettingRepository();
        $repo->upsertMany($payload);
        SiteSettings::setCache($repo->allAssoc());

        echo json_encode(['ok' => true, 'message' => 'Salvo automaticamente']);
    }

    public function update(): void
    {
        $siteSettings = new SiteSettingRepository();

        $payload = [
            'site_name' => $this->cleanText($_POST['site_name'] ?? '', 120),
            'site_description' => $this->cleanText($_POST['site_description'] ?? '', 255),
            'homepage_title' => $this->cleanText($_POST['homepage_title'] ?? '', 120),
            'homepage_subtitle' => $this->cleanText($_POST['homepage_subtitle'] ?? '', 255),
            'homepage_search_placeholder' => $this->cleanText($_POST['homepage_search_placeholder'] ?? '', 120),
            'homepage_public_notice' => $this->cleanText($_POST['homepage_public_notice'] ?? '', 255),
            'contact_email' => $this->cleanEmail($_POST['contact_email'] ?? ''),
            'contact_phone' => $this->cleanText($_POST['contact_phone'] ?? '', 40),
            'contact_whatsapp' => $this->cleanText($_POST['contact_whatsapp'] ?? '', 40),
            'footer_text' => $this->cleanText($_POST['footer_text'] ?? '', 180),
            'seo_default_robots' => $this->cleanRobots($_POST['seo_default_robots'] ?? ''),

            'companies_per_page' => (string) $this->toInt($_POST['companies_per_page'] ?? '15', 5, 100, 15),
            'public_search_rate_limit_per_minute' => (string) $this->toInt($_POST['public_search_rate_limit_per_minute'] ?? '20', 1, 300, 20),
            'sitemap_company_limit' => (string) $this->toInt($_POST['sitemap_company_limit'] ?? '10000', 100, 50000, 10000),
            'public_search_enabled' => isset($_POST['public_search_enabled']) ? '1' : '0',
        ];

        if ($payload['site_name'] === '') {
            Session::flash('error', 'Nome do site e obrigatorio.');
            redirect('/admin/configuracoes');
        }

        $repo = new SiteSettingRepository();
        $repo->upsertMany($payload);
        SiteSettings::setCache($repo->allAssoc());

        Session::flash('success', 'Configuracoes atualizadas com sucesso.');
        redirect('/admin/configuracoes');
    }

    public function clearCache(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? '')) {
            Session::flash('error', 'Token CSRF invalido.');
            redirect('/admin/configuracoes');
        }
        $cachePath = base_path('storage/cache');
        if (!is_dir($cachePath)) {
            Session::flash('info', 'O cache ja esta vazio.');
            redirect('/admin/configuracoes');
        }

        $files = glob($cachePath . '/*.cache');
        $count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        Session::flash('success', "Cache limpo com sucesso! ($count arquivos removidos)");
        redirect('/admin/configuracoes');
    }

    private const ALLOWED_BACKUP_TABLES = [
        'users', 'password_reset_tokens', 'user_favorites', 'favorite_groups',
        'states', 'municipalities', 'companies', 'company_changes',
        'company_change_subscriptions', 'company_partners', 'company_enrichments',
        'company_source_payloads', 'company_snapshots', 'company_query_logs',
        'lgpd_audit_logs', 'email_logs', 'site_settings', 'cnpj_blacklist',
        'notification_logs', 'email_verification_tokens', 'cnae_activities',
        'company_removal_requests', 'exchange_rates', 'economic_indicators',
        'impostometro_data', 'impostometro_arrecadacao', 'compliance_cache',
        'cnae_statistics', 'address_cache', 'municipality_cache',
        'blocked_ips', 'ip_failed_attempts', 'api_keys', 'api_access_logs',
        'company_mentions', 'company_mentions_history', 'mention_alert_subscriptions',
        'company_competitors', 'partner_history', 'municipality_vehicle_types',
        'audit_logs', ' brasil_info', 'ddd_cache', 'company_tax_data',
    ];

    public function downloadBackup(): void
    {
        $db = \App\Core\Database::connection();
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch(\PDO::FETCH_NUM)) {
            $tableName = $row[0];
            if (!in_array($tableName, self::ALLOWED_BACKUP_TABLES, true)) {
                continue;
            }
            $tables[] = $tableName;
        }

        $sql = "-- Backup PlattaData CMS\n-- Gerado em: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $res = $db->query("SHOW CREATE TABLE `$safeTable`")->fetch(\PDO::FETCH_ASSOC);
            $sql .= "\n\n" . $res['Create Table'] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$safeTable`")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $keys = array_keys($row);
                $values = array_values($row);
                $escapedValues = array_map(function ($v) use ($db) {
                    if ($v === null)
                        return 'NULL';
                    return $db->quote((string) $v);
                }, $values);
                $sql .= "INSERT INTO `$safeTable` (`" . implode('`, `', $keys) . "`) VALUES (" . implode(', ', $escapedValues) . ");\n";
            }
        }

        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;";

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_His') . '.sql"');
        echo $sql;
        exit;
    }

    private function cleanText(mixed $value, int $maxLength): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLength);
        }

        return substr($text, 0, $maxLength);
    }

    private function cleanEmail(mixed $value): string
    {
        $email = strtolower(trim((string) $value));
        if ($email === '') {
            return '';
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function cleanRobots(mixed $value): string
    {
        $robots = strtolower(trim((string) $value));
        $allowed = [
            'index,follow',
            'index,nofollow',
            'noindex,follow',
            'noindex,nofollow',
        ];

        if (!in_array($robots, $allowed, true)) {
            return 'index,follow';
        }

        return $robots;
    }

    private function toInt(mixed $value, int $min, int $max, int $default): int
    {
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            return $default;
        }

        return $number;
    }
}

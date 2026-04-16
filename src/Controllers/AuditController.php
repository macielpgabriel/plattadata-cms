<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogService;

final class AuditController
{
    private const QUICK_FILTERS = [
        'today' => 'Hoje',
        '7days' => 'Últimos 7 dias',
        '30days' => 'Últimos 30 dias',
    ];

    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $action = $_GET['action'] ?? '';
        $entityType = $_GET['entity'] ?? '';
        $userId = !empty($_GET['user']) ? (int) $_GET['user'] : null;
        $ipAddress = $_GET['ip'] ?? '';
        $startDate = $_GET['start'] ?? '';
        $endDate = $_GET['end'] ?? '';
        $quickFilter = $_GET['quick'] ?? '';
        $search = $_GET['q'] ?? '';

        $startDate = $this->applyQuickFilter($quickFilter, $startDate);

        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $allLogs = AuditLogService::getRecentLogs(
            $perPage,
            $action ?: null,
            $userId,
            $entityType ?: null,
            $startDate ?: null,
            $endDate ?: null,
            $search ?: null,
            $ipAddress ?: null
        );

        $allLogs = AuditLogService::enrichCompanyNames($allLogs);
        $logs = array_slice($allLogs, $offset, $perPage);

        $total = AuditLogService::countLogs(
            $action ?: null,
            $userId,
            $entityType ?: null,
            $startDate ?: null,
            $endDate ?: null,
            $search ?: null
        );
        $totalPages = max(1, (int) ceil($total / $perPage));

        $actions = AuditLogService::getDistinctActions();
        $entityTypes = AuditLogService::getDistinctEntityTypes();
        $users = AuditLogService::getUsersWithActivity();
        $ips = AuditLogService::getDistinctIps();
        $dashboard = AuditLogService::getDashboardStats();
        $alerts = AuditLogService::getAlerts();

        $params = $_GET;
        unset($params['page']);

        $stats = $this->calculateStats($allLogs);

        View::render('admin/audit/index', [
            'title' => 'Auditoria',
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'action' => $action,
            'entityType' => $entityType,
            'userId' => $userId,
            'ipAddress' => $ipAddress,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'quickFilter' => $quickFilter,
            'search' => $search,
            'actions' => $actions,
            'entityTypes' => $entityTypes,
            'users' => $users,
            'ips' => $ips,
            'queryParams' => $params,
            'stats' => $stats,
            'dashboard' => $dashboard,
            'alerts' => $alerts,
            'quickFilters' => self::QUICK_FILTERS,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    private function applyQuickFilter(string $quickFilter, string $currentStartDate): string
    {
        if ($currentStartDate) {
            return $currentStartDate;
        }

        $today = date('Y-m-d');

        return match ($quickFilter) {
            'today' => $today,
            '7days' => date('Y-m-d', strtotime('-7 days')),
            '30days' => date('Y-m-d', strtotime('-30 days')),
            default => '',
        };
    }

    private function calculateStats(array $logs): array
    {
        $byAction = [];
        foreach ($logs as $log) {
            $action = $log['action'] ?? 'unknown';
            $byAction[$action] = ($byAction[$action] ?? 0) + 1;
        }

        return [
            'by_action' => $byAction,
            'total' => count($logs),
        ];
    }

    public function export(): void
    {
        if (!Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        $action = $_GET['action'] ?? '';
        $entityType = $_GET['entity'] ?? '';
        $userId = !empty($_GET['user']) ? (int) $_GET['user'] : null;
        $startDate = $_GET['start'] ?? '';
        $endDate = $_GET['end'] ?? '';
        $quickFilter = $_GET['quick'] ?? '';
        $search = $_GET['q'] ?? '';

        $startDate = $this->applyQuickFilter($quickFilter, $startDate);

        $logs = AuditLogService::getRecentLogs(
            10000,
            $action ?: null,
            $userId,
            $entityType ?: null,
            $startDate ?: null,
            $endDate ?: null,
            $search ?: null
        );

        $logs = AuditLogService::enrichCompanyNames($logs);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', 'Usuário ID', 'Usuário Nome', 'Usuário Email', 'Ação', 'Entidade', 'Entidade ID', 'Nome Entidade', 'CNPJ', 'Valores Antigos', 'Valores Novos', 'Alterações', 'IP', 'Data'], ';');

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['user_id'],
                $log['user_name'] ?? '',
                $log['user_email'] ?? '',
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                $log['company_name'] ?? '',
                $log['company_cnpj'] ?? '',
                $log['old_values'],
                $log['new_values'],
                $log['changes'],
                $log['ip_address'],
                $log['created_at'],
            ], ';');
        }

        fclose($output);
        exit;
    }
}
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
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $action = $_GET['action'] ?? '';
        $entityType = $_GET['entity'] ?? '';
        $userId = !empty($_GET['user']) ? (int) $_GET['user'] : null;
        $startDate = $_GET['start'] ?? '';
        $endDate = $_GET['end'] ?? '';

        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $allLogs = AuditLogService::getRecentLogs(
            $perPage,
            $action ?: null,
            $userId,
            $entityType ?: null,
            $startDate ?: null,
            $endDate ?: null
        );

        $logs = array_slice($allLogs, $offset, $perPage);

        $total = AuditLogService::countLogs($action ?: null, $userId, $entityType ?: null);
        $totalPages = max(1, (int) ceil($total / $perPage));

        $actions = AuditLogService::getDistinctActions();
        $entityTypes = AuditLogService::getDistinctEntityTypes();

        $params = $_GET;
        unset($params['page']);

        View::render('admin/audit/index', [
            'title' => 'Auditoria',
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'action' => $action,
            'entityType' => $entityType,
            'userId' => $userId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'actions' => $actions,
            'entityTypes' => $entityTypes,
            'queryParams' => $params,
            'metaRobots' => 'noindex,nofollow',
        ]);
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
        $startDate = $_GET['start'] ?? '';
        $endDate = $_GET['end'] ?? '';

        $logs = AuditLogService::getRecentLogs(
            10000,
            $action ?: null,
            null,
            $entityType ?: null,
            $startDate ?: null,
            $endDate ?: null
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['ID', 'Usuário', 'Ação', 'Entidade', 'Entidade ID', 'Valores Antigos', 'Valores Novos', 'Alterações', 'IP', 'Data'], ';');

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['user_id'],
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
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
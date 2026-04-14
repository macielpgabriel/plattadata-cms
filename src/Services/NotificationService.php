<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\UserRepository;
use Throwable;

final class NotificationService
{
    private MailService $mail;
    private UserRepository $users;

    public const TYPE_COMPANY_UPDATED = 'company_updated';
    public const TYPE_COMPANY_REMOVED = 'company_removed';
    public const TYPE_COMPANY_FAVORITED = 'company_favorited';
    public const TYPE_RATE_LIMIT_WARNING = 'rate_limit_warning';
    public const TYPE_SECURITY_ALERT = 'security_alert';

    public function __construct()
    {
        $this->mail = new MailService();
        $this->users = new UserRepository();
    }

    public function send(int $userId, string $type, array $data): bool
    {
        if (!$this->isNotificationEnabled($userId, $type)) {
            return false;
        }

        $user = $this->users->findById($userId);
        if (!$user || !(int) $user['is_active']) {
            return false;
        }

        $template = $this->getTemplate($type, $data);
        if ($template === null) {
            return false;
        }

        $subject = $this->interpolate($template['subject'], $data);
        $body = $this->interpolate($template['body'], $data);
        
        $baseUrl = rtrim((string) config('app.url', 'https://plattadata.com'), '/');
        $body = str_replace('{base_url}', $baseUrl, $body);

        $sent = $this->mail->send($user['email'], $subject, $body);

        $this->logNotification($userId, $type, $data, $sent);

        return $sent;
    }

    public function sendToRole(string $role, string $type, array $data): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM users WHERE role = :role AND is_active = 1"
        );
        $stmt->execute(['role' => $role]);
        $users = $stmt->fetchAll();

        $count = 0;
        foreach ($users as $user) {
            if ($this->send((int) $user['id'], $type, $data)) {
                $count++;
            }
        }

        return $count;
    }

    public function sendToAdmins(string $type, array $data): int
    {
        return $this->sendToRole('admin', $type, $data);
    }

    public function notifyCompanyUpdated(int $companyId, string $companyName, string $cnpj): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT user_id FROM user_favorites WHERE company_id = :company_id"
        );
        $stmt->execute(['company_id' => $companyId]);
        $favorites = $stmt->fetchAll();

        $data = [
            'company_id' => $companyId,
            'company_name' => $companyName,
            'cnpj' => $cnpj,
            'updated_at' => date('d/m/Y H:i'),
        ];

        $count = 0;
        foreach ($favorites as $favorite) {
            if ($this->send((int) $favorite['user_id'], self::TYPE_COMPANY_UPDATED, $data)) {
                $count++;
            }
        }

        return $count > 0;
    }

    public function notifyCompanyRemoved(int $companyId, string $cnpj, string $reason): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT user_id FROM user_favorites WHERE company_id = :company_id"
        );
        $stmt->execute(['company_id' => $companyId]);
        $favorites = $stmt->fetchAll();

        $data = [
            'company_id' => $companyId,
            'cnpj' => $cnpj,
            'reason' => $reason,
            'removed_at' => date('d/m/Y H:i'),
        ];

        $count = 0;
        foreach ($favorites as $favorite) {
            if ($this->send((int) $favorite['user_id'], self::TYPE_COMPANY_REMOVED, $data)) {
                $count++;
            }
        }

        return $count > 0;
    }

    public function notifyRateLimitWarning(int $userId, string $action, int $remainingRequests, int $resetInMinutes): bool
    {
        return $this->send($userId, self::TYPE_RATE_LIMIT_WARNING, [
            'action' => $action,
            'remaining_requests' => $remainingRequests,
            'reset_in_minutes' => $resetInMinutes,
        ]);
    }

    public function notifySecurityAlert(int $userId, string $alertType, array $details = []): bool
    {
        $data = array_merge([
            'alert_type' => $alertType,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'occurred_at' => date('d/m/Y H:i:s'),
        ], $details);

        return $this->send($userId, self::TYPE_SECURITY_ALERT, $data);
    }

    private function isNotificationEnabled(int $userId, string $type): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT notifications_enabled, notification_preferences FROM users WHERE id = :id"
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !(int) ($user['notifications_enabled'] ?? 1)) {
            return false;
        }

        $preferences = json_decode($user['notification_preferences'] ?? '{}', true);
        return (bool) ($preferences[$type] ?? true);
    }

    private function getTemplate(string $type, array $data): ?array
    {
        $templates = [
            self::TYPE_COMPANY_UPDATED => [
                'subject' => 'Empresa atualizada: {company_name}',
                'body' => $this->getCompanyUpdatedTemplate(),
            ],
            self::TYPE_COMPANY_REMOVED => [
                'subject' => 'Empresa removida dos favoritos',
                'body' => $this->getCompanyRemovedTemplate(),
            ],
            self::TYPE_RATE_LIMIT_WARNING => [
                'subject' => 'Aviso: Limite de requisições接近',
                'body' => $this->getRateLimitWarningTemplate(),
            ],
            self::TYPE_SECURITY_ALERT => [
                'subject' => 'Alerta de segurança: {alert_type}',
                'body' => $this->getSecurityAlertTemplate(),
            ],
        ];

        return $templates[$type] ?? null;
    }

    private function getCompanyUpdatedTemplate(): string
    {
        return <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #d1fae5; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #059669;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Empresa Atualizada</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Uma das suas empresas favoritas foi atualizada.</p>
</div>
<div style="background: #f9fafb; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left;">
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">CNPJ:</strong> <span style="font-family: monospace; background: #e5e7eb; padding: 2px 8px; border-radius: 4px;">{cnpj}</span></p>
    <p style="margin: 0;"><strong style="color: #374151;">Atualizado em:</strong> {updated_at}</p>
</div>
<p style="color: #6b7280; text-align: center; margin: 20px 0 0;">Os dados da empresa foram atualizados em nossa base de dados.</p>
<div style="text-align: center; margin: 25px 0;">
    <a href="{base_url}/empresa/{company_id}" style="display: inline-block; background: #0f766e; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 500;">Ver Empresa</a>
</div>
<p style="color: #9ca3af; font-size: 12px; text-align: center; margin: 30px 0 0;">Você está recebendo este e-mail porque favoritou esta empresa.</p>
HTML;
    }

    private function getCompanyRemovedTemplate(): string
    {
        return <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #fee2e2; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Empresa Removida</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Uma empresa dos seus favoritos foi removida do sistema.</p>
</div>
<div style="background: #fef2f2; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left; border-left: 4px solid #dc2626;">
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">CNPJ:</strong> <span style="font-family: monospace; background: #fee2e2; padding: 2px 8px; border-radius: 4px;">{cnpj}</span></p>
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">Motivo:</strong> {reason}</p>
    <p style="margin: 0;"><strong style="color: #374151;">Removida em:</strong> {removed_at}</p>
</div>
<p style="color: #6b7280; text-align: center; margin: 20px 0 0;">A empresa foi removida do sistema e não está mais disponível para consulta.</p>
<p style="color: #9ca3af; font-size: 12px; text-align: center; margin: 30px 0 0;">Você está recebendo este e-mail porque favoritou esta empresa.</p>
HTML;
    }

    private function getRateLimitWarningTemplate(): string
    {
        return <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #fef3c7; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Atenção: Limite de Requisições</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Você está próximo do limite de requisições permitidas.</p>
</div>
<div style="background: #fffbeb; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left; border-left: 4px solid #f59e0b;">
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">Ação:</strong> {action}</p>
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">Requisições restantes:</strong> <span style="color: #dc2626; font-weight: bold;">{remaining_requests}</span></p>
    <p style="margin: 0;"><strong style="color: #374151;">Reinício em:</strong> {reset_in_minutes} minutos</p>
</div>
<p style="color: #6b7280; text-align: center; margin: 20px 0 0;">Aguarde o reinício do período para continuar fazendo requisições.</p>
HTML;
    }

    private function getSecurityAlertTemplate(): string
    {
        return <<<'HTML'
<div style="text-align: center;">
    <div style="width: 60px; height: 60px; background: #fee2e2; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
        <svg style="width: 30px; height: 30px; color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
    </div>
    <h2 style="color: #111827; margin: 0 0 15px; font-size: 20px;">Alerta de Segurança</h2>
    <p style="color: #6b7280; margin: 0 0 20px;">Detectamos uma atividade incomum na sua conta.</p>
</div>
<div style="background: #fef2f2; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left; border-left: 4px solid #dc2626;">
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">Tipo:</strong> {alert_type}</p>
    <p style="margin: 0 0 10px;"><strong style="color: #374151;">Endereço IP:</strong> {ip_address}</p>
    <p style="margin: 0;"><strong style="color: #374151;">Data:</strong> {occurred_at}</p>
</div>
<div style="margin: 25px 0; padding: 15px; background: #f3f4f6; border-radius: 8px;">
    <p style="margin: 0 0 10px; color: #374151;"><strong>Se não foi você quem realizou esta ação, recomendamos:</strong></p>
    <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
        <li>Alterar sua senha imediatamente</li>
        <li>Verificar dispositivos conectados à sua conta</li>
        <li>Entrar em contato com o suporte</li>
    </ul>
</div>
<p style="color: #9ca3af; font-size: 12px; text-align: center; margin: 30px 0 0;">Este é um e-mail automático de segurança.</p>
HTML;
    }

    private function interpolate(string $template, array $data): string
    {
        $replacements = [];
        foreach ($data as $key => $value) {
            $replacements['{' . $key . '}'] = e((string) $value);
        }

        return strtr($template, $replacements);
    }

    private function logNotification(int $userId, string $type, array $data, bool $sent): void
    {
        try {
            $stmt = Database::connection()->prepare(
                "INSERT INTO notification_logs (user_id, type, data_json, sent, created_at) 
                 VALUES (:user_id, :type, :data, :sent, NOW())"
            );
            $stmt->execute([
                'user_id' => $userId,
                'type' => $type,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'sent' => $sent ? 1 : 0,
            ]);
        } catch (Throwable) {
        }
    }
}

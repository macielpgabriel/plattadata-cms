<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

final class CompanyChangeMonitorService
{
    private const TRACKED_FIELDS = [
        'status', 'legal_name', 'trade_name', 'city', 'state',
        'street', 'address_number', 'district', 'postal_code',
        'phone', 'email', 'website', 'capital_social',
        'cnae_main_code', 'cnae_main_desc'
    ];

    public static function checkAndRecordChanges(int $companyId, array $oldData, array $newData): void
    {
        foreach (self::TRACKED_FIELDS as $field) {
            $oldValue = $oldData[$field] ?? null;
            $newValue = $newData[$field] ?? null;

            if ($oldValue !== $newValue) {
                self::recordChange($companyId, $field, $oldValue, $newValue);
            }
        }
    }

    private static function recordChange(int $companyId, string $field, ?string $oldValue, ?string $newValue): void
    {
        $changeType = self::getChangeType($field);

        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                INSERT INTO company_changes (company_id, change_type, field_name, old_value, new_value)
                VALUES (:company_id, :change_type, :field_name, :old_value, :new_value)
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'change_type' => $changeType,
                'field_name' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]);
            
            self::notifySubscribers($companyId, $field, $oldValue, $newValue);
        } catch (\Exception $e) {
            Logger::error('Erro ao registrar mudança: ' . $e->getMessage());
        }
    }
    
    private static function notifySubscribers(int $companyId, string $field, ?string $oldValue, ?string $newValue): void
    {
        try {
            $db = Database::connection();
            
            $companyStmt = $db->prepare("SELECT cnpj, legal_name FROM companies WHERE id = :id");
            $companyStmt->execute(['id' => $companyId]);
            $company = $companyStmt->fetch();
            
            if (!$company) return;
            
            $subsStmt = $db->prepare("
                SELECT user_id, notify_email, notify_whatsapp, whatsapp_phone 
                FROM company_change_subscriptions 
                WHERE company_cnpj = :cnpj AND (notify_email = 1 OR notify_whatsapp = 1)
            ");
            $subsStmt->execute(['cnpj' => $company['cnpj']]);
            $subscribers = $subsStmt->fetchAll();
            
            if (empty($subscribers)) return;
            
            $changeLabel = match($field) {
                'status' => 'situação cadastral',
                'legal_name' => 'razão social',
                'trade_name' => 'nome fantasia',
                'city', 'state' => 'localização',
                'street', 'address_number', 'district', 'postal_code' => 'endereço',
                'phone' => 'telefone',
                'email' => 'e-mail',
                'website' => 'website',
                'capital_social' => 'capital social',
                'cnae_main_code' => 'CNAE',
                default => $field,
            };
            
            $subject = "Mudança detectada: {$company['legal_name']}";
            $body = "Detectamos uma mudança na empresa {$company['legal_name']} (CNPJ: {$company['cnpj']}):\n\n";
            $body .= "Campo: {$changeLabel}\n";
            $body .= "De: " . ($oldValue ?: '(vazio)') . "\n";
            $body .= "Para: " . ($newValue ?: '(vazio)') . "\n";
            $body .= "\nAcesse para ver mais detalhes: " . config('app.url') . '/empresas/' . $company['cnpj'];
            
            foreach ($subscribers as $sub) {
                if ($sub['notify_email']) {
                    $userStmt = $db->prepare("SELECT email FROM users WHERE id = :id");
                    $userStmt->execute(['id' => $sub['user_id']]);
                    $user = $userStmt->fetch();
                    
                    if (!empty($user['email'])) {
                        @mail(
                            $user['email'],
                            $subject,
                            $body,
                            "From: " . config('app.email_from', 'naoreply@plattadata.com')
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::error('Erro ao notificar subscribers: ' . $e->getMessage());
        }
    }

    private static function getChangeType(string $field): string
    {
        return match($field) {
            'status' => 'status',
            'capital_social' => 'capital',
            'cnae_main_code', 'cnae_main_desc' => 'cnae',
            'legal_name', 'trade_name' => 'name',
            'phone', 'email', 'website' => 'contact',
            'street', 'address_number', 'district', 'postal_code', 'city', 'state' => 'address',
            default => 'other',
        };
    }

    public static function subscribe(int $userId, string $cnpj, bool $notifyEmail = true, bool $notifyWhatsapp = false, ?string $whatsappPhone = null): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                INSERT INTO company_change_subscriptions (user_id, company_cnpj, notify_email, notify_whatsapp, whatsapp_phone)
                VALUES (:user_id, :cnpj, :notify_email, :notify_whatsapp, :whatsapp)
                ON DUPLICATE KEY UPDATE 
                    notify_email = :notify_email2,
                    notify_whatsapp = :notify_whatsapp2,
                    whatsapp_phone = :whatsapp2
            ");
            return $stmt->execute([
                'user_id' => $userId,
                'cnpj' => preg_replace('/\D/', '', $cnpj),
                'notify_email' => $notifyEmail ? 1 : 0,
                'notify_email2' => $notifyEmail ? 1 : 0,
                'notify_whatsapp' => $notifyWhatsapp ? 1 : 0,
                'notify_whatsapp2' => $notifyWhatsapp ? 1 : 0,
                'whatsapp' => $whatsappPhone,
                'whatsapp2' => $whatsappPhone,
            ]);
        } catch (\Exception $e) {
            Logger::error('Erro ao criar subscription: ' . $e->getMessage());
            return false;
        }
    }

    public static function unsubscribe(int $userId, string $cnpj): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("DELETE FROM company_change_subscriptions WHERE user_id = :user_id AND company_cnpj = :cnpj");
            return $stmt->execute([
                'user_id' => $userId,
                'cnpj' => preg_replace('/\D/', '', $cnpj),
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getRecentChanges(string $cnpj, int $days = 30): array
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                SELECT * FROM company_changes 
                WHERE company_id = (SELECT id FROM companies WHERE cnpj = :cnpj)
                AND changed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY changed_at DESC
                LIMIT 50
            ");
            $stmt->execute(['cnpj' => preg_replace('/\D/', '', $cnpj), 'days' => $days]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }
}
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
        } catch (\Exception $e) {
            Logger::error('Erro ao registrar mudança: ' . $e->getMessage());
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
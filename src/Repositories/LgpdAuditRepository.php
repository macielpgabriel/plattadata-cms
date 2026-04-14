<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class LgpdAuditRepository
{
    public function logAccess(
        ?int $companyId,
        string $cnpj,
        ?int $userId,
        string $userRole,
        string $action,
        array $classifiedFields,
        string $maskingProfile,
        ?string $ipAddress
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO lgpd_audit_logs (
                company_id, cnpj, user_id, user_role, action_name, accessed_fields_json, masking_profile, ip_address, created_at
             ) VALUES (
                :company_id, :cnpj, :user_id, :user_role, :action_name, :accessed_fields_json, :masking_profile, :ip_address, NOW()
             )'
        );

        $stmt->execute([
            'company_id' => $companyId,
            'cnpj' => $cnpj,
            'user_id' => $userId,
            'user_role' => $this->limit($userRole, 20),
            'action_name' => $this->limit($action, 40),
            'accessed_fields_json' => json_encode($classifiedFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'masking_profile' => $this->limit($maskingProfile, 20),
            'ip_address' => $this->anonymizeIp($ipAddress),
        ]);
    }

    private function limit(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    private function anonymizeIp(?string $ipAddress): ?string
    {
        if (!is_string($ipAddress) || trim($ipAddress) === '') {
            return null;
        }

        $ipAddress = trim($ipAddress);

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ipAddress);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
            }
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ipAddress);
            if ($packed !== false) {
                $masked = substr($packed, 0, 6) . str_repeat("\x00", 10);
                $text = @inet_ntop($masked);
                if (is_string($text) && $text !== '') {
                    return $text . '/48';
                }
            }
        }

        return null;
    }
}

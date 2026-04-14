<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LgpdAuditRepository;

final class LgpdComplianceService
{
    public function resolveProfile(?array $user): string
    {
        if (!$user) {
            return 'public';
        }

        $role = (string) ($user['role'] ?? 'viewer');
        if (in_array($role, ['admin', 'editor', 'viewer'], true)) {
            return $role;
        }

        return 'viewer';
    }

    public function classifyCompanyPayload(array $payload): array
    {
        $fields = [];

        if (!empty($payload['email'])) {
            $fields[] = $this->field('email', 'contato_pessoal', 'medio');
        }
        if (!empty($payload['ddd_telefone_1'])) {
            $fields[] = $this->field('ddd_telefone_1', 'contato_pessoal', 'medio');
        }
        if (!empty($payload['ddd_telefone_2'])) {
            $fields[] = $this->field('ddd_telefone_2', 'contato_pessoal', 'medio');
        }

        $qsa = is_array($payload['qsa'] ?? null) ? $payload['qsa'] : [];
        foreach ($qsa as $index => $partner) {
            if (!is_array($partner)) {
                continue;
            }

            if (!empty($partner['nome_socio']) || !empty($partner['nome'])) {
                $fields[] = $this->field('qsa.' . $index . '.nome_socio', 'identidade_pessoal', 'alto');
            }
            if (!empty($partner['nome_rep_legal'])) {
                $fields[] = $this->field('qsa.' . $index . '.nome_rep_legal', 'identidade_pessoal', 'alto');
            }
            if (!empty($partner['cpf_cnpj_socio'])) {
                $fields[] = $this->field('qsa.' . $index . '.cpf_cnpj_socio', 'identificador_pessoal', 'alto');
            }
            if (!empty($partner['cpf_representante_legal'])) {
                $fields[] = $this->field('qsa.' . $index . '.cpf_representante_legal', 'identificador_pessoal', 'alto');
            }
            if (!empty($partner['faixa_etaria'])) {
                $fields[] = $this->field('qsa.' . $index . '.faixa_etaria', 'perfil_pessoal', 'baixo');
            }
        }

        $categoryCount = [];
        foreach ($fields as $field) {
            $category = (string) ($field['classificacao'] ?? 'outros');
            $categoryCount[$category] = ($categoryCount[$category] ?? 0) + 1;
        }

        return [
            'contains_personal_data' => $fields !== [],
            'fields' => $fields,
            'category_count' => $categoryCount,
            'total_classified' => count($fields),
        ];
    }

    public function maskCompanyPayload(array $payload, string $profile): array
    {
        if ($profile === 'admin') {
            return $payload;
        }

        $masked = $payload;

        if (isset($masked['email'])) {
            $masked['email'] = $this->maskEmail((string) $masked['email'], $profile);
        }
        if (isset($masked['ddd_telefone_1'])) {
            $masked['ddd_telefone_1'] = $this->maskPhone((string) $masked['ddd_telefone_1'], $profile);
        }
        if (isset($masked['ddd_telefone_2'])) {
            $masked['ddd_telefone_2'] = $this->maskPhone((string) $masked['ddd_telefone_2'], $profile);
        }

        $qsa = is_array($masked['qsa'] ?? null) ? $masked['qsa'] : [];
        foreach ($qsa as $index => $partner) {
            if (!is_array($partner)) {
                continue;
            }

            if (isset($partner['nome_socio'])) {
                $qsa[$index]['nome_socio'] = $this->maskName((string) $partner['nome_socio'], $profile);
            }
            if (isset($partner['nome'])) {
                $qsa[$index]['nome'] = $this->maskName((string) $partner['nome'], $profile);
            }
            if (isset($partner['nome_rep_legal'])) {
                $qsa[$index]['nome_rep_legal'] = $this->maskName((string) $partner['nome_rep_legal'], $profile);
            }
            if (isset($partner['cpf_cnpj_socio'])) {
                $qsa[$index]['cpf_cnpj_socio'] = $this->maskIdentifier((string) $partner['cpf_cnpj_socio'], $profile);
            }
            if (isset($partner['cpf_representante_legal'])) {
                $qsa[$index]['cpf_representante_legal'] = $this->maskIdentifier((string) $partner['cpf_representante_legal'], $profile);
            }
        }
        $masked['qsa'] = $qsa;

        return $masked;
    }

    public function auditAccess(
        ?int $companyId,
        string $cnpj,
        ?array $user,
        string $action,
        array $classification,
        string $profile,
        ?string $ipAddress
    ): void {
        if (!(bool) config('app.lgpd.audit.enabled', true)) {
            return;
        }

        try {
            (new LgpdAuditRepository())->logAccess(
                $companyId,
                $cnpj,
                isset($user['id']) ? (int) $user['id'] : null,
                $this->resolveProfile($user),
                $action,
                $classification['fields'] ?? [],
                $profile,
                $ipAddress
            );
        } catch (\Throwable) {
            // Auditoria LGPD nao pode quebrar fluxo principal.
        }
    }

    private function field(string $name, string $classification, string $risk): array
    {
        return [
            'campo' => $name,
            'classificacao' => $classification,
            'risco' => $risk,
        ];
    }

    private function maskName(string $name, string $profile): string
    {
        $name = trim($name);
        if ($name === '') {
            return $name;
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        if ($parts === []) {
            return $this->maskWithAsterisks($name, $profile === 'editor' ? 2 : 1);
        }

        if ($profile === 'editor') {
            return $this->maskWithAsterisks($parts[0], 2) . (count($parts) > 1 ? ' ' . $this->maskWithAsterisks((string) end($parts), 1) : '');
        }

        return $this->maskWithAsterisks($parts[0], 1) . (count($parts) > 1 ? ' ' . $this->maskWithAsterisks((string) end($parts), 1) : '');
    }

    private function maskEmail(string $email, string $profile): string
    {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = $profile === 'editor' ? 2 : 1;
        return $this->maskWithAsterisks($local, $visible) . '@' . $domain;
    }

    private function maskPhone(string $phone, string $profile): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return $phone;
        }

        $visible = $profile === 'editor' ? 4 : 2;
        $maskedDigits = str_repeat('*', max(0, strlen($digits) - $visible)) . substr($digits, -$visible);
        return $maskedDigits;
    }

    private function maskIdentifier(string $value, string $profile): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return $this->maskWithAsterisks($value, $profile === 'editor' ? 3 : 2);
        }

        $visible = $profile === 'editor' ? 4 : 2;
        return str_repeat('*', max(0, strlen($digits) - $visible)) . substr($digits, -$visible);
    }

    private function maskWithAsterisks(string $value, int $visible): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $len = mb_strlen($value);
            if ($len <= $visible) {
                return str_repeat('*', $len);
            }
            return mb_substr($value, 0, $visible) . str_repeat('*', $len - $visible);
        }

        $len = strlen($value);
        if ($len <= $visible) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, $visible) . str_repeat('*', $len - $visible);
    }
}

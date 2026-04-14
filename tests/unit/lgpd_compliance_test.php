<?php

declare(strict_types=1);

use App\Services\LgpdComplianceService;

function lgpd_compliance_test_cases(): array
{
    $service = new LgpdComplianceService();
    $cases = [];

    $payload = [
        'email' => 'contato@empresa.com.br',
        'ddd_telefone_1' => '11999998888',
        'qsa' => [
            [
                'nome_socio' => 'ANA PAULA DE SOUZA',
                'cpf_cnpj_socio' => '12345678901',
            ],
        ],
    ];

    $classification = $service->classifyCompanyPayload($payload);
    $cases['lgpd_classifies_personal_data'] = !empty($classification['contains_personal_data']) && ((int) ($classification['total_classified'] ?? 0) >= 3);

    $maskedPublic = $service->maskCompanyPayload($payload, 'public');
    $cases['lgpd_masks_name_for_public'] = ($maskedPublic['qsa'][0]['nome_socio'] ?? '') !== 'ANA PAULA DE SOUZA';
    $cases['lgpd_masks_identifier_for_public'] = ($maskedPublic['qsa'][0]['cpf_cnpj_socio'] ?? '') !== '12345678901';

    $maskedAdmin = $service->maskCompanyPayload($payload, 'admin');
    $cases['lgpd_keeps_full_data_for_admin'] = ($maskedAdmin['qsa'][0]['nome_socio'] ?? '') === 'ANA PAULA DE SOUZA';

    return normalize_cases($cases);
}

<?php

declare(strict_types=1);

use App\Services\PasswordPolicyService;

function password_policy_test_cases(): array
{
    $service = new PasswordPolicyService();
    $cases = [];

    $strong = $service->validate('Forte@12345');
    $cases['password_accepts_strong'] = $strong === null;

    $weakShort = $service->validate('Aa1@');
    $cases['password_rejects_short'] = is_string($weakShort) && strpos($weakShort, 'pelo menos') !== false;

    $weakNoUpper = $service->validate('fraca@12345');
    $cases['password_rejects_without_uppercase'] = is_string($weakNoUpper) && strpos($weakNoUpper, 'maiuscula') !== false;

    return normalize_cases($cases);
}

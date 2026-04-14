<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/unit/_helpers.php';

require __DIR__ . '/unit/password_policy_test.php';
require __DIR__ . '/unit/lgpd_compliance_test.php';
require __DIR__ . '/unit/observability_test.php';
require __DIR__ . '/unit/disposable_email_test.php';

$suite = array_merge(
    password_policy_test_cases(),
    lgpd_compliance_test_cases(),
    observability_test_cases(),
    disposable_email_test_cases()
);

$total = 0;
$failed = 0;

foreach ($suite as $name => $result) {
    $total++;
    if ($result !== true) {
        $failed++;
        echo "[FAIL] {$name}: {$result}\n";
        continue;
    }
    echo "[OK] {$name}\n";
}

echo "\nTotal: {$total} | Falhas: {$failed}\n";
exit($failed > 0 ? 1 : 0);

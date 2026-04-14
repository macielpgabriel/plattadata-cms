<?php

declare(strict_types=1);

function normalize_cases(array $cases): array
{
    $normalized = [];
    foreach ($cases as $name => $value) {
        if ($value === true) {
            $normalized[$name] = true;
            continue;
        }

        if ($value === false) {
            $normalized[$name] = 'assertion failed';
            continue;
        }

        $normalized[$name] = is_string($value) ? $value : 'assertion failed';
    }

    return $normalized;
}

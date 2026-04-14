<?php

declare(strict_types=1);

return [
    'admin' => [
        'label' => 'Administrador',
        'abilities' => ['users.manage', 'companies.search', 'companies.view', 'removals.manage'],
    ],
    'moderator' => [
        'label' => 'Moderador de Remoções',
        'abilities' => ['companies.search', 'companies.view', 'removals.manage'],
    ],
    'editor' => [
        'label' => 'Editor',
        'abilities' => ['companies.search', 'companies.view'],
    ],
    'viewer' => [
        'label' => 'Leitor',
        'abilities' => ['companies.view'],
    ],
];

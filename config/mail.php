<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
    'from_name' => env('MAIL_FROM_NAME', 'CMS'),
    'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@localhost'),
    'admin_email' => env('ADMIN_EMAIL', ''),
];

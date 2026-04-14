<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

final class StaffMiddleware
{
    public function handle(): void
    {
        if (!Auth::can(['admin', 'moderator'])) {
            http_response_code(403);
            echo 'Acesso negado. Esta área é restrita a administradores e moderadores.';
            exit;
        }
    }
}

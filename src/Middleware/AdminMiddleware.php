<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

final class AdminMiddleware
{
    public function handle(): void
    {
        if (!Auth::can(['admin'])) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
    }
}

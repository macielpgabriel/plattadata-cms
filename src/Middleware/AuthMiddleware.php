<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Services\SetupService;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (!(new SetupService())->isDatabaseReady()) {
            redirect('/install');
        }

        if (!Auth::check()) {
            redirect('/login');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Response;

class CsrfMiddleware
{
    /**
     * Handle the request.
     * 
     * Validates CSRF token for state-changing methods.
     * Exceptions can be added for specific routes if needed (e.g., API webhooks).
     */
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Only check state-changing methods
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            // Exclude API v1 routes if they use API keys instead of sessions
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (str_contains($uri, '/api/v1/')) {
                return;
            }

            // Also exclude webhooks
            if (str_contains($uri, '/webhook')) {
                return;
            }

            $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

            if (!Csrf::validate($token)) {
                Response::json([
                    'error' => 'CSRF token mismatch',
                    'message' => 'A validação de segurança falhou. Por favor, recarregue a página e tente novamente.'
                ], 403);
            }
        }
    }
}

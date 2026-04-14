<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        // Garantir variáveis básicas para evitar warnings em views
        $defaults = [
            'title' => '',
            'metaTitle' => null,
            'metaDescription' => null,
            'metaRobots' => null,
            'structuredData' => null,
            'usdRate' => null,
            'capitalUsd' => null,
            'marketNews' => [],
            'stockQuote' => null,
            'weather' => null,
            'company' => [],
            'qsa' => [],
            'mainCnae' => [],
            'secondaryCnaes' => [],
            'enrichment' => [],
            'rawData' => [],
            'cnpj' => '',
        ];
        
        $data = array_merge($defaults, $data);
        
        // Log view render attempt for debugging
        error_log(sprintf(
            "[VIEW_RENDER] template=%s, data_keys=%s, has_marketNews=%s, marketNews_type=%s",
            $template,
            implode(',', array_keys($data)),
            isset($data['marketNews']) ? 'yes' : 'no',
            isset($data['marketNews']) ? gettype($data['marketNews']) : 'N/A'
        ));
        
        extract($data, EXTR_SKIP);
        
        $viewPath = base_path('src/Views/' . $template . '.php');

        if (!is_file($viewPath)) {
            http_response_code(500);
            Logger::error("View nao encontrada: " . $viewPath);
            echo "<h1>HTTP ERROR 500</h1>";
            echo "<p>Desculpe, uma peça do sistema está faltando (View não encontrada).</p>";
            return;
        }

        $layoutPath = base_path('src/Views/layouts/app.php');
        if (!is_file($layoutPath)) {
            // Fallback se o layout sumir
            require $viewPath;
            return;
        }

        require $layoutPath;
    }
}

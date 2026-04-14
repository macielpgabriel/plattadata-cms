<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;
use PDOException;

abstract class Controller
{
    protected function tryRender(callable $callback, string $template, array $data = []): void
    {
        try {
            $result = $callback();
            $data = array_merge($data, is_array($result) ? $result : []);
            View::render($template, $data);
        } catch (PDOException $e) {
            $this->handleDatabaseError($e, $template, $data);
        } catch (Throwable $e) {
            $this->handleError($e, $template, $data);
        }
    }
    
    protected function handleDatabaseError(PDOException $e, string $template, array $data): void
    {
        $data['error'] = 'Erro ao acessar o banco de dados. Tente novamente mais tarde.';
        Logger::error('Database Error: ' . $e->getMessage());
        
        $debug = (bool) config('app.debug', false);
        if ($debug) {
            $data['error'] .= ' ' . $e->getMessage();
        }
        
        http_response_code(503);
        View::render($template, $data);
    }
    
    protected function handleError(Throwable $e, string $template, array $data): void
    {
        $data['error'] = 'Ocorreu um erro inesperado. Tente novamente mais tarde.';
        Logger::error('Controller Error: ' . $e->getMessage());
        
        $debug = (bool) config('app.debug', false);
        if ($debug) {
            $data['error'] .= ' ' . $e->getMessage();
        }
        
        http_response_code(500);
        View::render($template, $data);
    }
    
    protected function safeGet(array &$data, string $key, $default = null)
    {
        return $data[$key] ?? $default;
    }
}
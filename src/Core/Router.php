<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $globalMiddlewares = [];

    public function addGlobalMiddleware(string $middlewareClass): void
    {
        $this->globalMiddlewares[] = $middlewareClass;
    }

    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        error_log("Router::get($path)");
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Adiciona uma rota GET com padrão regex customizado
     * Útil para rotas com parâmetros que contêm caracteres especiais como barras
     */
    public function getWithPattern(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->addRouteWithPattern('GET', $pattern, $handler, $middlewares);
    }

    private function addRouteWithPattern(string $method, string $pattern, callable|array $handler, array $middlewares): void
    {
        $regexPattern = '#^' . $pattern . '$#';

        $this->routes[$method][] = [
            'path' => $pattern,
            'pattern' => $regexPattern,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares): void
    {
        $pattern = preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        
        error_log("addRoute: $method $path -> $pattern");

        $this->routes[$method][] = [
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        
        // Normalizar caminho: remover trailing slash exceto para root
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        
        $routes = $this->routes[$method] ?? [];
        $startTime = microtime(true);

        try {
            error_log(sprintf("[DISPATCH] %s %s - METODO: %s, rotas: %d", $method, $path, $method, count($routes)));
            
            // Debug: listar rotas disponíveis
            if (str_starts_with($path, '/verificar')) {
                foreach ($routes as $i => $route) {
                    error_log(sprintf("[ROTA %d] pattern: %s", $i, $route['pattern']));
                }
            }

            foreach ($routes as $route) {
                if (!preg_match($route['pattern'], $path, $matches)) {
                    continue;
                }

                $params = array_filter($matches, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
                
                // Extract single param values (not arrays)
                foreach ($params as $key => $value) {
                    if (is_array($value)) {
                        $params[$key] = $value[0] ?? '';
                    }
                }

                // Run global middlewares
                foreach ($this->globalMiddlewares as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $middleware->handle();
                }

                foreach ($route['middlewares'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $middleware->handle();
                }

                if (is_array($route['handler'])) {
                    [$class, $action] = $route['handler'];
                    $controller = new $class();
                    
                    if (empty($params)) {
                        $controller->{$action}();
                    } else {
                        // Pass only string values, not arrays
                        $scalarParams = [];
                        foreach ($params as $k => $v) {
                            $scalarParams[$k] = is_array($v) ? ($v[0] ?? '') : $v;
                        }
                        $controller->{$action}($scalarParams);
                    }
                    return;
                }

                if (empty($params)) {
                    call_user_func($route['handler']);
                } else {
                    call_user_func($route['handler'], $params);
                }
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                error_log(sprintf("[OK] %s %s - %dms", $method, $path, $duration));
                return;
            }

            error_log(sprintf("[404] %s %s - Rota nao encontrada", $method, $path));
            Response::notFound();
        } catch (\Throwable $e) {
            // Se for uma requisição API ou AJAX, retorna JSON em vez de página HTML
            if (is_api_request()) {
                Logger::error('Erro em rota API: ' . $e->getMessage(), [
                    'path' => $path,
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);
                
                Response::json([
                    'error' => 'Erro interno no servidor',
                    'message' => $e->getMessage()
                ], 500);
            }

            // Caso contrário, relança a exceção para o handler global
            error_log(sprintf("[ERROR] %s %s - %s", $method, $path, $e->getMessage()));
            throw $e;
        }
    }
}


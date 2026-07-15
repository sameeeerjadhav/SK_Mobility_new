<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = rtrim(env('APP_BASE', ''), '/');
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            if (!preg_match($pattern, $uri, $matches)) {
                continue;
            }
            array_shift($matches);

            foreach ($route['middleware'] as $mw) {
                if (is_callable($mw)) {
                    $mw();
                } elseif (is_string($mw) && function_exists($mw)) {
                    $mw();
                }
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            call_user_func_array([$controller, $action], $matches);
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Not Found'], 'guest');
    }
}

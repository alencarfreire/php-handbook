<?php

namespace App\Presentation;

final class Router
{
    /** @var list<array{method: string, regex: string, names: list<string>, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        // /tasks/{id} vira regex. Sem framework.
        $names = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)\}/', static function (array $m) use (&$names): string {
            $names[] = $m[1];
            return '([^/]+)';
        }, $path);

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'names'   => $names,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $allowed = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $allowed = true;
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($route['names'] as $i => $name) {
                $params[$name] = $matches[$i + 1];
            }

            ($route['handler'])($params);
            return;
        }

        // Path existe, verbo não: 405. Path não existe: 404.
        if ($allowed) {
            Json::error(405, 'Método não permitido.');
        }

        Json::error(404, 'Rota não encontrada.');
    }
}

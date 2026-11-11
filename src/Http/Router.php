<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

/**
 * Minimální router: vzory jako "/api/parcels/{id}" – parametry jsou vždy jen číslice,
 * takže controllery dostávají už validovaný vstup.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: Closure(array<string, int>, Request): Response}> */
    private array $routes = [];

    /** @param Closure(array<string, int>, Request): Response $handler */
    public function get(string $pattern, Closure $handler): self
    {
        $regex = '#^' . preg_replace('/\\\\\{(\w+)\\\\\}/', '(?<$1>\d{1,18})', preg_quote($pattern, '#')) . '$#';
        $this->routes[] = ['method' => 'GET', 'regex' => $regex, 'handler' => $handler];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method && !($request->method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }
            $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

            return ($route['handler'])($params, $request);
        }

        throw $pathMatched
            ? new HttpException(405, 'Metoda není povolena')
            : new HttpException(404, 'Nenalezeno');
    }
}

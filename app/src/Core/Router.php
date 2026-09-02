<?php

declare(strict_types=1);

namespace Bbs\Core;

/**
 * Tiny regex router. Patterns use {name} placeholders (matched greedily against
 * a single path segment) or {name:.*} for the rest of the path.
 */
final class Router
{
    /** @var list<array{method:string,regex:string,params:list<string>,handler:callable|array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $params = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern
        );
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    public function get(string $p, callable|array $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, callable|array $h): void   { $this->add('POST', $p, $h); }
    public function put(string $p, callable|array $h): void    { $this->add('PUT', $p, $h); }
    public function delete(string $p, callable|array $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(Request $req): Response
    {
        // HEAD is served by the matching GET handler (Apache/PHP drops the body);
        // OPTIONS gets a bare CORS-friendly 204. Social crawlers rely on both.
        $method = $req->method === 'HEAD' ? 'GET' : $req->method;
        if ($req->method === 'OPTIONS') {
            return Response::raw('', 'text/plain', 204)
                ->withHeader('Allow', 'GET, HEAD, POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS')
                ->withHeader('Access-Control-Max-Age', '86400');
        }

        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $req->path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[$route['method']] = true;
                continue;
            }
            $args = [];
            foreach ($route['params'] as $i => $name) {
                $args[$name] = rawurldecode($m[$i + 1]);
            }
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $handler = [is_string($class) ? new $class() : $class, $method];
            }
            $result = $handler($req, $args);
            return $result instanceof Response ? $result : Response::json($result);
        }

        if ($allowed) {
            return Response::error('METHOD NOT ALLOWED', 405)
                ->withHeader('Allow', implode(', ', array_keys($allowed)));
        }
        return Response::error('NO CARRIER - route not found: ' . $req->path, 404);
    }
}

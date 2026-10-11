<?php
declare(strict_types=1);

namespace Wiki;

class Router
{
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $p, callable $h): void  { $this->routes['GET'][]  = [$p, $h]; }
    public function post(string $p, callable $h): void { $this->routes['POST'][] = [$p, $h]; }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        // HEAD requests are served by the GET handler
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // 去掉子目录前缀
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($base && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . ltrim($path, '/');

        // 按注册顺序匹配（越具体越靠前）
        foreach ($this->routes[$method] ?? [] as [$pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler($params);
                return;
            }
        }
        http_response_code(404);
        echo '404 Not Found';
    }
}
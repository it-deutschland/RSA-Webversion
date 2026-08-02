<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Simple HTTP router.
 */
class Router
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $routes = [];

    /**
     * @var array<string, int>
     */
    private array $namedRoutes = [];

    /**
     * @var list<string>
     */
    private array $groupPrefixes = [];

    /**
     * @var list<list<string>>
     */
    private array $groupMiddlewares = [];

    /**
     * @var list<string>
     */
    private array $pendingMiddlewares = [];

    public function get(string $uri, string|callable|array $action): RouteDefinition
    {
        return $this->register('GET', $uri, $action);
    }

    public function post(string $uri, string|callable|array $action): RouteDefinition
    {
        return $this->register('POST', $uri, $action);
    }

    public function put(string $uri, string|callable|array $action): RouteDefinition
    {
        return $this->register('PUT', $uri, $action);
    }

    public function delete(string $uri, string|callable|array $action): RouteDefinition
    {
        return $this->register('DELETE', $uri, $action);
    }

    public function any(string $uri, string|callable|array $action): RouteDefinition
    {
        return $this->register('ANY', $uri, $action);
    }

    /**
     * Queues middleware for the next route or route group.
     */
    public function middleware(string $name): self
    {
        if ($name !== '') {
            $this->pendingMiddlewares[] = $name;
        }

        return $this;
    }

    /**
     * Groups routes under a shared URI prefix.
     */
    public function group(string $prefix, callable $callback): void
    {
        $this->groupPrefixes[] = $this->normalizePrefix($prefix);
        $this->groupMiddlewares[] = $this->pendingMiddlewares;
        $this->pendingMiddlewares = [];

        try {
            $callback($this);
        } finally {
            array_pop($this->groupPrefixes);
            array_pop($this->groupMiddlewares);
            $this->pendingMiddlewares = [];
        }
    }

    /**
     * Dispatches the current request.
     */
    public function dispatch(): void
    {
        $method = Request::method();
        $uri = Request::uri();

        try {
            foreach ($this->routes as $route) {
                if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                    continue;
                }

                if (!preg_match($route['regex'], $uri, $matches)) {
                    continue;
                }

                $params = [];
                foreach ($route['parameters'] as $parameter) {
                    $params[$parameter] = $matches[$parameter] ?? null;
                }

                foreach ($route['middlewares'] as $middleware) {
                    Middleware::handle($middleware);
                }

                $this->invokeAction($route['action'], $params);

                return;
            }

            $this->renderError(404);
        } catch (Throwable $throwable) {
            Logger::error('Router dispatch failed.', [
                'exception' => $throwable->getMessage(),
                'trace' => APP_DEBUG ? $throwable->getTraceAsString() : null,
                'uri' => $uri,
                'method' => $method,
            ]);

            $this->renderError(500, $throwable);
        }
    }

    /**
     * Generates a URL from a named route.
     *
     * @param array<string, scalar|null> $parameters
     */
    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException(sprintf('Route [%s] is not defined.', $name));
        }

        $uri = $this->routes[$this->namedRoutes[$name]]['uri'];

        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string) $value, $uri);
        }

        return rtrim((string) APP_URL, '/') . $uri;
    }

    /**
     * @param string|callable|array<int, string> $action
     */
    private function register(string $method, string $uri, string|callable|array $action): RouteDefinition
    {
        $normalizedUri = $this->normalizeUri($uri);
        [$regex, $parameters] = $this->compileUriPattern($normalizedUri);

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $normalizedUri,
            'action' => $action,
            'regex' => $regex,
            'parameters' => $parameters,
            'middlewares' => $this->collectMiddlewares(),
            'name' => null,
        ];

        $index = array_key_last($this->routes);
        $this->pendingMiddlewares = [];

        return new RouteDefinition($this, (int) $index);
    }

    /**
     * @return list<string>
     */
    private function collectMiddlewares(): array
    {
        $middlewares = [];

        foreach ($this->groupMiddlewares as $groupMiddleware) {
            foreach ($groupMiddleware as $name) {
                if (!in_array($name, $middlewares, true)) {
                    $middlewares[] = $name;
                }
            }
        }

        foreach ($this->pendingMiddlewares as $name) {
            if (!in_array($name, $middlewares, true)) {
                $middlewares[] = $name;
            }
        }

        return $middlewares;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function compileUriPattern(string $uri): array
    {
        $parameters = [];
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/',
            static function (array $matches) use (&$parameters): string {
                $parameters[] = $matches[1];

                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $uri
        );

        return ['#^' . $pattern . '$#', $parameters];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function invokeAction(string|callable|array $action, array $params): void
    {
        if (is_callable($action)) {
            call_user_func_array($action, $params);

            return;
        }

        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            $instance = is_string($controller) ? new $controller() : $controller;
            $instance->{$method}(...array_values($params));

            return;
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            $class = str_contains($controller, '\\') ? $controller : 'App\\Controllers\\' . $controller;
            $instance = new $class();
            $instance->{$method}(...array_values($params));

            return;
        }

        throw new \RuntimeException('Invalid route action definition.');
    }

    private function renderError(int $status, ?Throwable $throwable = null): void
    {
        Response::setStatus($status);

        $view = 'errors/' . $status;
        $viewPath = VIEWS_PATH . '/' . $view . '.php';

        if (is_file($viewPath)) {
            View::render($view, [
                'status' => $status,
                'exception' => $throwable,
            ], 'none');

            return;
        }

        header('Content-Type: text/html; charset=UTF-8');

        $title = $status === 404 ? 'Page Not Found' : 'Application Error';
        $message = match ($status) {
            404 => 'The requested page could not be found.',
            default => APP_DEBUG && $throwable !== null ? $throwable->getMessage() : 'An internal server error occurred.',
        };

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'
            . View::e($title)
            . '</title></head><body><h1>'
            . View::e($title)
            . '</h1><p>'
            . View::e($message)
            . '</p></body></html>';
    }

    private function normalizePrefix(string $prefix): string
    {
        if ($prefix === '' || $prefix === '/') {
            return '';
        }

        return '/' . trim($prefix, '/');
    }

    private function normalizeUri(string $uri): string
    {
        $groupPrefix = implode('', array_filter($this->groupPrefixes, static fn (string $prefix): bool => $prefix !== ''));
        $fullUri = $groupPrefix . '/' . trim($uri, '/');
        $fullUri = preg_replace('#/+#', '/', $fullUri) ?: '/';

        if ($fullUri === '') {
            return '/';
        }

        $normalized = rtrim($fullUri, '/');

        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * @param non-empty-string $name
     */
    public function setRouteName(int $index, string $name): void
    {
        $this->routes[$index]['name'] = $name;
        $this->namedRoutes[$name] = $index;
    }

    public function addRouteMiddleware(int $index, string $middleware): void
    {
        if (!in_array($middleware, $this->routes[$index]['middlewares'], true)) {
            $this->routes[$index]['middlewares'][] = $middleware;
        }
    }
}

/**
 * Chainable route definition.
 */
class RouteDefinition
{
    public function __construct(
        private readonly Router $router,
        private readonly int $index
    ) {
    }

    public function middleware(string $name): self
    {
        $this->router->addRouteMiddleware($this->index, $name);

        return $this;
    }

    public function name(string $name): self
    {
        $this->router->setRouteName($this->index, $name);

        return $this;
    }
}

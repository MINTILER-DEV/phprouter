<?php

/**
 * PHP Router Class
 * 
 * A flexible router for PHP applications with support for:
 * - GET, POST, PUT, PATCH, DELETE HTTP methods
 * - Dynamic route parameters
 * - Middleware support
 * - Route groups
 * - Custom 404 handling
 * 
 * @package    Router
 * @author     MINTILER-DEV
 * @version    1.0.0
 * @license    MIT
 * @link       https://github.com/MINTILER-DEV/phprouter
 */
class Router
{
    /**
     * @var array[] Registered routes
     */
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => []
    ];

    /**
     * @var array Route groups stack
     */
    private $groups = [];

    /**
     * @var callable 404 handler
     */
    private $notFoundHandler;

    /**
     * Register a GET route
     *
     * @param string $path
     * @param callable|array $handler
     * @return self
     */
    public function get(string $path, $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route
     *
     * @param string $path
     * @param callable|array $handler
     * @return self
     */
    public function post(string $path, $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route
     *
     * @param string $path
     * @param callable|array $handler
     * @return self
     */
    public function put(string $path, $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register a PATCH route
     *
     * @param string $path
     * @param callable|array $handler
     * @return self
     */
    public function patch(string $path, $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Register a DELETE route
     *
     * @param string $path
     * @param callable|array $handler
     * @return self
     */
    public function delete(string $path, $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Register a route for any HTTP method
     *
     * @param string $path
     * @param callable|array $handler
     * @return self
     */
    public function any(string $path, $handler): self
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }

    /**
     * Create a route group
     *
     * @param string $prefix
     * @param callable $callback
     * @return self
     */
    public function group(string $prefix, callable $callback): self
    {
        $this->groups[] = $prefix;
        $callback($this);
        array_pop($this->groups);
        return $this;
    }

    /**
     * Set 404 handler
     *
     * @param callable $handler
     * @return self
     */
    public function setNotFoundHandler(callable $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Add a route to the collection
     *
     * @param string $method
     * @param string $path
     * @param callable|array $handler
     * @return self
     */
    private function addRoute(string $method, string $path, $handler): self
    {
        // Apply group prefix if any
        $path = $this->applyGroupPrefix($path);
        
        // Convert route parameters to regex pattern
        $pattern = $this->compileRoute($path);
        
        $this->routes[$method][$pattern] = [
            'handler' => $handler,
            'original_path' => $path
        ];
        
        return $this;
    }

    /**
     * Apply group prefix to path
     *
     * @param string $path
     * @return string
     */
    private function applyGroupPrefix(string $path): string
    {
        if (!empty($this->groups)) {
            $prefix = implode('', $this->groups);
            $path = $prefix . $path;
        }
        
        // Ensure path starts with a slash
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        return $path;
    }

    /**
     * Compile route path to regex pattern
     *
     * @param string $path
     * @return string
     */
    private function compileRoute(string $path): string
    {
        // Replace {param} with named capture groups
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^\/]+)', $path);
        
        // Add start and end anchors
        return '#^' . $pattern . '$#';
    }

    /**
     * Dispatch the request to the appropriate handler
     *
     * @return mixed
     */
    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Handle PUT/PATCH/DELETE methods with method override
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        if (!isset($this->routes[$method])) {
            return $this->handleNotFound();
        }

        foreach ($this->routes[$method] as $pattern => $route) {
            if (preg_match($pattern, $uri, $matches)) {
                // Filter out numeric keys, keep only named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                return $this->callHandler($route['handler'], $params);
            }
        }

        return $this->handleNotFound();
    }

    /**
     * Call the route handler
     *
     * @param callable|array $handler
     * @param array $params
     * @return mixed
     */
    private function callHandler($handler, array $params = [])
    {
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            
            if (class_exists($class)) {
                $instance = new $class();
                
                if (method_exists($instance, $method)) {
                    return call_user_func_array([$instance, $method], $params);
                }
            }
        }

        throw new RuntimeException('Invalid route handler');
    }

    /**
     * Handle 404 Not Found
     *
     * @return mixed
     */
    private function handleNotFound()
    {
        if ($this->notFoundHandler) {
            return call_user_func($this->notFoundHandler);
        }

        http_response_code(404);
        header('Content-Type: application/json');
        return json_encode(['error' => 'Route not found']);
    }

    /**
     * Get all registered routes (for debugging)
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

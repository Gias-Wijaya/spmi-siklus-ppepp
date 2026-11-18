<?php
declare(strict_types=1);

/** Router sederhana berbasis regex (GET/POST). */
final class Router
{
    /** @var array<int, array{0:string,1:string,2:string}> [method, regex, handler] */
    private array $routes = [];

    private const PUBLIC_ROUTES = [
        'AuthController@showLogin',
        'AuthController@login',
    ];

    public function get(string $path, string $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, string $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, string $handler): void
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [$method, '#^' . $pattern . '$#', $handler];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base   = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = ($uri === '' ? '/' : rtrim($uri, '/')) ?: '/';

        foreach ($this->routes as [$m, $regex, $handler]) {
            if ($m !== $method) {
                continue;
            }
            if (!preg_match($regex, $uri, $matches)) {
                continue;
            }

            // Verifikasi CSRF untuk semua permintaan tulis.
            if ($method === 'POST') {
                csrf_verify();
            }

            // Penjagaan autentikasi.
            if (!in_array($handler, self::PUBLIC_ROUTES, true) && !Auth::check()) {
                flash('warning', 'Silakan masuk terlebih dahulu.');
                redirect('/login');
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = explode('@', $handler);

            if (!class_exists($class) || !method_exists($class, $action)) {
                $this->render404();
            }

            $controller = new $class();
            $controller->{$action}(...array_values($params));
            return;
        }

        $this->render404();
    }

    private function render404(): never
    {
        http_response_code(404);
        $title = '404 — Halaman tidak ditemukan';
        $message = 'Halaman yang Anda tuju tidak ada atau sudah dipindahkan.';
        $home = url('/');
        include BASE_PATH . '/app/views/errors/shell.php';
        exit;
    }
}

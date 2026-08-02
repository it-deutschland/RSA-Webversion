<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Application bootstrapper.
 */
class App
{
    private Router $router;

    /**
     * @throws RuntimeException
     */
    public function __construct()
    {
        $this->ensureConfiguration();
        date_default_timezone_set((string) APP_TIMEZONE);

        Session::start();
        Database::getInstance();

        $this->router = new Router();
        $this->routes();
    }

    /**
     * Registers application routes.
     */
    public function routes(): void
    {
        $router = $this->router;
        $routeFiles = [
            BASE_PATH . '/routes/web.php',
            BASE_PATH . '/routes/api.php',
        ];

        foreach ($routeFiles as $routeFile) {
            if (is_file($routeFile)) {
                require $routeFile;
            }
        }
    }

    /**
     * Runs the router dispatcher.
     */
    public function run(): void
    {
        $this->router->dispatch();
    }

    /**
     * @throws RuntimeException
     */
    private function ensureConfiguration(): void
    {
        $requiredConstants = [
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'APP_URL',
            'APP_KEY',
            'APP_DEBUG',
            'APP_TIMEZONE',
        ];

        foreach ($requiredConstants as $constant) {
            if (!defined($constant)) {
                throw new RuntimeException(sprintf('Missing required configuration constant: %s', $constant));
            }
        }
    }
}

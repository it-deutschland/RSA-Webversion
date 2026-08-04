<?php

/**
 * RSA21-Free – Front Controller
 *
 * Entry point for all web requests.
 * Bootstraps the application and dispatches to the router.
 *
 * @license MIT
 */

declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('SRC_PATH', BASE_PATH . '/src');
define('VIEWS_PATH', SRC_PATH . '/Views');

// Redirect to installer if no config exists.
// Guard: only redirect if we are NOT already on the install path to prevent loops.
if (!file_exists(BASE_PATH . '/config.php')) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    // Avoid looping: if the request is already inside /install, do not redirect again
    if (strpos($requestUri, '/install') === false) {
        header('Location: /install/');
        exit;
    }
    // If somehow index.php is called from within /install context, just stop
    http_response_code(503);
    echo '<h1>Bitte führen Sie zuerst die Installation durch.</h1><p><a href="/install/">Zur Installation</a></p>';
    exit;
}

// Built-in PSR-4 autoloader for the App\ namespace (replaces Composer/vendor).
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = SRC_PATH . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
require_once BASE_PATH . '/config.php';

use App\Core\App;

$app = new App();
$app->run();

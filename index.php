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

// Guard: vendor/autoload.php must exist (composer install must have been run).
// Without it the application cannot start. Show a clear error instead of looping.
if (!file_exists(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(503);
    echo '<h1>Abhängigkeiten fehlen</h1>';
    echo '<p>Die Datei <code>vendor/autoload.php</code> wurde nicht gefunden.</p>';
    echo '<p>Bitte führen Sie <code>composer install</code> im Verzeichnis <code>' . htmlspecialchars(BASE_PATH) . '</code> aus.</p>';
    echo '<p>Falls Sie keinen Zugriff auf Composer haben, laden Sie den <code>vendor</code>-Ordner manuell auf den Server hoch.</p>';
    exit;
}

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/config.php';

use App\Core\App;

$app = new App();
$app->run();

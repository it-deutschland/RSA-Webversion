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

// Redirect to installer if no config exists
if (!file_exists(BASE_PATH . '/config.php')) {
    header('Location: /install/index.php');
    exit;
}

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/config.php';

use App\Core\App;

$app = new App();
$app->run();

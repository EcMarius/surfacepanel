<?php

/**
 * VirPanel - Front Controller
 *
 * This file is the entry point for all web requests
 */

use VirPanel\Core\Application;
use VirPanel\Core\Http\WebRouter;
use VirPanel\Core\Template\TemplateEngine;
use Symfony\Component\HttpFoundation\Request;

// Load autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize application
$app = Application::getInstance(__DIR__ . '/..');

// Start session if not already started
if (!isset($_SESSION)) {
    session_start();
}

// Create request
$request = Request::createFromGlobals();

// Register template engine
$app->getContainer()->set('template', function() use ($app) {
    return new TemplateEngine($app);
});

// Load web routes
$router = new WebRouter();
$routeConfig = require __DIR__ . '/../config/web/routes.php';
$routeConfig($router);

// Handle request
try {
    $response = $router->handle($request);
    $response->send();
} catch (\Throwable $e) {
    // Log error
    logger('Web request error: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Show error page
    if (config('app.debug', false)) {
        echo '<h1>Application Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>500 - Internal Server Error</h1>';
        echo '<p>An error occurred while processing your request.</p>';
    }

    http_response_code(500);
}

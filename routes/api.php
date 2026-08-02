<?php

declare(strict_types=1);

/**
 * API Routes
 * All routes are prefixed with /api/v1/
 *
 * @var App\Core\Router $router
 */

$router->group('/api/v1', function ($router): void {

    // ── Auth ─────────────────────────────────────────────────
    $router->post('/auth/login',   'Api\AuthApiController@login');
    $router->post('/auth/refresh', 'Api\AuthApiController@refresh');

    // ── Projects ─────────────────────────────────────────────
    $router->get('/projects',      'Api\ProjectApiController@index');
    $router->get('/projects/{id}', 'Api\ProjectApiController@show');
    $router->post('/projects',     'Api\ProjectApiController@store');
    $router->put('/projects/{id}', 'Api\ProjectApiController@update');
    $router->delete('/projects/{id}','Api\ProjectApiController@destroy');

    // ── Plans ────────────────────────────────────────────────
    $router->get('/plans',         'Api\PlanApiController@index');
    $router->get('/plans/{id}',    'Api\PlanApiController@show');

    // ── Symbols ──────────────────────────────────────────────
    $router->get('/symbols',       'Api\SymbolApiController@index');
    $router->get('/symbols/{id}',  'Api\SymbolApiController@show');

    // ── API Documentation ─────────────────────────────────────
    $router->get('/docs',          'Api\DocsApiController@index');
    $router->get('/openapi.json',  'Api\DocsApiController@openapi');
});

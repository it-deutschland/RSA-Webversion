<?php

declare(strict_types=1);

/**
 * Web Routes
 * Registered by App\Core\App via routes/web.php
 *
 * @var App\Core\Router $router
 */

// ── Auth ─────────────────────────────────────────────────────
$router->get('/login',            'AuthController@showLogin');
$router->post('/login',           'AuthController@login');
$router->get('/logout',           'AuthController@logout');
$router->get('/register',         'AuthController@showRegister');
$router->post('/register',        'AuthController@register');
$router->get('/forgot',           'AuthController@showForgot');
$router->post('/forgot',          'AuthController@forgotPassword');
$router->get('/reset/{token}',    'AuthController@showReset');
$router->post('/reset',           'AuthController@resetPassword');
$router->get('/2fa',              'AuthController@show2fa');
$router->post('/2fa',             'AuthController@verify2fa');
$router->get('/profile',          'AuthController@profile');
$router->post('/profile',         'AuthController@updateProfile');
$router->post('/profile/password','AuthController@changePassword');
$router->post('/profile/2fa/enable',  'AuthController@enable2fa');
$router->post('/profile/2fa/disable', 'AuthController@disable2fa');

// ── Dashboard ─────────────────────────────────────────────────
$router->get('/',          'DashboardController@index');
$router->get('/dashboard', 'DashboardController@index');

// ── Projects ──────────────────────────────────────────────────
$router->get('/projects',              'ProjectController@index');
$router->get('/projects/create',       'ProjectController@create');
$router->post('/projects',             'ProjectController@store');
$router->get('/projects/{id}',         'ProjectController@show');
$router->get('/projects/{id}/edit',    'ProjectController@edit');
$router->post('/projects/{id}',        'ProjectController@update');
$router->post('/projects/{id}/delete', 'ProjectController@destroy');
$router->post('/projects/{id}/upload', 'ProjectController@upload');

// ── Plans ─────────────────────────────────────────────────────
$router->get('/plans',                       'PlanController@index');
$router->get('/projects/{pid}/plans/create', 'PlanController@create');
$router->post('/projects/{pid}/plans',       'PlanController@store');
$router->get('/plans/{id}/editor',           'PlanController@editor');
$router->post('/plans/{id}/save',            'PlanController@save');
$router->get('/plans/{id}/export',           'PlanController@exportForm');
$router->post('/plans/{id}/export',          'PlanController@export');
$router->get('/plans/{id}/edit',             'PlanController@edit');
$router->post('/plans/{id}',                 'PlanController@update');
$router->post('/plans/{id}/delete',          'PlanController@destroy');

// ── Documents ─────────────────────────────────────────────────
$router->get('/documents',                       'DocumentController@index');
$router->get('/projects/{pid}/documents/create', 'DocumentController@create');
$router->post('/projects/{pid}/documents',       'DocumentController@store');
$router->get('/documents/{id}',                  'DocumentController@show');
$router->get('/documents/{id}/edit',             'DocumentController@edit');
$router->post('/documents/{id}',                 'DocumentController@update');
$router->post('/documents/{id}/delete',          'DocumentController@destroy');
$router->get('/documents/{id}/pdf',              'DocumentController@exportPdf');
$router->get('/documents/{id}/print',            'DocumentController@printView');

// ── Materials ─────────────────────────────────────────────────
$router->get('/materials',              'MaterialController@index');
$router->get('/materials/create',       'MaterialController@create');
$router->post('/materials',             'MaterialController@store');
$router->get('/materials/{id}/edit',    'MaterialController@edit');
$router->post('/materials/{id}',        'MaterialController@update');
$router->post('/materials/{id}/delete', 'MaterialController@destroy');

// ── Symbols ───────────────────────────────────────────────────
$router->get('/symbols',              'SymbolController@index');
$router->get('/symbols/create',       'SymbolController@create');
$router->post('/symbols',             'SymbolController@store');
$router->post('/symbols/import',      'SymbolController@import');
$router->get('/symbols/{id}/edit',    'SymbolController@edit');
$router->post('/symbols/{id}',        'SymbolController@update');
$router->post('/symbols/{id}/delete', 'SymbolController@destroy');
$router->post('/symbols/{id}/favourite','SymbolController@toggleFavourite');

// ── Customers ─────────────────────────────────────────────────
$router->get('/customers',              'CustomerController@index');
$router->get('/customers/create',       'CustomerController@create');
$router->post('/customers',             'CustomerController@store');
$router->get('/customers/{id}/edit',    'CustomerController@edit');
$router->post('/customers/{id}',        'CustomerController@update');
$router->post('/customers/{id}/delete', 'CustomerController@destroy');

// ── Users ─────────────────────────────────────────────────────
$router->get('/users',              'UserController@index');
$router->get('/users/create',       'UserController@create');
$router->post('/users',             'UserController@store');
$router->get('/users/{id}/edit',    'UserController@edit');
$router->post('/users/{id}',        'UserController@update');
$router->post('/users/{id}/delete', 'UserController@destroy');

// ── Settings ──────────────────────────────────────────────────
$router->get('/settings',           'SettingsController@index');
$router->post('/settings',          'SettingsController@update');
$router->get('/settings/smtp-test', 'SettingsController@testSmtp');

// ── Backup ────────────────────────────────────────────────────
$router->get('/backup',              'BackupController@index');
$router->post('/backup/create',      'BackupController@create');
$router->get('/backup/download/{f}', 'BackupController@download');
$router->post('/backup/restore',     'BackupController@restore');
$router->post('/backup/delete/{f}',  'BackupController@delete');

// ── Notifications ─────────────────────────────────────────────
$router->get('/notifications',             'NotificationController@index');
$router->post('/notifications/read/{id}',  'NotificationController@markRead');
$router->post('/notifications/read-all',   'NotificationController@markAllRead');

// ── Uploads (AJAX) ────────────────────────────────────────────
$router->post('/upload',              'UploadController@store');
$router->post('/upload/{id}/delete',  'UploadController@destroy');
$router->get('/uploads/{id}',         'UploadController@serve');

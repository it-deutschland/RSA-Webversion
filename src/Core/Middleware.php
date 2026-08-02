<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Middleware runner.
 */
class Middleware
{
    public static function handle(string $name): void
    {
        $middleware = strtolower(trim($name));

        match ($middleware) {
            'auth' => self::requireAuth(),
            'guest' => self::requireGuest(),
            'csrf' => CSRF::check(),
            'admin', 'editor', 'reviewer' => self::requireRole($middleware),
            default => null,
        };
    }

    private static function requireAuth(): void
    {
        if (Auth::guest()) {
            Session::flash('error', 'Please sign in to continue.');
            Response::redirect('/login');
        }
    }

    private static function requireGuest(): void
    {
        if (!Auth::guest()) {
            Response::redirect('/');
        }
    }

    private static function requireRole(string $role): void
    {
        if (Auth::guest()) {
            Session::flash('error', 'Please sign in to continue.');
            Response::redirect('/login');
        }

        if (!Auth::hasRole($role)) {
            Response::forbidden();
        }
    }
}

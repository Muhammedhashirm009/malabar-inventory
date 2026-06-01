<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

if (env('APP_ENV') !== 'testing' && !$app->runningUnitTests()) {
    $storagePath = null;
    $dbPath = null;

    if (env('APP_STORAGE_PATH')) {
        $storagePath = env('APP_STORAGE_PATH');
    } else {
        $appData = $_SERVER['APPDATA'] ?? $_ENV['APPDATA'] ?? getenv('APPDATA');
        if ($appData) {
            $storagePath = $appData . DIRECTORY_SEPARATOR . 'com.lamyapro.app' . DIRECTORY_SEPARATOR . 'storage';
        }
    }

    if (env('DB_DATABASE')) {
        $dbPath = env('DB_DATABASE');
    } else {
        $appData = $_SERVER['APPDATA'] ?? $_ENV['APPDATA'] ?? getenv('APPDATA');
        if ($appData) {
            $dbPath = $appData . DIRECTORY_SEPARATOR . 'com.lamyapro.app' . DIRECTORY_SEPARATOR . 'database.sqlite';
        }
    }

    if ($storagePath) {
        // Ensure the directories exist
        if (!is_dir($storagePath)) {
            @mkdir($storagePath, 0755, true);
        }
        foreach (['app', 'logs', 'framework/cache', 'framework/sessions', 'framework/views'] as $dir) {
            $subDir = $storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
            if (!is_dir($subDir)) {
                @mkdir($subDir, 0755, true);
            }
        }
        $app->useStoragePath($storagePath);
    }

    if ($dbPath) {
        // Copy the bundled seed database if it doesn't exist in AppData
        if (!file_exists($dbPath)) {
            $seedDb = base_path('database/database.sqlite');
            if (file_exists($seedDb)) {
                @copy($seedDb, $dbPath);
            } else {
                @file_put_contents($dbPath, '');
            }
        }
        
        $_ENV['DB_DATABASE'] = $dbPath;
        $_SERVER['DB_DATABASE'] = $dbPath;
        putenv("DB_DATABASE={$dbPath}");
    }
}

return $app;

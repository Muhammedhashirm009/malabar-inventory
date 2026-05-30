<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                foreach (\App\Models\Setting::all() as $setting) {
                    config()->set('settings.' . $setting->key, $setting->value);
                }
            }
        } catch (\Exception $e) {
            // Prevent breaking migrations or command bootstrapping before DB is migrated
        }
    }
}

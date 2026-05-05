<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\View;

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
        View::composer('*', function ($view) {
            $themes = config('themes') ?? [];
            $currentTheme = request()->cookie('selected_theme', 'sunset');
            if (empty($themes) || !array_key_exists($currentTheme, $themes)) {
                $currentTheme = 'sunset';
            }
            $theme = $themes[$currentTheme] ?? [];
            $view->with(compact('themes', 'currentTheme', 'theme'));
        });
    }
}

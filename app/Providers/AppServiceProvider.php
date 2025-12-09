<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        view()->composer('layouts.app', function ($view) {
            $breadcrumbs = [];
            if (!empty($titles = explode('/', Str::replaceFirst(config('app.url') . '/', '', request()->url())))) {
                foreach ($titles as $value) $breadcrumbs[] = Str::title(str_replace('-', ' ', $value));
            } else {
                $breadcrumbs[] = "Dasbor";
            }

            $view->with('breadcrumbs', $breadcrumbs);
        });
    }
}

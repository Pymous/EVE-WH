<?php

namespace App\Providers;

use App\Services\Eve\EveEsi;
use App\Services\Eve\EveSso;
use Illuminate\Support\ServiceProvider;

class EveServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the EveEsi service as a singleton
        $this->app->singleton(EveEsi::class, function ($app) {
            return new EveEsi();
        });

        // Register the EveSso service as a singleton
        $this->app->singleton(EveSso::class, function ($app) {
            return new EveSso();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

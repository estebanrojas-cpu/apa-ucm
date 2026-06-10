<?php

namespace App\Providers;

use App\Contracts\ApaDataSource;
use App\Services\ManualApaSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApaDataSource::class, ManualApaSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

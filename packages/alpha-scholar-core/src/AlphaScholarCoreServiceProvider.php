<?php

namespace Corasoft\AlphaScholar;

use Illuminate\Support\ServiceProvider;

class AlphaScholarCoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

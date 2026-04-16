<?php

namespace App\Providers;

use App\Services\GitWorktreeService;
use App\Services\SelfUpdateService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GitWorktreeService::class);
        $this->app->singleton(SelfUpdateService::class);
    }

    public function boot(): void
    {
        //
    }
}

<?php

namespace Alihaiderx\LaravelSpool\Providers;

use Alihaiderx\LaravelSpool\Services\FileSystemBufferService;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{

  public function register() {
    $this->registerSingletonServices();
  }

  public function boot()
  {
    $this->loadRoutes();
  }

  protected function loadRoutes(): void
  {
    $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
  }

  protected function registerSingletonServices(): void
  {
    $this->app->singleton('alihaiderx.laravel-spool.file-system-buffer', function ($app) {
      return new FileSystemBufferService();
    });
  }
}

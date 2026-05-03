<?php

namespace Alihaiderx\LaravelSpool\Providers;

use Alihaiderx\LaravelSpool\Commands\InstallCommand;
use Alihaiderx\LaravelSpool\Services\BufferService;
use Alihaiderx\LaravelSpool\Services\FileSystemBufferService;
use Alihaiderx\LaravelSpool\Services\RedisBufferService;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{

  public function register()
  {
    $this->registerSingletonServices();
  }

  public function boot()
  {
    $this->publishResources();
    $this->loadRoutes();
    $this->registerCommands();
  }

  protected function publishResources(): void
  {
    $this->publishes([
      __DIR__ . '/../../config/spool.php' => config_path('spool.php')
    ], 'spool');
  }

  protected function loadRoutes(): void
  {
    $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
  }

  protected function registerCommands(): void
  {
    if (!$this->app->runningInConsole()) return;

    $this->commands([
      InstallCommand::class
    ]);
  }

  protected function registerSingletonServices(): void
  {
    $this->app->singleton('alihaiderx.laravel-spool.file-system-buffer', function ($app) {
      return new FileSystemBufferService();
    });

    $this->app->singleton('alihaiderx.laravel-spool.redis-buffer', function ($app) {
      return new RedisBufferService();
    });

    $this->app->singleton('alihaiderx.laravel-spool.buffer', function ($app) {
      return new BufferService();
    });
  }
}

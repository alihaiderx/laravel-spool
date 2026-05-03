<?php

namespace Alihaiderx\LaravelSpool\Providers;

use Alihaiderx\LaravelSpool\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{

  public function register() {}

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
}

<?php

namespace Alihaiderx\LaravelSpool\Providers;

use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{

  public function register() {
   
  }

  public function boot()
  {
    $this->loadRoutes();
  }

  protected function loadRoutes(): void {
    $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
  }
}

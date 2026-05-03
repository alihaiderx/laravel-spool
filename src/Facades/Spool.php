<?php

namespace Alihaiderx\LaravelSpool\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

class Spool extends Facade
{
  static function getFacadeAccessor()
  {
    return 'alihaiderx.laravel-spool.spool';
  }
}

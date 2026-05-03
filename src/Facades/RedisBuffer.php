<?php

namespace Alihaiderx\LaravelSpool\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

class RedisBuffer extends Facade {

  public static function getFacadeAccessor()
  {
    return 'alihaiderx.laravel-spool.redis-buffer';
  }

}


?>
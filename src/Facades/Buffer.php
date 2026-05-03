<?php

namespace Alihaiderx\LaravelSpool\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

class Buffer extends Facade {

  public static function getFacadeAccessor()
  {
    return 'alihaiderx.laravel-spool.buffer';
  }

}

?>
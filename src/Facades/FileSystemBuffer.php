<?php

namespace Alihaiderx\LaravelSpool\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

class FileSystemBuffer extends Facade {

  public static function getFacadeAccessor()
  {
    return 'alihaiderx.laravel-spool.file-system-buffer';
  }

}

?>
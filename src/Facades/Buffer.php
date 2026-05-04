<?php

namespace Alihaiderx\LaravelSpool\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void listenRedis(callable $callback)
 * @method static string|null buffer(array $arr, string $bucketSlug)
 *
 * @see \Alihaiderx\LaravelSpool\Services\BufferService
 */
class Buffer extends Facade {

  public static function getFacadeAccessor()
  {
    return 'alihaiderx.laravel-spool.buffer';
  }

}

?>
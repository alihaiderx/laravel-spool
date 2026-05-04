<?php

namespace Alihaiderx\LaravelSpool\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isAvailable()
 * @method static bool checkRedis()
 * @method static array createConsumer()
 * @method static string|null buffer(array $arr, string $bucketSlug)
 * @method static void startConsume()
 *
 * @see \Alihaiderx\LaravelSpool\Services\RedisBufferService
 */
class RedisBuffer extends Facade {

  public static function getFacadeAccessor()
  {
    return 'alihaiderx.laravel-spool.redis-buffer';
  }

}


?>
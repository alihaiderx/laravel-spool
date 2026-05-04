<?php

namespace Alihaiderx\LaravelSpool\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed getDirs()
 * @method static void setupDirs()
 * @method static string|null buffer(array $arr, string $bucketSlug)
 * @method static string generateSharedFileName(string $bucketSlug, string $shard, string $type = 'bufferActive')
 * @method static bool|null flush(callable $callback, ?string $bucketSlug = null)
 * @method static array clean(int $numberOfFiles = 10, ?string $bucketSlug = null)
 * @method static mixed getShardAgeInDays(string $file)
 *
 * @see \Alihaiderx\LaravelSpool\Services\FileSystemBufferService
 */
class FileSystemBuffer extends Facade {

  public static function getFacadeAccessor()
  {
    return 'alihaiderx.laravel-spool.file-system-buffer';
  }

}

?>
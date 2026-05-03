<?php

namespace Alihaiderx\LaravelSpool\Services;

use Alihaiderx\LaravelSpool\Facades\FileSystemBuffer;
use Alihaiderx\LaravelSpool\Facades\RedisBuffer;

class BufferService
{

  function buffer(array $arr, string $bucketSlug): string|null
  {
    if (RedisBuffer::isAvailable()) return RedisBuffer::buffer($arr, $bucketSlug);
    return FileSystemBuffer::buffer($arr, $bucketSlug);
  }
}

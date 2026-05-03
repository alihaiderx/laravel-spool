<?php

namespace Alihaiderx\LaravelSpool\Services;

use Alihaiderx\LaravelSpool\Events\RedisBufferConsumeEvent;
use Alihaiderx\LaravelSpool\Facades\FileSystemBuffer;
use Alihaiderx\LaravelSpool\Facades\RedisBuffer;
use Illuminate\Support\Facades\Event;

class BufferService
{

  function listenRedis(callable $callback): void
  {
    Event::listen(RedisBufferConsumeEvent::class, $callback);
  }

  function buffer(array $arr, string $bucketSlug): string|null
  {
    if (RedisBuffer::isAvailable()) return RedisBuffer::buffer($arr, $bucketSlug);
    return FileSystemBuffer::buffer($arr, $bucketSlug);
  }
}

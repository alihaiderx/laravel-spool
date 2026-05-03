<?php

namespace Alihaiderx\LaravelSpool\Services;

use Alihaiderx\LaravelSpool\Events\RedisBufferConsumeEvent;
use Exception;
use Illuminate\Support\Facades\Redis;

class RedisBufferService
{

  private static ?bool $available = null;

  static function isAvailable(): bool
  {
    if ((config('spool.buffer_driver') !== 'redis')) return false;

    if (self::$available !== null) {
      return self::$available;
    }

    self::$available = self::checkRedis();

    return self::$available;
  }

  static function checkRedis(): bool
  {
    try {
      Redis::ping();
      return true;
    } catch (\Throwable $e) {
      return false;
    }
  }

  function createConsumer(): array
  {
    try {
      Redis::xgroup('CREATE', 'alihaiderx.laravel-spool.buffer', 'alihaiderx.laravel-spool.buffer-consumer', 0, true);
      return ['status' => true];
    } catch (Exception $e) {
      return ['status' => false, 'msg' => $e->getMessage()];
    }
  }

  function buffer(array $arr, string $bucketSlug): string|null
  {
    $payload = serialize($arr['payload'] ?? '');
    Redis::connection()->command('xadd', ['alihaiderx.laravel-spool.buffer', ['payload' => $payload, 'bucketSlug'=>$bucketSlug]]);
    return 'ok';
  }

  function startConsume(): void{
    
    $event = 'alihaiderx.laravel-spool.buffer';
    $group = 'alihaiderx.laravel-spool.buffer-consumer';
    $consumer = gethostname() . '-' . getmypid();
    $spoolConfig = config('spool');
    $batchSize = $spoolConfig['redis_batch_size'];

    if (function_exists('pcntl_async_signals')){
      pcntl_async_signals(true);
      pcntl_signal(SIGTERM, function () {
        exit;
      });
    }

    while (true) {
    
      $messages = Redis::connection()->command('xreadgroup', [$group, $consumer, $batchSize, 2000, false, $event, '>']);

      if (!$messages) continue;

      $batch = [];
      $ids = [];

      foreach ($messages as [$streamName, $streamMessages]) {
        foreach ($streamMessages as [$id, $rawFields]) {
          $fields = [];
          for ($i = 0; $i < count($rawFields); $i += 2) {
            $fields[$rawFields[$i]] = $rawFields[$i + 1];
          }
    
          $batch[] = [
            'payload'=> unserialize($fields['payload']),
            'bucketSlug'=> $fields['bucketSlug']
          ];
          $ids[] = $id;
        }
      }

      Redis::connection()->command('xack', [$event, $group, ...$ids]);

      event(new RedisBufferConsumeEvent($batch));

    }
  }

}

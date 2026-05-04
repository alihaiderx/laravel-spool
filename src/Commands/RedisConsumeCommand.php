<?php

namespace Alihaiderx\LaravelSpool\Commands;

use Alihaiderx\LaravelSpool\Facades\RedisBuffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RedisConsumeCommand extends Command
{

  protected $signature = 'spool:start-redis-consume';
  protected $description = 'Start long running process to consume redis streams.';

  public function handle() {
    try {
      RedisBuffer::createConsumer();
      RedisBuffer::startConsume();
    }
    catch(Throwable $e){
      $this->error($e->getMessage());
      Log::error($e->getMessage(), ['trace'=>$e->getTraceAsString()]);
    }
  }
}

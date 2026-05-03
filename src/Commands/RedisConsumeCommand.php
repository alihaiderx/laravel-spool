<?php

namespace Alihaiderx\LaravelSpool\Commands;

use Alihaiderx\LaravelSpool\Facades\RedisBuffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RedisConsumeCommand extends Command
{

  protected $signature = 'spool:start-redis-consume';
  protected $description = 'Start long running process to consume redis streams.';

  public function handle() {
    RedisBuffer::createConsumer();
    RedisBuffer::startConsume();
  }
}

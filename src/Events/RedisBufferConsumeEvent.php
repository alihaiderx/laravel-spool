<?php

namespace Alihaiderx\LaravelSpool\Events;

class RedisBufferConsumeEvent
{

  function __construct(
    public array $batch
  ) {}
}
